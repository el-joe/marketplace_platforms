<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\GiftCardBatch;
use App\Models\GiftCardPurchase;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftCardPurchaseService
{
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

            $gatewayConfig = \App\Models\CountryPaymentGateway::where('id', $data['country_payment_gateway_id'])
                ->with('gateway')
                ->first();

            $gatewayCode = $gatewayConfig?->gateway?->code ?? $data['country_payment_gateway_id'];

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
                'status' => 'completed',
                'currency' => $batch->currency_code,
                'subtotal' => $totalAmount,
                'discount' => 0,
                'shipping' => 0,
                'tax' => 0,
                'cod_fee' => 0,
                'warranty_total' => 0,
                'total' => $totalAmount,
                'wallet_amount_used' => 0,
                'payment_method' => $gatewayCode,
                'payment_status' => 'pending',
                'placed_at' => now(),
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

            return [
                'order' => $order,
                'purchases' => $purchases,
                'cards' => $cards,
            ];
        });
    }

    /**
     * Called by the delivery job after payment has been confirmed for the purchase's order.
     */
    public function deliverCard(GiftCardPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase->delivery_status = 'sent';
            $purchase->delivered_at = now();
            $purchase->increment('delivery_attempts');
            $purchase->save();

            $purchase->giftCard()->update(['delivery_sent_at' => now()]);
        });

        // Dispatch: Mail::to($purchase->recipient_email)->send(new GiftCardDeliveryMail($purchase))
        // The plain PIN is not stored after batch generation. For purchased cards, either
        // re-generate/re-hash the PIN at purchase time and cache the plain value briefly
        // for delivery, or send code-only delivery and require PIN reset before redemption.
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
