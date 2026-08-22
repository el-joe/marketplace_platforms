<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function validate(string $code, Customer $customer): Voucher
    {
        $voucher = Voucher::whereRaw('UPPER(code) = ?', [strtoupper($code)])->first();

        if (! $voucher) {
            throw ValidationException::withMessages(['code' => 'Invalid voucher code']);
        }

        if (! $voucher->is_active) {
            throw ValidationException::withMessages(['code' => 'This voucher is no longer active']);
        }

        if ($voucher->valid_from && now()->lt($voucher->valid_from)) {
            throw ValidationException::withMessages(['code' => 'This voucher is not yet valid']);
        }

        if ($voucher->valid_until && now()->gt($voucher->valid_until)) {
            throw ValidationException::withMessages(['code' => 'This voucher has expired']);
        }

        if ($voucher->country_id !== null) {
            $customerCountryId = $customer->addresses()
                ->where('is_default', true)
                ->value('country_id') ?? $customer->country_id;

            if ($customerCountryId !== $voucher->country_id) {
                throw ValidationException::withMessages(['code' => 'This voucher is not valid in your country']);
            }
        }

        if ($voucher->usage_limit_total !== null && $voucher->times_redeemed >= $voucher->usage_limit_total) {
            throw ValidationException::withMessages(['code' => 'This voucher has reached its redemption limit']);
        }

        $count = VoucherRedemption::where('voucher_id', $voucher->id)
            ->where('customer_id', $customer->id)
            ->count();

        if ($count >= $voucher->usage_limit_per_customer) {
            throw ValidationException::withMessages(['code' => 'You have already redeemed this voucher']);
        }

        if ($voucher->customer_eligibility === 'new_customers' && $customer->orders()->exists()) {
            throw ValidationException::withMessages(['code' => 'This voucher is for new customers only']);
        }

        if ($voucher->customer_eligibility === 'specific_users'
            && $voucher->eligible_customer_ids !== null
            && ! in_array($customer->id, $voucher->eligible_customer_ids)
        ) {
            throw ValidationException::withMessages(['code' => 'You are not eligible for this voucher']);
        }

        return $voucher;
    }

    public function redeem(Voucher $voucher, Customer $customer): array
    {
        return DB::transaction(function () use ($voucher, $customer) {
            $voucher = Voucher::where('id', $voucher->id)->lockForUpdate()->firstOrFail();

            if ($voucher->usage_limit_total && $voucher->times_redeemed >= $voucher->usage_limit_total) {
                throw ValidationException::withMessages(['code' => 'This voucher has reached its redemption limit.']);
            }

            $alreadyUsed = VoucherRedemption::where('voucher_id', $voucher->id)
                ->where('customer_id', $customer->id)
                ->count();

            if ($alreadyUsed >= $voucher->usage_limit_per_customer) {
                throw ValidationException::withMessages(['code' => 'You have already redeemed this voucher.']);
            }

            $wallet = CustomerWallet::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id, 'currency_code' => $voucher->currency_code],
                ['balance' => 0]
            );

            $newBalance = $wallet->balance + $voucher->amount;
            $wallet->balance = $newBalance;
            $wallet->save();

            VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'customer_id' => $customer->id,
                'customer_wallet_id' => $wallet->id,
                'amount' => $voucher->amount,
                'currency_code' => $voucher->currency_code,
                'wallet_balance_after' => $newBalance,
                'redeemed_at' => now(),
            ]);

            DB::table('wallet_transactions')->insert([
                'id' => Str::uuid(),
                'wallet_id' => null,
                'customer_id' => $customer->id,
                'type' => 'voucher_redemption',
                'direction' => 'credit',
                'amount' => $voucher->amount,
                'balance_after' => $newBalance,
                'currency_code' => $voucher->currency_code,
                'source_type' => 'voucher',
                'source_id' => $voucher->id,
                'description' => 'Voucher redeemed: '.$voucher->code,
                'created_at' => now(),
            ]);

            DB::table('vouchers')->where('id', $voucher->id)->increment('times_redeemed');

            return [
                'amount_credited' => $voucher->amount,
                'currency_code' => $voucher->currency_code,
                'new_balance' => $newBalance,
                'title' => $voucher->title_en ?? $voucher->name,
            ];
        });
    }

    public function bulkGenerate(array $data, Admin $admin): array
    {
        $codes = [];

        DB::transaction(function () use ($data, $admin, &$codes) {
            $rows = [];
            $prefix = strtoupper($data['code_prefix'] ?? '');

            for ($i = 0; $i < $data['quantity']; $i++) {
                do {
                    $suffix = strtoupper(Str::random(8));
                    $code = $prefix.$suffix;
                } while (in_array($code, $codes) || Voucher::where('code', $code)->exists());

                $codes[] = $code;
                $rows[] = [
                    'id' => Str::uuid(),
                    'code' => $code,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'title_ar' => $data['title_ar'] ?? null,
                    'title_en' => $data['title_en'] ?? null,
                    'amount' => $data['amount'],
                    'currency_code' => $data['currency_code'],
                    'country_id' => $data['country_id'] ?? null,
                    'customer_eligibility' => $data['customer_eligibility'] ?? 'all',
                    'eligible_customer_ids' => isset($data['eligible_customer_ids'])
                        ? json_encode($data['eligible_customer_ids']) : null,
                    'usage_limit_total' => $data['usage_limit_total'] ?? null,
                    'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? 1,
                    'times_redeemed' => 0,
                    'valid_from' => $data['valid_from'],
                    'valid_until' => $data['valid_until'],
                    'is_active' => $data['is_active'] ?? true,
                    'created_by_admin_id' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('vouchers')->insert($chunk);
            }
        });

        return ['count' => count($codes), 'sample_codes' => array_slice($codes, 0, 5)];
    }
}
