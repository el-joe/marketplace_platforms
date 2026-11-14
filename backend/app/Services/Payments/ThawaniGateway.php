<?php

namespace App\Services\Payments;

use App\DTOs\Payment\ConnectionTestResult;
use App\DTOs\Payment\PaymentInitiationData;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentVerificationResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\WebhookResult;
use App\Enums\PaymentTransactionStatus;
use App\Models\Currency;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ThawaniGateway extends AbstractPaymentGateway
{
    private const BASE_URL_SANDBOX = 'https://uatcheckout.thawani.om/api/v1';

    private const BASE_URL_PRODUCTION = 'https://checkout.thawani.om/api/v1';

    private const CHECKOUT_URL_SANDBOX = 'https://uatcheckout.thawani.om/pay/';

    private const CHECKOUT_URL_PRODUCTION = 'https://checkout.thawani.om/pay/';

    public function getCode(): string
    {
        return 'thawani';
    }

    private function baseUrl(): string
    {
        return $this->isProduction() ? self::BASE_URL_PRODUCTION : self::BASE_URL_SANDBOX;
    }

    private function checkoutUrl(): string
    {
        return $this->isProduction() ? self::CHECKOUT_URL_PRODUCTION : self::CHECKOUT_URL_SANDBOX;
    }

    private function httpClient(): PendingRequest
    {
        $creds = $this->credentials();

        return Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Thawani-Api-Key' => $creds['secret_key'] ?? '',
                'Content-Type' => 'application/json',
            ])
            ->timeout(20);
    }

    /**
     * The platform carries decimal base-currency amounts (1.5 OMR); Thawani
     * expects integer minor units (1500 baisa for OMR's 3 decimal places).
     */
    private function toGatewayAmount(int|float $platformAmount, string $currency): int
    {
        return $this->toMinorUnits($platformAmount, $currency);
    }

    private function fromGatewayAmount(int|float $gatewayAmount, string $currency): int|float
    {
        return $this->fromMinorUnits($gatewayAmount, $currency);
    }

    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $currency = $this->settlementCurrency();
        $gatewayAmount = $this->toGatewayAmount($data->amountCents, $currency);

        $payload = [
            'client_reference_id' => $data->orderNumber,
            'mode' => 'payment',
            'products' => [[
                'name' => 'Order '.$data->orderNumber,
                'quantity' => 1,
                'unit_amount' => $gatewayAmount,
            ]],
            'success_url' => $data->successUrl,
            'cancel_url' => $data->cancelUrl,
            // Thawani's activation checklist requires the customer's name,
            // contact number and email to be passed on the session metadata.
            'metadata' => array_filter(array_merge($data->metadata, [
                'order_id' => $data->orderId,
                'customer_id' => $data->customerId,
                'customer_name' => $data->customerName,
                'customer_email' => $data->customerEmail,
                'customer_phone' => $data->customerPhone,
            ]), fn ($value) => $value !== null && $value !== ''),
        ];

        try {
            $response = $this->httpClient()->post('/checkout/session', $payload);

            if (! $response->successful()) {
                return new PaymentInitiationResult(
                    success: false,
                    errorMessage: 'Thawani error: '.$response->body(),
                    rawResponse: $response->json() ?? [],
                );
            }

            $body = $response->json();
            $sessionId = $body['data']['session_id'] ?? null;

            if (! $sessionId) {
                return new PaymentInitiationResult(
                    success: false,
                    errorMessage: 'No session_id returned',
                    rawResponse: $body,
                );
            }

            $publishableKey = $this->credentials()['publishable_key'] ?? '';

            return new PaymentInitiationResult(
                success: true,
                redirectUrl: $this->checkoutUrl().$sessionId.'?key='.$publishableKey,
                gatewayTransactionId: $sessionId,
                sessionId: $sessionId,
                rawResponse: $body,
            );
        } catch (\Throwable $e) {
            report($e);

            return new PaymentInitiationResult(
                success: false,
                errorMessage: 'Connection error: '.$e->getMessage(),
            );
        }
    }

    public function verify(string $transactionReference): PaymentVerificationResult
    {
        try {
            $response = $this->httpClient()->get('/checkout/session/'.$transactionReference);

            if (! $response->successful()) {
                return new PaymentVerificationResult(
                    success: false,
                    status: PaymentTransactionStatus::Failed->value,
                    amountCents: 0,
                    currency: $this->settlementCurrency(),
                    failureMessage: $response->body(),
                    rawResponse: $response->json() ?? [],
                );
            }

            $body = $response->json();
            $sessionData = $body['data'] ?? [];
            $rawStatus = $sessionData['payment_status'] ?? 'unpaid';

            $status = match ($rawStatus) {
                'paid' => PaymentTransactionStatus::Succeeded,
                'unpaid' => PaymentTransactionStatus::Pending,
                default => PaymentTransactionStatus::Failed,
            };

            $amount = $this->fromGatewayAmount(
                $sessionData['total_amount'] ?? 0,
                $this->settlementCurrency(),
            );

            return new PaymentVerificationResult(
                success: $status === PaymentTransactionStatus::Succeeded,
                status: $status->value,
                amountCents: $amount,
                currency: $this->settlementCurrency(),
                gatewayTransactionId: $transactionReference,
                rawResponse: $body,
            );
        } catch (\Throwable $e) {
            report($e);

            return new PaymentVerificationResult(
                success: false,
                status: PaymentTransactionStatus::Failed->value,
                amountCents: 0,
                currency: $this->settlementCurrency(),
                failureMessage: $e->getMessage(),
            );
        }
    }

    /**
     * Thawani refunds a payment, not a checkout session: the session's invoice leads to its
     * payment (GET /payments?checkout_invoice=…), whose payment_id is refunded with POST /refunds.
     * The whole payment is refunded, so a partial amount is refused here and should go to the
     * customer wallet instead.
     */
    public function refund(string $transactionReference, int|float $amountCents, string $reason): RefundResult
    {
        try {
            $session = $this->httpClient()->get('/checkout/session/'.$transactionReference);
            $invoice = $session->json('data.invoice');

            if (! $session->successful() || ! $invoice) {
                return new RefundResult(
                    success: false,
                    errorMessage: 'Thawani refund failed: checkout session or its invoice was not found.',
                    rawResponse: $session->json() ?? [],
                );
            }

            $payments = $this->httpClient()->get('/payments', [
                'checkout_invoice' => $invoice,
                'limit' => 10,
                'skip' => 0,
            ]);
            $paymentRows = collect($payments->json('data') ?? []);
            $payment = $paymentRows->first(fn (array $row) => ($row['status'] ?? null) === 'successful') ?? $paymentRows->first();

            if (! $payments->successful() || empty($payment['payment_id'])) {
                return new RefundResult(
                    success: false,
                    errorMessage: "Thawani refund failed: no payment found for invoice {$invoice}.",
                    rawResponse: $payments->json() ?? [],
                );
            }

            $paidAmount = $this->fromGatewayAmount($payment['amount'] ?? 0, $this->settlementCurrency());

            if ($paidAmount > 0 && $amountCents + 0.0005 < $paidAmount) {
                return new RefundResult(
                    success: false,
                    errorMessage: 'Thawani refunds the whole payment; refund this partial amount to the customer wallet instead.',
                    rawResponse: ['payment' => $payment],
                );
            }

            $response = $this->httpClient()->post('/refunds', [
                'payment_id' => $payment['payment_id'],
                'reason' => mb_substr($reason !== '' ? $reason : 'Refund', 0, 255),
                'metadata' => [
                    'checkout_session' => $transactionReference,
                    'invoice' => $invoice,
                ],
            ]);

            if (! $response->successful()) {
                return new RefundResult(
                    success: false,
                    errorMessage: 'Thawani refund failed: '.$response->body(),
                    rawResponse: $response->json() ?? [],
                );
            }

            $body = $response->json();

            return new RefundResult(
                success: true,
                refundTransactionId: $body['data']['refund_id'] ?? null,
                refundedAmountCents: $amountCents,
                rawResponse: $body,
            );
        } catch (\Throwable $e) {
            report($e);

            return new RefundResult(
                success: false,
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * Thawani signs every webhook: `thawani-signature` is the hex HMAC-SHA256 of
     * "{raw body}-{thawani-timestamp}" with the webhook secret from the Thawani merchant portal.
     * A webhook is accepted only when that secret is configured and the signature matches, and the
     * payment status is then re-read from Thawani's API instead of being trusted from the payload.
     */
    public function handleWebhook(array $payload, array $headers): WebhookResult
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        $header = fn (string $name): ?string => is_array($headers[$name] ?? null)
            ? ($headers[$name][0] ?? null)
            : ($headers[$name] ?? null);

        $webhookSecret = $this->config->getWebhookSecret();
        $signature = $header('thawani-signature');
        $timestamp = $header('thawani-timestamp');
        $rawBody = request()->getContent() ?: json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signatureValid = $webhookSecret !== null && $webhookSecret !== ''
            && $signature !== null && $timestamp !== null
            && hash_equals(hash_hmac('sha256', $rawBody.'-'.$timestamp, $webhookSecret), strtolower($signature));

        $data = $payload['data'] ?? $payload;
        $sessionId = $data['session_id'] ?? $payload['session_id'] ?? null;
        $orderReference = $data['client_reference_id'] ?? $payload['client_reference_id'] ?? null;
        $eventType = $payload['event_type'] ?? $data['payment_status'] ?? $data['status'] ?? null;

        $resultingStatus = PaymentTransactionStatus::Pending->value;
        if ($signatureValid && $sessionId) {
            $verified = $this->verify($sessionId);
            $resultingStatus = $verified->status;
            $orderReference ??= $verified->rawResponse['data']['client_reference_id'] ?? null;
        }

        $this->logWebhook(
            payload: $payload,
            headers: $headers,
            signatureValid: $signatureValid,
            eventType: $eventType,
        );

        return new WebhookResult(
            signatureValid: $signatureValid,
            eventType: $eventType,
            gatewayTransactionId: $sessionId,
            orderReference: $orderReference,
            resultingStatus: $resultingStatus,
            parsedPayload: $payload,
        );
    }

    public function testConnection(): ConnectionTestResult
    {
        $creds = $this->credentials();

        if (empty($creds['secret_key']) || empty($creds['publishable_key'])) {
            return new ConnectionTestResult(
                success: false,
                message: 'Missing secret_key or publishable_key in credentials',
            );
        }

        try {
            $response = $this->httpClient()->get('/checkout/session', ['limit' => 1]);

            return new ConnectionTestResult(
                success: $response->successful(),
                message: $response->successful()
                    ? 'Connected to Thawani '.($this->isProduction() ? 'production' : 'sandbox').' successfully'
                    : 'Thawani responded with status '.$response->status(),
                details: ['status_code' => $response->status()],
            );
        } catch (\Throwable $e) {
            return new ConnectionTestResult(
                success: false,
                message: 'Connection failed: '.$e->getMessage(),
            );
        }
    }
}
