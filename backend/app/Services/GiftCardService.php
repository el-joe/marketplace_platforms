<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\GiftCard;
use App\Models\GiftCardBatch;
use App\Models\GiftCardTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftCardService
{
    /**
     * Admin generates a batch of gift cards. Returns the batch and the plain
     * (unhashed) PINs, which are only ever available at generation time.
     *
     * @param  array{name: string, description?: string|null, amount: int, currency_code: string, quantity: int, expires_at?: string|null, activate_immediately?: bool}  $data
     * @return array{batch: GiftCardBatch, plain_pins: array<int, string>}
     */
    public function generateBatch(array $data, Admin $admin): array
    {
        $batch = null;
        $plainPins = [];

        DB::transaction(function () use ($data, $admin, &$batch, &$plainPins) {
            $batch = GiftCardBatch::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'],
                'quantity' => $data['quantity'],
                'expires_at' => $data['expires_at'] ?? null,
                'created_by_admin_id' => $admin->id,
            ]);

            $plainPins = [];
            $cards = [];

            for ($i = 0; $i < $data['quantity']; $i++) {
                $plainPin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $plainPins[] = $plainPin;

                $code = GiftCard::generateUniqueCode();

                $cards[] = [
                    'id' => (string) Str::uuid(),
                    'gift_card_batch_id' => $batch->id,
                    'source' => 'batch',
                    'code' => $code,
                    'pin_hash' => Hash::make($plainPin),
                    'amount' => $data['amount'],
                    'remaining_balance' => $data['amount'],
                    'currency_code' => $data['currency_code'],
                    'status' => ($data['activate_immediately'] ?? false) ? 'active' : 'inactive',
                    'expires_at' => $data['expires_at'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($cards, 500) as $chunk) {
                GiftCard::insert($chunk);
            }

            $transactions = array_map(fn (array $card) => [
                'id' => (string) Str::uuid(),
                'gift_card_id' => $card['id'],
                'order_id' => null,
                'amount' => $data['amount'],
                'balance_after' => $data['amount'],
                'type' => 'issuance',
                'performed_by_admin_id' => $admin->id,
                'notes' => 'Batch issuance: '.$batch->name,
                'created_at' => now(),
            ], $cards);

            foreach (array_chunk($transactions, 500) as $chunk) {
                DB::table('gift_card_transactions')->insert($chunk);
            }
        });

        return ['batch' => $batch, 'plain_pins' => $plainPins];
    }

    /**
     * Redeem a code+PIN gift card into the customer's wallet. Credits the full
     * remaining balance in one atomic operation.
     *
     * @return array{amount_credited: int, currency_code: string, new_balance: int, card_code_masked: string}
     */
    public function redeemToWallet(string $code, string $pin, Customer $customer): array
    {
        return DB::transaction(function () use ($code, $pin, $customer) {
            $card = GiftCard::whereRaw('UPPER(code) = ?', [strtoupper($code)])
                ->lockForUpdate()
                ->first();

            if (! $card || ! Hash::check($pin, $card->pin_hash)) {
                throw ValidationException::withMessages(['code' => 'Invalid code or PIN.']);
            }
            if ($card->status !== 'active') {
                throw ValidationException::withMessages(['code' => 'This gift card is not active.']);
            }
            if ($card->expires_at && $card->expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'This gift card has expired.']);
            }
            if ($card->remaining_balance <= 0) {
                throw ValidationException::withMessages(['code' => 'No balance remaining on this gift card.']);
            }
            if ($card->issued_to_customer_id && $card->issued_to_customer_id !== $customer->id) {
                throw ValidationException::withMessages(['code' => 'Invalid code or PIN.']);
            }

            $amountToCredit = $card->remaining_balance;

            $wallet = CustomerWallet::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id, 'currency_code' => $card->currency_code],
                ['balance' => 0]
            );

            $newBalance = $wallet->balance + $amountToCredit;
            $wallet->balance = $newBalance;
            $wallet->save();

            $card->remaining_balance = 0;
            $card->status = 'redeemed';
            $card->redeemed_by_customer_id = $customer->id;
            $card->redeemed_at = now();
            $card->save();

            GiftCardTransaction::create([
                'gift_card_id' => $card->id,
                'amount' => $amountToCredit,
                'balance_after' => 0,
                'type' => 'redemption',
                'performed_by_customer_id' => $customer->id,
                'notes' => 'Redeemed to wallet',
            ]);

            DB::table('wallet_transactions')->insert([
                'id' => (string) Str::uuid(),
                'wallet_id' => null,
                'customer_id' => $customer->id,
                'type' => 'gift_card_redemption',
                'direction' => 'credit',
                'amount' => $amountToCredit,
                'balance_after' => $newBalance,
                'currency_code' => $card->currency_code,
                'source_type' => 'gift_card',
                'source_id' => $card->id,
                'description' => 'Gift card redeemed: '.$card->masked_code,
                'created_at' => now(),
            ]);

            return [
                'amount_credited' => $amountToCredit,
                'currency_code' => $card->currency_code,
                'new_balance' => $newBalance,
                'card_code_masked' => $card->masked_code,
            ];
        });
    }

    /**
     * Admin refunds an amount back onto a gift card (e.g. when refunding an
     * order that was paid via a wallet balance traced back to this card).
     */
    public function refundToCard(GiftCard $card, int $amount, Admin $admin, string $notes = ''): void
    {
        DB::transaction(function () use ($card, $amount, $admin, $notes) {
            $card->increment('remaining_balance', $amount);

            if ($card->status === 'redeemed') {
                $card->status = 'active';
                $card->redeemed_by_customer_id = null;
                $card->redeemed_at = null;
                $card->save();
            }

            GiftCardTransaction::create([
                'gift_card_id' => $card->id,
                'amount' => $amount,
                'balance_after' => $card->fresh()->remaining_balance,
                'type' => 'refund',
                'performed_by_admin_id' => $admin->id,
                'notes' => $notes ?: 'Admin refund',
            ]);
        });
    }

    /**
     * Admin manually adjusts a gift card's balance. Positive delta adds
     * credit, negative deducts.
     */
    public function adminAdjust(GiftCard $card, int $delta, string $notes, Admin $admin): void
    {
        DB::transaction(function () use ($card, $delta, $notes, $admin) {
            $newBalance = $card->remaining_balance + $delta;

            if ($newBalance < 0) {
                throw new \InvalidArgumentException('Adjustment would result in negative balance.');
            }

            $card->remaining_balance = $newBalance;
            $card->save();

            GiftCardTransaction::create([
                'gift_card_id' => $card->id,
                'amount' => $delta,
                'balance_after' => $newBalance,
                'type' => 'admin_adjustment',
                'performed_by_admin_id' => $admin->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Marks active gift cards past their expiry date as expired. Called daily
     * by the scheduler.
     */
    public function expireDueCards(): int
    {
        $cards = GiftCard::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        DB::transaction(function () use ($cards) {
            foreach ($cards as $card) {
                $card->status = 'expired';
                $card->save();

                GiftCardTransaction::create([
                    'gift_card_id' => $card->id,
                    'amount' => -$card->remaining_balance,
                    'balance_after' => 0,
                    'type' => 'expiry',
                    'notes' => 'Expired by scheduler',
                ]);
            }
        });

        return $cards->count();
    }
}
