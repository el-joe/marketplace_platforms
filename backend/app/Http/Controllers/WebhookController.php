<?php

namespace App\Http\Controllers;

use App\Models\CountryPaymentGateway;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
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
            report(new \Exception("Invalid webhook signature for {$gatewayCode}"));
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if ($result->orderReference) {
            $order = Order::where('order_number', $result->orderReference)->first();

            if ($order) {
                $transaction = PaymentTransaction::where('order_id', $order->id)
                    ->where('gateway', $gatewayCode)
                    ->latest()
                    ->first();

                if ($transaction) {
                    $transaction->update([
                        'status'       => $result->resultingStatus,
                        'raw_response' => $result->parsedPayload,
                        'processed_at' => now(),
                    ]);

                    if ($result->resultingStatus === 'succeeded') {
                        $order->update([
                            'payment_status' => 'captured',
                            'status'         => 'confirmed',
                        ]);
                    }
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
