<?php

namespace Tests\Support;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\DTOs\Payment\ConnectionTestResult;
use App\DTOs\Payment\PaymentInitiationData;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentVerificationResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\WebhookResult;
use Illuminate\Support\Str;

/**
 * A scriptable fake payment gateway used in tests instead of hitting a real
 * gateway (Stripe/Thawani/Paytabs/...). Bind it in the container in place of
 * App\Contracts\Payments\PaymentGatewayInterface (or resolve it directly)
 * and script the outcome you want before exercising checkout/refund code.
 *
 * Usage:
 *   $gateway = new FakePaymentGateway();
 *   $gateway->scriptInitiate('success');   // or 'decline' | 'exception'
 *   $gateway->scriptRefund('success');     // or 'failure' | 'exception'
 *   app()->instance(PaymentGatewayInterface::class, $gateway);
 */
class FakePaymentGateway implements PaymentGatewayInterface
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_DECLINE = 'decline';
    public const OUTCOME_EXCEPTION = 'exception';
    public const OUTCOME_REFUND_SUCCESS = 'refund_success';
    public const OUTCOME_REFUND_FAILURE = 'refund_failure';

    /** @var array<int, array{outcome:string}> */
    protected array $initiateQueue = [];

    /** @var array<int, array{outcome:string}> */
    protected array $refundQueue = [];

    protected string $defaultInitiateOutcome = self::OUTCOME_SUCCESS;

    protected string $defaultRefundOutcome = self::OUTCOME_REFUND_SUCCESS;

    /** @var array<int, array<string, mixed>> */
    public array $initiateCalls = [];

    /** @var array<int, array<string, mixed>> */
    public array $refundCalls = [];

    protected string $code = 'stripe';

    public function withCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /** Script the next call to initiate(). Queue multiple calls if needed. */
    public function scriptInitiate(string $outcome): static
    {
        $this->initiateQueue[] = $outcome;

        return $this;
    }

    /** Script every future initiate() call that isn't explicitly queued. */
    public function scriptDefaultInitiate(string $outcome): static
    {
        $this->defaultInitiateOutcome = $outcome;

        return $this;
    }

    /** Script the next call to refund(). Queue multiple calls if needed. */
    public function scriptRefund(string $outcome): static
    {
        $this->refundQueue[] = $outcome;

        return $this;
    }

    /** Script every future refund() call that isn't explicitly queued. */
    public function scriptDefaultRefund(string $outcome): static
    {
        $this->defaultRefundOutcome = $outcome;

        return $this;
    }

    public function initiate(PaymentInitiationData $data): PaymentInitiationResult
    {
        $this->initiateCalls[] = ['data' => $data];

        $outcome = array_shift($this->initiateQueue) ?? $this->defaultInitiateOutcome;

        if ($outcome === self::OUTCOME_EXCEPTION) {
            throw new \RuntimeException('Fake gateway: simulated initiate exception');
        }

        if ($outcome === self::OUTCOME_DECLINE) {
            return new PaymentInitiationResult(
                success: false,
                errorMessage: 'card_declined',
            );
        }

        return new PaymentInitiationResult(
            success: true,
            redirectUrl: 'https://fake-gateway.test/pay/' . Str::uuid(),
            gatewayTransactionId: 'fake_txn_' . Str::random(16),
            sessionId: 'fake_session_' . Str::random(12),
        );
    }

    public function verify(string $transactionReference): PaymentVerificationResult
    {
        return new PaymentVerificationResult(
            success: true,
            status: 'succeeded',
            amountCents: 0,
            currency: 'AED',
            gatewayTransactionId: $transactionReference,
        );
    }

    public function refund(string $transactionReference, int $amountCents, string $reason): RefundResult
    {
        $this->refundCalls[] = compact('transactionReference', 'amountCents', 'reason');

        $outcome = array_shift($this->refundQueue) ?? $this->defaultRefundOutcome;

        if ($outcome === self::OUTCOME_EXCEPTION) {
            throw new \RuntimeException('Fake gateway: simulated refund exception');
        }

        if ($outcome === self::OUTCOME_REFUND_FAILURE) {
            return new RefundResult(
                success: false,
                refundTransactionId: null,
                refundedAmountCents: 0,
                errorMessage: 'refund_failed',
            );
        }

        return new RefundResult(
            success: true,
            refundTransactionId: 'fake_refund_' . Str::random(16),
            refundedAmountCents: $amountCents,
            errorMessage: null,
        );
    }

    public function handleWebhook(array $payload, array $headers): WebhookResult
    {
        return new WebhookResult(
            signatureValid: true,
            eventType: $payload['type'] ?? 'payment.succeeded',
            gatewayTransactionId: $payload['transaction_id'] ?? null,
            orderReference: $payload['order_reference'] ?? null,
            resultingStatus: 'captured',
            parsedPayload: $payload,
        );
    }

    public function testConnection(): ConnectionTestResult
    {
        return new ConnectionTestResult(success: true, message: 'ok');
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
