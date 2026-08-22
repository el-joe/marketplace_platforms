<?php

namespace App\Services;

use App\DTOs\Payment\ConnectionTestResult;
use App\DTOs\Payment\PaymentInitiationData;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentVerificationResult;
use App\DTOs\Payment\RefundResult;
use App\Models\CountryPaymentGateway;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Support\Str;

class PaymentService
{
    public function initiatePayment(
        Order $order,
        CountryPaymentGateway $gatewayConfig,
        ?string $idempotencyKey = null,
    ): PaymentInitiationResult {
        $gateway        = PaymentGatewayFactory::make($gatewayConfig);
        $idempotencyKey ??= (string) Str::uuid();

        $data = new PaymentInitiationData(
            orderId:       $order->id,
            orderNumber:   $order->order_number,
            amountCents:   $order->total,
            currency:      $gatewayConfig->effective_currency,
            customerId:    $order->customer_id,
            customerEmail: $order->customer->email,
            customerPhone: $order->customer->phone ?? null,
            successUrl:    route('checkout.success', $order->order_number),
            cancelUrl:     route('checkout.cancel', $order->order_number),
            webhookUrl:    route('webhooks.payment', $gateway->getCode()),
            metadata:      ['idempotency_key' => $idempotencyKey],
        );

        $result = $gateway->initiate($data);

        PaymentTransaction::create([
            'order_id'               => $order->id,
            'customer_id'            => $order->customer_id,
            'type'                   => 'authorization',
            'gateway'                => $gateway->getCode(),
            'gateway_transaction_id' => $result->gatewayTransactionId ?? ('PENDING-' . $idempotencyKey),
            'idempotency_key'        => $idempotencyKey,
            'amount'                 => $order->total,
            'currency'               => $data->currency,
            'status'                 => $result->success ? 'pending' : 'failed',
            'failure_message'        => $result->errorMessage,
            'raw_request'            => (array) $data,
            'raw_response'           => $result->rawResponse,
        ]);

        return $result;
    }

    public function verifyAndCapture(PaymentTransaction $transaction): PaymentVerificationResult
    {
        $gatewayConfig = CountryPaymentGateway::byGatewayCode($transaction->gateway)
            ->forCountry($transaction->order->customer->country_id)
            ->firstOrFail();

        $gateway = PaymentGatewayFactory::make($gatewayConfig);
        $result  = $gateway->verify($transaction->gateway_transaction_id);

        $transaction->update([
            'status'          => $result->status,
            'failure_code'    => $result->failureCode    ?? null,
            'failure_message' => $result->failureMessage ?? null,
            'raw_response'    => $result->rawResponse,
            'processed_at'    => now(),
        ]);

        if ($result->success) {
            $transaction->order->update(['payment_status' => 'captured']);
        }

        return $result;
    }

    public function refund(
        PaymentTransaction $originalTransaction,
        int $amountCents,
        string $reason,
    ): RefundResult {
        $gatewayConfig = CountryPaymentGateway::byGatewayCode($originalTransaction->gateway)
            ->forCountry($originalTransaction->order->customer->country_id)
            ->firstOrFail();

        $gateway = PaymentGatewayFactory::make($gatewayConfig);
        $result  = $gateway->refund($originalTransaction->gateway_transaction_id, $amountCents, $reason);

        if ($result->success) {
            PaymentTransaction::create([
                'order_id'               => $originalTransaction->order_id,
                'customer_id'            => $originalTransaction->customer_id,
                'type'                   => 'refund',
                'gateway'                => $gateway->getCode(),
                'gateway_transaction_id' => $result->refundTransactionId ?? ('REFUND-' . Str::uuid()),
                'idempotency_key'        => (string) Str::uuid(),
                'amount'                 => -$result->refundedAmountCents,
                'currency'               => $originalTransaction->currency,
                'status'                 => 'succeeded',
                'raw_response'           => $result->rawResponse,
                'processed_at'           => now(),
            ]);
        }

        return $result;
    }

    public function testGatewayConnection(CountryPaymentGateway $gatewayConfig): ConnectionTestResult
    {
        $gateway = PaymentGatewayFactory::make($gatewayConfig);
        $result  = $gateway->testConnection();

        $gatewayConfig->update([
            'last_verified_at'           => now(),
            'last_verification_status'   => $result->success ? 'success' : 'failed',
            'last_verification_message'  => $result->message,
        ]);

        return $result;
    }
}
