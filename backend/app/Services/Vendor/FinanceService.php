<?php

namespace App\Services\Vendor;

use App\Models\Commission;
use App\Models\Payout;
use App\Models\Vendor;
use App\Models\VendorBankAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function getSummary(Vendor $vendor): array
    {
        return Cache::remember("vendor_finance_summary_{$vendor->id}", now()->addMinutes(10), function () use ($vendor) {
            $completed = Payout::where('vendor_id', $vendor->id)
                ->where('status', 'completed');

            $totalCommissionPaid = (clone $completed)->sum('commission');

            $lastPayout = (clone $completed)
                ->whereNotNull('processed_at')
                ->latest('processed_at')
                ->first();

            $pendingPayout = Payout::where('vendor_id', $vendor->id)
                ->whereIn('status', ['pending', 'approved', 'processing'])
                ->sum('net_amount');

            return [
                'total_gmv'                  => (float) $vendor->total_sales,
                'total_commission_paid' => (int) $totalCommissionPaid,
                'pending_payout'        => (int) $pendingPayout,
                'last_payout_date'            => $lastPayout?->processed_at?->toDateString(),
                'next_payout_date'            => $this->resolveNextPayoutDate($vendor->payout_schedule),
                'payout_schedule'             => $vendor->payout_schedule,
                'payout_on_hold'              => (bool) $vendor->payout_hold_active,
            ];
        });
    }

    private function resolveNextPayoutDate(string $schedule): string
    {
        $today = Carbon::today();

        return match ($schedule) {
            'weekly'   => $today->copy()->next(Carbon::MONDAY)->toDateString(),
            'biweekly' => $today->copy()->next(Carbon::MONDAY)->addWeeks(
                $today->weekOfYear % 2 === 0 ? 0 : 1
            )->toDateString(),
            default    => $today->copy()->firstOfMonth()->addMonth()->toDateString(), // monthly
        };
    }

    public function resolveCommissionRates(Vendor $vendor): array
    {
        $today = now()->toDateString();

        // Fetch all active commission rules that could apply to this vendor,
        // ordered by descending priority so we can pick the highest-priority
        // match per category.
        $rules = Commission::where(function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id)
                  ->orWhereNull('vendor_id');
            })
            ->where('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $today))
            ->orderByDesc('priority')
            ->with('category')
            ->get();

        // Group by category (null = platform default / seller-wide override).
        $resolved = [];
        $seen     = [];

        foreach ($rules as $rule) {
            $key = ($rule->vendor_id ? 'v' : '_') . '|' . ($rule->category_id ?? '*');

            // Priority chain: seller+category > seller-only > category-only > platform default.
            // Since rules are ordered by priority desc, first seen per category wins.
            $catKey = $rule->category_id ?? '__default__';

            if (!isset($seen[$catKey])) {
                $seen[$catKey] = true;
                $resolved[]    = [
                    'category_id'      => $rule->category_id,
                    'category_name'    => $rule->category?->name_en ?? 'All Categories',
                    'rate_pct'         => (float) $rule->rate_pct,
                    'rate_type'        => $rule->rate_type,
                    'min_commission'   => $rule->min_commission,
                    'max_commission'   => $rule->max_commission,
                    'effective_from'   => $rule->effective_from?->toDateString(),
                    'effective_until'  => $rule->effective_until?->toDateString(),
                    'scope'            => $rule->vendor_id ? ($rule->category_id ? 'vendor+category' : 'vendor') : ($rule->category_id ? 'category' : 'platform_default'),
                ];
            }
        }

        return $resolved;
    }

    public function createBankAccount(Vendor $vendor, array $data): VendorBankAccount
    {
        $account = new VendorBankAccount([
            'vendor_id'               => $vendor->id,
            'account_holder_name'     => $data['account_holder_name'],
            'bank_name'               => $data['bank_name'],
            'iban'                    => $data['iban'] ?? null,
            'account_number_encrypted' => encrypt($data['account_number']),
            'currency'                => $data['currency'],
            'branch'                  => $data['branch'] ?? null,
            'swift_code'              => $data['swift_code'] ?? null,
            'is_primary'              => false,
            'verification_status'     => 'pending',
        ]);

        $account->save();

        // If this is the vendor's first account, make it primary automatically.
        if (VendorBankAccount::where('vendor_id', $vendor->id)->count() === 1) {
            $account->update(['is_primary' => true]);
        }

        return $account;
    }

    public function setPrimaryAccount(Vendor $vendor, VendorBankAccount $account): void
    {
        DB::transaction(function () use ($vendor, $account) {
            VendorBankAccount::where('vendor_id', $vendor->id)->update(['is_primary' => false]);
            $account->update(['is_primary' => true]);
        });
    }

    public function deleteBankAccount(Vendor $vendor, VendorBankAccount $account): void
    {
        $total = VendorBankAccount::where('vendor_id', $vendor->id)->count();

        if ($total === 1) {
            // Check if any payout is scheduled against this account.
            $scheduled = Payout::where('vendor_id', $vendor->id)
                ->where('bank_account_id', $account->id)
                ->whereIn('status', ['pending', 'approved', 'processing'])
                ->exists();

            if ($scheduled) {
                abort(422, 'Cannot delete the only bank account while a payout is pending against it. Resolve the payout or add another account first.');
            }
        }

        $account->delete();
    }
}
