<?php

namespace App\Services\Payments;

use App\DTOs\Payment\ConnectionTestResult;
use App\DTOs\Payment\PaymentInitiationData;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentVerificationResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\WebhookResult;
use App\Enums\PaymentTransactionStatus;

/**
 * Bank Transfer — no external API. Creates a pending transaction.
 * Customer is shown bank account details from credentials.
 * Admin manually confirms receipt and marks as captured.
 *
 * Credentials stored in country_payment_gateways.credentials_encrypted:
 *   {
 *     "bank_name": "Bank Muscat",
 *     "account_name": "Marketplace LLC",
 *     "account_number": "12345678",
 *     "iban": "OM91BMUS0000000012345678",
 *     "swift": "BMUSOMOMXXX",
 *     "reference_instructions": "Use your order number as reference"
 *   }
 */
class BankTransferGateway extends AbstractPaymentGateway
{
    public function getCode(): string
    {
        return 'bank_transfer';
    }

    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $creds = $this->credentials();

        return new PaymentInitiationResult(
            success: true,
            redirectUrl: null,
            gatewayTransactionId: 'BT-' . $data->orderNumber,
            rawResponse: [
                'bank_name'              => $creds['bank_name'] ?? null,
                'account_name'           => $creds['account_name'] ?? null,
                'account_number'         => $creds['account_number'] ?? null,
                'iban'                   => $creds['iban'] ?? null,
                'swift'                  => $creds['swift'] ?? null,
                'reference'              => $data->orderNumber,
                'reference_instructions' => $creds['reference_instructions'] ?? 'Use your order number as the payment reference.',
                'amount'                 => $data->amountCents,
                'currency'               => $data->currency,
            ],
        );
    }

    public function verify(string $transactionReference): PaymentVerificationResult
    {
        return new PaymentVerificationResult(
            success: false,
            status: PaymentTransactionStatus::Pending->value,
            amountCents: 0,
            currency: $this->settlementCurrency(),
            gatewayTransactionId: $transactionReference,
            failureMessage: 'Bank transfer verification is manual — awaiting admin confirmation.',
        );
    }

    public function refund(string $transactionReference, int $amountCents, string $reason): RefundResult
    {
        return new RefundResult(
            success: false,
            errorMessage: 'Bank transfer refunds must be processed manually by the admin.',
        );
    }

    public function handleWebhook(array $payload, array $headers): WebhookResult
    {
        return new WebhookResult(
            signatureValid: true,
            eventType: null,
            gatewayTransactionId: null,
            orderReference: null,
            resultingStatus: PaymentTransactionStatus::Pending->value,
            parsedPayload: [],
        );
    }

    public function testConnection(): ConnectionTestResult
    {
        $creds = $this->credentials();

        $hasRequired = !empty($creds['bank_name']) && !empty($creds['account_number']);

        return new ConnectionTestResult(
            success: $hasRequired,
            message: $hasRequired ? 'Bank transfer details configured.' : 'Missing bank_name or account_number in credentials.',
        );
    }
}
