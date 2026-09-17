<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Checkout\CheckoutRollbackService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles the browser redirect-back from redirect-based payment gateways
 * (Thawani, Stripe, PayPal).
 *
 * These routes are unauthenticated because the customer's session may not
 * survive the gateway redirect (especially on mobile WebView). Authentication
 * is not required here — the order is looked up by its unguessable order
 * number and verification happens against the gateway itself.
 */
class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly CheckoutRollbackService $rollbackService = new CheckoutRollbackService(),
    ) {}

    /**
     * GET /api/customer/v1/{country}/checkout/payment/success/{orderNumber}
     * Gateway redirects here after successful payment.
     */
    public function success(Request $request, string $country, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return ApiResponse::error('Order not found.', [], 404);
        }

        $transaction = PaymentTransaction::where('order_id', $order->id)
            ->where('status', PaymentTransactionStatus::Pending)
            ->latest()
            ->first();

        if ($transaction && !in_array($transaction->gateway, ['cod', 'bank_transfer'], true)) {
            try {
                $this->paymentService->verifyAndCapture($transaction);
                $order->refresh();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $order->refresh();

        return ApiResponse::success([
            'order_number'   => $order->order_number,
            'payment_status' => $order->payment_status,
            'status'         => $order->status,
            'verified'       => in_array($order->payment_status, [OrderPaymentStatus::Captured, OrderPaymentStatus::Authorized], true),
        ], 'Payment verified.');
    }

    /**
     * GET /api/customer/v1/{country}/checkout/payment/cancel/{orderNumber}
     * Gateway redirects here when customer cancels / payment fails.
     */
    public function cancel(Request $request, string $country, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return ApiResponse::error('Order not found.', [], 404);
        }

        // enhancement.md P-05 task 5: anyone who guesses an order number
        // must not be able to cancel it. Allow either a validly signed URL
        // (the one this order's own PaymentService::initiatePayment handed
        // the gateway as cancelUrl) OR proof, from the gateway itself, that
        // the transaction actually failed/was declined/cancelled.
        $latestTransaction = PaymentTransaction::where('order_id', $order->id)->latest()->first();
        $gatewayConfirmsFailure = $latestTransaction
            && in_array($latestTransaction->status->value, ['failed', 'cancelled'], true);

        if (! $request->hasValidSignature() && ! $gatewayConfirmsFailure) {
            return ApiResponse::error('Forbidden.', [], 403);
        }

        if ($order->payment_status === OrderPaymentStatus::Pending) {
            $order->update([
                'payment_status' => OrderPaymentStatus::Failed,
                'status'         => \App\Enums\OrderStatus::Cancelled,
                'cancelled_at'   => now(),
            ]);

            if ($latestTransaction) {
                $latestTransaction->update(['status' => 'failed']);
            }

            $this->rollbackService->rollback($order, 'Payment cancelled at gateway');
        }

        return ApiResponse::success([
            'order_number'   => $orderNumber,
            'payment_status' => OrderPaymentStatus::Failed,
            'status'         => \App\Enums\OrderStatus::Cancelled,
        ], 'Payment cancelled.');
    }
}
