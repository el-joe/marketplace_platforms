<?php

namespace App\Services\Payments;

use App\DTOs\Payment\ConnectionTestResult;
use App\Enums\PaymentTransactionStatus;
use App\DTOs\Payment\PaymentInitiationData;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentVerificationResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\WebhookResult;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;

class ThawaniGateway extends AbstractPaymentGateway
{
    private const BASE_URL_SANDBOX    = 'https://uatcheckout.thawani.om/api/v1';
    private const BASE_URL_PRODUCTION = 'https://checkout.thawani.om/api/v1';
    private const CHECKOUT_URL_SANDBOX    = 'https://uatcheckout.thawani.om/pay/';
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

    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        $creds = $this->credentials();

        return Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Thawani-Api-Key' => $creds['secret_key'] ?? '',
                'Content-Type'    => 'application/json',
            ])
            ->timeout(20);
    }

    /**
     * Platform stores all amounts as integer subunits matching the currency's
     * decimal_places (e.g. OMR: 3 decimal places = stored in baisa already).
     * Thawani also expects baisa, so for OMR this is a 1:1 passthrough.
     */
    private function toGatewayAmount(int $platformCents, string $currency): int
    {
        // Both platform and Thawani use the currency's smallest subunit,
        // so no conversion is needed for OMR (or any 3-decimal currency).
        return $platformCents;
    }

    private function fromGatewayAmount(int $gatewayAmount, string $currency): int
    {
        return $gatewayAmount;
    }

    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $currency     = $this->settlementCurrency();
        $gatewayAmount = $this->toGatewayAmount($data->amountCents, $currency);

        $payload = [
            'client_reference_id' => $data->orderNumber,
            'mode'                => 'payment',
            'products'            => [[
                'name'        => 'Order ' . $data->orderNumber,
                'quantity'    => 1,
                'unit_amount' => $gatewayAmount,
            ]],
            'success_url' => $data->successUrl,
            'cancel_url'  => $data->cancelUrl,
            'metadata'    => array_merge($data->metadata, [
                'order_id'    => $data->orderId,
                'customer_id' => $data->customerId,
            ]),
        ];

        try {
            $response = $this->httpClient()->post('/checkout/session', $payload);

            if (!$response->successful()) {
                return new PaymentInitiationResult(
                    success: false,
                    errorMessage: 'Thawani error: ' . $response->body(),
                    rawResponse: $response->json() ?? [],
                );
            }

            $body      = $response->json();
            $sessionId = $body['data']['session_id'] ?? null;

            if (!$sessionId) {
                return new PaymentInitiationResult(
                    success: false,
                    errorMessage: 'No session_id returned',
                    rawResponse: $body,
                );
            }

            $publishableKey = $this->credentials()['publishable_key'] ?? '';

            return new PaymentInitiationResult(
                success: true,
                redirectUrl: $this->checkoutUrl() . $sessionId . '?key=' . $publishableKey,
                gatewayTransactionId: $sessionId,
                sessionId: $sessionId,
                rawResponse: $body,
            );
        } catch (\Throwable $e) {
            report($e);
            return new PaymentInitiationResult(
                success: false,
                errorMessage: 'Connection error: ' . $e->getMessage(),
            );
        }
    }

    public function verify(string $transactionReference): PaymentVerificationResult
    {
        try {
            $response = $this->httpClient()->get('/checkout/session/' . $transactionReference);

            if (!$response->successful()) {
                return new PaymentVerificationResult(
                    success: false,
                    status: PaymentTransactionStatus::Failed->value,
                    amountCents: 0,
                    currency: $this->settlementCurrency(),
                    failureMessage: $response->body(),
                    rawResponse: $response->json() ?? [],
                );
            }

            $body        = $response->json();
            $sessionData = $body['data'] ?? [];
            $rawStatus   = $sessionData['payment_status'] ?? 'unpaid';

            $status = match ($rawStatus) {
                'paid'   => PaymentTransactionStatus::Succeeded,
                'unpaid' => PaymentTransactionStatus::Pending,
                default  => PaymentTransactionStatus::Failed,
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

    public function refund(string $transactionReference, int $amountCents, string $reason): RefundResult
    {
        try {
            $response = $this->httpClient()
                ->post('/checkout/session/' . $transactionReference . '/refund', [
                    'reason' => $reason,
                ]);

            if (!$response->successful()) {
                return new RefundResult(
                    success: false,
                    errorMessage: 'Thawani refund failed: ' . $response->body(),
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

    public function handleWebhook(array $payload, array $headers): WebhookResult
    {
        $webhookSecret   = $this->config->getWebhookSecret();
        $signatureHeader = $headers['x-thawani-signature'][0]
            ?? $headers['X-Thawani-Signature'][0]
            ?? null;

        // Default to valid when no signing secret is configured or Thawani omits the header
        $signatureValid = true;
        if ($webhookSecret && $signatureHeader) {
            $expected       = hash_hmac('sha256', json_encode($payload), $webhookSecret);
            $signatureValid = hash_equals($expected, $signatureHeader);
        }

        $sessionId     = $payload['session_id'] ?? $payload['data']['session_id'] ?? null;
        $paymentStatus = $payload['payment_status'] ?? $payload['data']['payment_status'] ?? null;

        $resultingStatus = match ($paymentStatus) {
            'paid'   => PaymentTransactionStatus::Succeeded->value,
            'unpaid' => PaymentTransactionStatus::Pending->value,
            default  => PaymentTransactionStatus::Failed->value,
        };

        $this->logWebhook(
            payload: $payload,
            headers: $headers,
            signatureValid: $signatureValid,
            eventType: $paymentStatus,
        );

        return new WebhookResult(
            signatureValid: $signatureValid,
            eventType: $paymentStatus,
            gatewayTransactionId: $sessionId,
            orderReference: $payload['client_reference_id'] ?? null,
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
                    ? 'Connected to Thawani ' . ($this->isProduction() ? 'production' : 'sandbox') . ' successfully'
                    : 'Thawani responded with status ' . $response->status(),
                details: ['status_code' => $response->status()],
            );
        } catch (\Throwable $e) {
            return new ConnectionTestResult(
                success: false,
                message: 'Connection failed: ' . $e->getMessage(),
            );
        }
    }
}
