<?php

namespace App\Http\Controllers;

use App\Models\CountryPaymentGateway;
use App\Models\Order;
use App\Models\PaymentGatewayWebhookLog;
use App\Models\PaymentTransaction;
use App\Services\Checkout\CheckoutRollbackService;
use App\Services\Checkout\CouponUsageService;
use App\Services\LedgerService;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * enhancement.md P-05 task 5: webhooks are the SOURCE OF TRUTH for capture
 * — a redirect-based gateway's success/cancel callback is best-effort (the
 * customer's browser may never come back), so this is what actually moves
 * an order from 'pending' to 'captured'/'failed'. Made idempotent against
 * gateway retries via `payment_gateway_webhook_logs`.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService = new LedgerService(),
        private readonly CouponUsageService $couponUsageService = new CouponUsageService(),
        private readonly CheckoutRollbackService $rollbackService = new CheckoutRollbackService(),
    ) {}

    public function payment(Request $request, string $gatewayCode): JsonResponse
    {
        $methodConfig = CountryPaymentGateway::byGatewayCode($gatewayCode)->active()->first();

        if (!$methodConfig) {
            return response()->json(['error' => 'No active config for gateway'], 404);
        }

        $gateway = PaymentGatewayFactory::make($methodConfig);
        $result  = $gateway->handleWebhook(
            $request->all(),
            $request->headers->all(),
        );

        if (!$result->signatureValid) {
            PaymentGatewayWebhookLog::create([
                'country_payment_gateway_id' => $methodConfig->id,
                'gateway_code' => $gatewayCode,
                'event_type' => $result->eventType,
                'payload' => $result->parsedPayload,
                'headers' => $request->headers->all(),
                'signature_valid' => false,
                'processed' => false,
            ]);
            report(new \Exception("Invalid webhook signature for {$gatewayCode}"));
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $processed = false;
        $matchedTransactionId = null;

        if ($result->orderReference) {
            $order = Order::where('order_number', $result->orderReference)->first();

            if ($order) {
                $transaction = PaymentTransaction::where('order_id', $order->id)
                    ->where('gateway', $gatewayCode)
                    ->latest()
                    ->first();

                if ($transaction) {
                    $matchedTransactionId = $transaction->id;

                    // Idempotent against duplicate/retried webhooks for a
                    // transaction that has already reached a terminal
                    // state: don't re-post the ledger or re-consume/release
                    // the coupon a second time.
                    $alreadyTerminal = in_array($transaction->status->value, ['succeeded', 'failed', 'cancelled'], true);

                    $transaction->update([
                        'status'       => $result->resultingStatus,
                        'raw_response' => $result->parsedPayload,
                        'processed_at' => now(),
                    ]);

                    if (!$alreadyTerminal && $result->resultingStatus === 'succeeded') {
                        $order->update([
                            'payment_status' => 'captured',
                            'status'         => 'confirmed',
                        ]);
                        // enhancement.md P-03 task 5: ledger at capture.
                        $this->ledgerService->postOrderCapture($order, (int) $transaction->amount);
                        $this->couponUsageService->consumeForOrder($order);
                    } elseif (!$alreadyTerminal && in_array($result->resultingStatus, ['failed', 'cancelled', 'declined'], true)) {
                        $order->update(['payment_status' => 'failed', 'status' => 'cancelled']);
                        $this->rollbackService->rollback($order, 'Payment gateway webhook reported failure');
                    }

                    $processed = true;
                }
            }
        }

        PaymentGatewayWebhookLog::create([
            'country_payment_gateway_id' => $methodConfig->id,
            'gateway_code' => $gatewayCode,
            'event_type' => $result->eventType,
            'payload' => $result->parsedPayload,
            'headers' => $request->headers->all(),
            'signature_valid' => true,
            'processed' => $processed,
            'payment_transaction_id' => $matchedTransactionId,
        ]);

        return response()->json(['received' => true]);
    }
}
