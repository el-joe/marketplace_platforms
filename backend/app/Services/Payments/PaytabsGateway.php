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
 * Paytabs gateway.
 * Credentials stored in country_payment_gateways.credentials_encrypted:
 *   { "profile_id": "12345", "server_key": "sk_...", "base_url": "https://secure.paytabs.com" }
 */
class PaytabsGateway extends AbstractPaymentGateway
{
    public function getCode(): string
    {
        return 'paytabs';
    }

    private function baseUrl(): string
    {
        return $this->credentials()['base_url'] ?? 'https://secure.paytabs.com';
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'authorization' => $this->credentials()['server_key'] ?? '',
            'Content-Type'  => 'application/json',
        ])->timeout(20);
    }

    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $creds = $this->credentials();

        try {
            $response = $this->http()->post($this->baseUrl() . '/payment/request', [
                'profile_id'         => (int) ($creds['profile_id'] ?? 0),
                'tran_type'          => 'sale',
                'tran_class'         => 'ecom',
                'cart_id'            => $data->orderNumber,
                'cart_amount'        => $data->amountCents / 1000, // OMR: baisa to OMR
                'cart_currency'      => $data->currency,
                'cart_description'   => 'Order ' . $data->orderNumber,
                'customer_ref'       => $data->customerId,
                'customer_details'   => [
                    'name'  => $data->customerEmail,
                    'email' => $data->customerEmail,
                    'phone' => $data->customerPhone ?? '',
                ],
                'return'             => $data->successUrl,
                'callback'           => $data->webhookUrl,
            ]);

            if (!$response->successful()) {
                return new PaymentInitiationResult(
                    success: false,
                    errorMessage: 'Paytabs error: ' . $response->body(),
                    rawResponse: $response->json() ?? [],
                );
            }

            $body = $response->json();
            $redirectUrl = $body['redirect_url'] ?? null;

            if (!$redirectUrl) {
                return new PaymentInitiationResult(
                    success: false,
                    errorMessage: 'No redirect_url from Paytabs',
                    rawResponse: $body,
                );
            }

            return new PaymentInitiationResult(
                success: true,
                redirectUrl: $redirectUrl,
                gatewayTransactionId: $body['tran_ref'] ?? null,
                rawResponse: $body,
            );
        } catch (\Throwable $e) {
            report($e);
            return new PaymentInitiationResult(
                success: false,
                errorMessage: 'Paytabs connection error: ' . $e->getMessage(),
            );
        }
    }

    public function verify(string $transactionReference): PaymentVerificationResult
    {
        try {
            $creds    = $this->credentials();
            $response = $this->http()->post($this->baseUrl() . '/payment/query', [
                'profile_id' => (int) ($creds['profile_id'] ?? 0),
                'tran_ref'   => $transactionReference,
            ]);

            $body      = $response->json();
            $rawStatus = $body['payment_result']['response_status'] ?? 'A';

            $status = match ($rawStatus) {
                'A' => PaymentTransactionStatus::Succeeded,
                'P' => PaymentTransactionStatus::Pending,
                default => PaymentTransactionStatus::Failed,
            };

            return new PaymentVerificationResult(
                success: $status === PaymentTransactionStatus::Succeeded,
                status: $status->value,
                amountCents: (int) (($body['cart_amount'] ?? 0) * 1000),
                currency: $body['cart_currency'] ?? $this->settlementCurrency(),
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
            $creds    = $this->credentials();
            $response = $this->http()->post($this->baseUrl() . '/payment/refund', [
                'profile_id'       => (int) ($creds['profile_id'] ?? 0),
                'tran_ref'         => $transactionReference,
                'tran_type'        => 'refund',
                'tran_class'       => 'ecom',
                'cart_id'          => 'REFUND-' . $transactionReference,
                'cart_amount'      => $amountCents / 1000,
                'cart_currency'    => $this->settlementCurrency(),
                'cart_description' => $reason,
            ]);

            $body = $response->json();
            $ok   = ($body['payment_result']['response_status'] ?? '') === 'A';

            return new RefundResult(
                success: $ok,
                refundTransactionId: $body['tran_ref'] ?? null,
                refundedAmountCents: $ok ? $amountCents : 0,
                errorMessage: $ok ? null : ($body['payment_result']['response_message'] ?? 'Refund failed'),
                rawResponse: $body,
            );
        } catch (\Throwable $e) {
            report($e);
            return new RefundResult(success: false, errorMessage: $e->getMessage());
        }
    }

    public function handleWebhook(array $payload, array $headers): WebhookResult
    {
        $serverKey      = $this->credentials()['server_key'] ?? '';
        $signature      = $headers['signature'][0] ?? $headers['Signature'][0] ?? null;
        $signatureValid = true;

        if ($serverKey && $signature) {
            $computed       = base64_encode(hash_hmac('sha256', json_encode($payload), $serverKey, true));
            $signatureValid = hash_equals($computed, $signature);
        }

        $rawStatus = $payload['payment_result']['response_status'] ?? null;
        $status    = match ($rawStatus) {
            'A'     => PaymentTransactionStatus::Succeeded->value,
            'P'     => PaymentTransactionStatus::Pending->value,
            default => PaymentTransactionStatus::Failed->value,
        };

        $this->logWebhook($payload, $headers, $signatureValid, $rawStatus);

        return new WebhookResult(
            signatureValid: $signatureValid,
            eventType: $rawStatus,
            gatewayTransactionId: $payload['tran_ref'] ?? null,
            orderReference: $payload['cart_id'] ?? null,
            resultingStatus: $status,
            parsedPayload: $payload,
        );
    }

    public function testConnection(): ConnectionTestResult
    {
        $creds = $this->credentials();

        if (empty($creds['profile_id']) || empty($creds['server_key'])) {
            return new ConnectionTestResult(
                success: false,
                message: 'Missing profile_id or server_key in credentials.',
            );
        }

        try {
            $response = $this->http()->post($this->baseUrl() . '/payment/query', [
                'profile_id' => (int) $creds['profile_id'],
                'tran_ref'   => 'TEST',
            ]);

            // Paytabs returns 400 for invalid tran_ref — that still means the API is reachable
            $reachable = in_array($response->status(), [200, 400, 422]);

            return new ConnectionTestResult(
                success: $reachable,
                message: $reachable
                    ? 'Paytabs API reachable (' . ($this->isProduction() ? 'production' : 'sandbox') . ')'
                    : 'Paytabs responded with status ' . $response->status(),
            );
        } catch (\Throwable $e) {
            return new ConnectionTestResult(success: false, message: $e->getMessage());
        }
    }
}
