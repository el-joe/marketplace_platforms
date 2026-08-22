<?php

namespace App\Services\Payments;

use App\DTOs\Payment\ConnectionTestResult;
use App\DTOs\Payment\PaymentInitiationData;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentVerificationResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\WebhookResult;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Support\Facades\Http;

/**
 * Stripe gateway using the NEW PaymentGatewayInterface (initiate/verify/refund).
 * Credentials stored in country_payment_gateways.credentials_encrypted:
 *   { "secret_key": "sk_live_...", "publishable_key": "pk_live_..." }
 */
class StripeGateway extends AbstractPaymentGateway
{
    public function getCode(): string
    {
        return 'stripe';
    }

    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $creds = $this->credentials();
        $secretKey = $creds['secret_key'] ?? '';

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->asForm()
                ->timeout(20)
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'payment_method_types[]' => 'card',
                    'mode'                   => 'payment',
                    'client_reference_id'    => $data->orderNumber,
                    'customer_email'         => $data->customerEmail,
                    'success_url'            => $data->successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url'             => $data->cancelUrl,
                    'line_items[0][price_data][currency]'                  => strtolower($data->currency),
                    'line_items[0][price_data][unit_amount]'               => $data->amountCents,
                    'line_items[0][price_data][product_data][name]'        => 'Order ' . $data->orderNumber,
                    'line_items[0][quantity]'                              => 1,
                    'metadata[order_id]'     => $data->orderId,
                    'metadata[customer_id]'  => $data->customerId,
                ]);

            if (!$response->successful()) {
                return new PaymentInitiationResult(
                    success: false,
                    errorMessage: 'Stripe error: ' . $response->body(),
                    rawResponse: $response->json() ?? [],
                );
            }

            $body      = $response->json();
            $sessionId = $body['id'] ?? null;
            $redirectUrl = $body['url'] ?? null;

            if (!$sessionId || !$redirectUrl) {
                return new PaymentInitiationResult(
                    success: false,
                    errorMessage: 'No session URL returned from Stripe',
                    rawResponse: $body,
                );
            }

            return new PaymentInitiationResult(
                success: true,
                redirectUrl: $redirectUrl,
                gatewayTransactionId: $sessionId,
                sessionId: $sessionId,
                rawResponse: $body,
            );
        } catch (\Throwable $e) {
            report($e);
            return new PaymentInitiationResult(
                success: false,
                errorMessage: 'Stripe connection error: ' . $e->getMessage(),
            );
        }
    }

    public function verify(string $transactionReference): PaymentVerificationResult
    {
        $creds     = $this->credentials();
        $secretKey = $creds['secret_key'] ?? '';

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->timeout(20)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$transactionReference}");

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

            $body       = $response->json();
            $rawStatus  = $body['payment_status'] ?? 'unpaid';

            $status = match ($rawStatus) {
                'paid'       => PaymentTransactionStatus::Succeeded,
                'unpaid'     => PaymentTransactionStatus::Pending,
                default      => PaymentTransactionStatus::Failed,
            };

            return new PaymentVerificationResult(
                success: $status === PaymentTransactionStatus::Succeeded,
                status: $status->value,
                amountCents: (int) ($body['amount_total'] ?? 0),
                currency: strtoupper($body['currency'] ?? $this->settlementCurrency()),
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
        $creds     = $this->credentials();
        $secretKey = $creds['secret_key'] ?? '';

        try {
            $sessionResponse = Http::withBasicAuth($secretKey, '')
                ->timeout(20)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$transactionReference}");

            $paymentIntentId = $sessionResponse->json()['payment_intent'] ?? null;

            if (!$paymentIntentId) {
                return new RefundResult(success: false, errorMessage: 'No payment_intent found for session');
            }

            $response = Http::withBasicAuth($secretKey, '')
                ->asForm()
                ->timeout(20)
                ->post('https://api.stripe.com/v1/refunds', [
                    'payment_intent' => $paymentIntentId,
                    'amount'         => $amountCents,
                    'reason'         => 'requested_by_customer',
                    'metadata[reason]' => $reason,
                ]);

            if (!$response->successful()) {
                return new RefundResult(
                    success: false,
                    errorMessage: 'Stripe refund failed: ' . $response->body(),
                    rawResponse: $response->json() ?? [],
                );
            }

            $body = $response->json();
            return new RefundResult(
                success: true,
                refundTransactionId: $body['id'] ?? null,
                refundedAmountCents: $amountCents,
                rawResponse: $body,
            );
        } catch (\Throwable $e) {
            report($e);
            return new RefundResult(success: false, errorMessage: $e->getMessage());
        }
    }

    public function handleWebhook(array $payload, array $headers): WebhookResult
    {
        $webhookSecret   = $this->config->getWebhookSecret();
        $sigHeader       = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? null;
        $signatureValid  = true;

        if ($webhookSecret && $sigHeader) {
            $computedSig    = hash_hmac('sha256', json_encode($payload), $webhookSecret);
            $signatureValid = hash_equals($computedSig, $sigHeader);
        }

        $eventType = $payload['type'] ?? null;
        $data      = $payload['data']['object'] ?? [];

        $resultingStatus = match ($eventType) {
            'checkout.session.completed'        => PaymentTransactionStatus::Succeeded->value,
            'payment_intent.payment_failed'     => PaymentTransactionStatus::Failed->value,
            default                             => PaymentTransactionStatus::Pending->value,
        };

        $this->logWebhook(
            payload: $payload,
            headers: $headers,
            signatureValid: $signatureValid,
            eventType: $eventType,
        );

        return new WebhookResult(
            signatureValid: $signatureValid,
            eventType: $eventType,
            gatewayTransactionId: $data['id'] ?? null,
            orderReference: $data['client_reference_id'] ?? null,
            resultingStatus: $resultingStatus,
            parsedPayload: $payload,
        );
    }

    public function testConnection(): ConnectionTestResult
    {
        $creds = $this->credentials();

        if (empty($creds['secret_key'])) {
            return new ConnectionTestResult(success: false, message: 'Missing secret_key in credentials');
        }

        try {
            $response = Http::withBasicAuth($creds['secret_key'], '')
                ->timeout(10)
                ->get('https://api.stripe.com/v1/balance');

            return new ConnectionTestResult(
                success: $response->successful(),
                message: $response->successful() ? 'Stripe connected' : 'Stripe returned ' . $response->status(),
            );
        } catch (\Throwable $e) {
            return new ConnectionTestResult(success: false, message: $e->getMessage());
        }
    }
}
