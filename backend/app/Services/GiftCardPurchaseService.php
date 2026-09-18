<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Mail\GiftCardDeliveryMail;
use App\Models\Customer;
use App\Models\CountryPaymentGateway;
use App\Models\GiftCard;
use App\Models\GiftCardBatch;
use App\Models\GiftCardPurchase;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaymentMethodMapper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftCardPurchaseService
{
    public function __construct(
        private readonly PaymentService $paymentService = new PaymentService(),
    ) {
    }

    /**
     * Purchasable batches that currently have available (active, unassigned) cards.
     */
    public function getAvailableBatches(string $currencyCode): Collection
    {
        return GiftCardBatch::purchasable()
            ->where('currency_code', $currencyCode)
            ->withCount([
                'giftCards as available_count' => fn ($q) => $q->where('status', 'active')
                    ->whereNull('purchased_by_customer_id'),
            ])
            ->having('available_count', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('amount')
            ->get();
    }

    /**
     * @param  array{gift_card_batch_id: string, quantity?: int, recipient_email?: string|null, recipient_name?: string|null, gift_message?: string|null, country_payment_gateway_id: string}  $data
     * @return array{order: Order, purchases: array<int, GiftCardPurchase>, cards: Collection}
     */
    public function purchase(array $data, Customer $buyer): array
    {
        return DB::transaction(function () use ($data, $buyer) {
            $batch = GiftCardBatch::where('id', $data['gift_card_batch_id'])
                ->where('is_purchasable', true)
                ->lockForUpdate()
                ->firstOrFail();

            $gatewayConfig = CountryPaymentGateway::where('id', $data['country_payment_gateway_id'])
                ->with('gateway')
                ->first();

            if (! $gatewayConfig || ! $gatewayConfig->gateway) {
                throw ValidationException::withMessages([
                    'country_payment_gateway_id' => 'Selected payment method is not available.',
                ]);
            }

            $gatewayCode = $gatewayConfig->gateway->code;
            $gatewayType = $gatewayConfig->gateway->type;
            $isWallet = $gatewayCode === 'wallet';
            $isCod = $gatewayCode === 'cod';

            if ($isCod) {
                // Gift cards are digital-only; PurchaseGiftCardRequest already
                // rejects COD, but guard here too since this service could be
                // called from elsewhere.
                throw ValidationException::withMessages([
                    'country_payment_gateway_id' => 'Cash on Delivery is not available for gift card purchases.',
                ]);
            }

            $qty = $data['quantity'] ?? 1;
            if ($qty < $batch->min_quantity || $qty > $batch->max_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Quantity must be between {$batch->min_quantity} and {$batch->max_quantity}.",
                ]);
            }

            $cards = GiftCard::where('gift_card_batch_id', $batch->id)
                ->where('status', 'active')
                ->whereNull('purchased_by_customer_id')
                ->lockForUpdate()
                ->take($qty)
                ->get();

            if ($cards->count() < $qty) {
                throw ValidationException::withMessages([
                    'gift_card_batch_id' => 'Not enough gift cards available. Please try a different denomination.',
                ]);
            }

            $totalAmount = $batch->amount * $qty;

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $buyer->id,
                'country_id' => $buyer->country_id,
                // Payment is not actually captured/verified here (see purchase() note
                // below) — the order must not be marked as a finished/completed order
                // until payment is confirmed. 'placed' matches the initial status used
                // by the regular checkout flow before payment capture.
                'status' => OrderStatus::Placed->value,
                'currency' => $batch->currency_code,
                'subtotal' => $totalAmount,
                'discount' => 0,
                'shipping' => 0,
                'tax' => 0,
                'cod_fee' => 0,
                'warranty_total' => 0,
                'total' => $totalAmount,
                'wallet_amount_used' => 0,
                'payment_method' => PaymentMethodMapper::toOrderPaymentMethod($gatewayCode, $gatewayType),
                'payment_gateway_code' => $gatewayCode,
                // Set to 'captured' below once the gateway is actually invoked
                // (wallet debit or online-gateway success). Offline gateways
                // (e.g. bank transfer) stay 'pending' until an admin approves.
                'payment_status' => OrderPaymentStatus::Pending->value,
                'placed_at' => now(),
                'shipping_address_snapshot' => [],
                'ip_address' => request()->ip(),
            ]);

            $isGift = ! empty($data['recipient_email']) && $data['recipient_email'] !== $buyer->email;

            $purchases = [];

            foreach ($cards as $card) {
                $recipientEmail = $isGift ? $data['recipient_email'] : $buyer->email;
                $recipientName = $isGift ? ($data['recipient_name'] ?? null) : $buyer->name;

                $card->purchased_by_customer_id = $buyer->id;
                $card->recipient_email = $recipientEmail;
                $card->recipient_name = $recipientName;
                $card->purchase_order_id = $order->id;
                $card->save();

                $purchases[] = GiftCardPurchase::create([
                    'gift_card_id' => $card->id,
                    'gift_card_batch_id' => $batch->id,
                    'order_id' => $order->id,
                    'buyer_customer_id' => $buyer->id,
                    'amount_paid' => $batch->amount,
                    'currency_code' => $batch->currency_code,
                    'is_gift' => $isGift,
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => $recipientName,
                    'gift_message' => $data['gift_message'] ?? null,
                    'delivery_status' => 'pending',
                ]);
            }

            // ── Actually invoke the payment gateway ──────────────────────────
            // Previously this order was created and left 'pending' forever —
            // no gateway was ever charged, yet the controller dispatched the
            // gift-card delivery job unconditionally. Now: wallet is debited
            // synchronously (instant capture); any other gateway (redirect,
            // e.g. thawani/paytabs, or offline, e.g. bank_transfer) goes
            // through the same PaymentService::initiatePayment() checkout
            // uses. Offline gateways stay 'pending' until an admin approves
            // via Admin\TransactionController::confirmBankTransfer(); redirect
            // gateways stay 'pending' until the webhook/callback confirms.
            if ($isWallet) {
                $wallet = (new \App\Services\WalletService())->getOrCreateWallet(
                    \App\Enums\WalletOwnerType::Customer->value,
                    $buyer->id,
                    $order->currency,
                );

                try {
                    (new \App\Services\WalletService())->debit(
                        $wallet,
                        (int) $totalAmount,
                        'gift_card_purchase',
                        $order->id,
                        "Gift card purchase {$order->order_number}",
                    );
                } catch (\App\Exceptions\InsufficientBalanceException $e) {
                    throw ValidationException::withMessages([
                        'country_payment_gateway_id' => 'Insufficient wallet balance for this purchase.',
                    ]);
                }

                $order->update(['payment_status' => OrderPaymentStatus::Captured->value]);

                PaymentTransaction::create([
                    'id' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'customer_id' => $buyer->id,
                    'type' => 'sale',
                    'gateway' => 'wallet',
                    'gateway_transaction_id' => 'WALLET-'.$order->order_number,
                    'idempotency_key' => (string) Str::uuid(),
                    'amount' => $totalAmount,
                    'currency' => $order->currency,
                    'status' => 'succeeded',
                    'processed_at' => now(),
                ]);
            } else {
                try {
                    $paymentResult = $this->paymentService->initiatePayment($order, $gatewayConfig, (string) Str::uuid(), (int) $totalAmount);

                    if (! $paymentResult->success) {
                        Log::error('Gift card payment gateway declined purchase', [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'gateway_code' => $gatewayCode,
                            'error' => $paymentResult->errorMessage ?? null,
                        ]);
                        $order->update(['payment_status' => OrderPaymentStatus::Failed->value, 'status' => OrderStatus::Cancelled->value]);

                        throw ValidationException::withMessages([
                            'country_payment_gateway_id' => $paymentResult->errorMessage ?? 'Payment could not be initiated.',
                        ]);
                    }
                } catch (ValidationException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    Log::error('Gift card payment initiation threw an exception', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'gateway_code' => $gatewayCode,
                        'exception' => $e->getMessage(),
                    ]);
                    $order->update(['payment_status' => OrderPaymentStatus::Failed->value, 'status' => OrderStatus::Cancelled->value]);

                    throw ValidationException::withMessages([
                        'country_payment_gateway_id' => 'Payment could not be initiated.',
                    ]);
                }
            }

            return [
                'order' => $order->fresh(),
                'purchases' => $purchases,
                'cards' => $cards,
            ];
        });
    }

    /**
     * Dispatches the delivery job for any not-yet-delivered gift card
     * purchases on an order, but only once the order's payment has actually
     * been captured. Called right after purchase() when payment captured
     * synchronously (wallet), and from the offline-payment admin-approval
     * flow (Admin\TransactionController::confirmBankTransfer) /
     * online-gateway webhook capture once payment_status flips to
     * 'captured' for an order that turns out to be a gift-card order.
     */
    public function dispatchPendingDeliveries(Order $order): void
    {
        if ($order->payment_status?->value !== OrderPaymentStatus::Captured->value) {
            return;
        }

        $purchases = GiftCardPurchase::where('order_id', $order->id)
            ->where('delivery_status', 'pending')
            ->get();

        foreach ($purchases as $purchase) {
            \App\Jobs\SendGiftCardDeliveryJob::dispatch($purchase->id);
        }
    }

    /**
     * Delivers (or re-delivers, on resend) a purchased card to its recipient.
     *
     * The original batch PIN is hashed-only and never recoverable (see
     * GiftCardService::generateBatch), so every delivery — including resends —
     * mints a brand-new plain PIN, hashes it into the card, and emails the
     * plaintext immediately. Nothing plaintext is ever persisted.
     */
    public function deliverCard(GiftCardPurchase $purchase): void
    {
        $plainPin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($purchase, $plainPin) {
            $card = GiftCard::where('id', $purchase->gift_card_id)->lockForUpdate()->first();

            if ($card) {
                $card->pin_hash = Hash::make($plainPin);
                $card->delivery_sent_at = now();
                $card->save();
            }

            $purchase->delivery_status = 'sent';
            $purchase->delivered_at = now();
            $purchase->increment('delivery_attempts');
            $purchase->save();
        });

        $purchase->refresh()->load(['giftCard', 'batch', 'buyer']);

        Mail::to($purchase->recipient_email)->send(new GiftCardDeliveryMail($purchase, $plainPin));
    }

    public function getPurchaseHistory(Customer $customer): LengthAwarePaginator
    {
        return GiftCardPurchase::where('buyer_customer_id', $customer->id)
            ->with(['giftCard', 'batch'])
            ->latest()
            ->paginate(15);
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'NOON-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
