<?php

namespace App\Services\Vendor;

use App\Models\Category;
use App\Models\CountryCategory;
use App\Models\Payout;
use App\Models\Vendor;
use App\Models\VendorBankAccount;
use App\Services\MarketingFeeReportService;
use App\Support\Money;
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
                'total_gmv' => (float) $vendor->total_sales,
                'total_commission_paid' => Money::number($totalCommissionPaid),
                'pending_payout' => (int) $pendingPayout,
                'last_payout_date' => $lastPayout?->processed_at?->toDateString(),
                'next_payout_date' => $this->resolveNextPayoutDate($vendor->payout_schedule),
                'payout_schedule' => $vendor->payout_schedule instanceof \BackedEnum ? $vendor->payout_schedule->value : $vendor->payout_schedule,
                'payout_on_hold' => (bool) $vendor->payout_hold_active,
                // P8: marketer-campaign charges as separate lines per currency (never blended).
                'marketing' => app(MarketingFeeReportService::class)->vendorTotals($vendor->id),
            ];
        });
    }

    private function resolveNextPayoutDate(string|\BackedEnum $schedule): string
    {
        // Vendor::payout_schedule is cast to the PayoutSchedule enum; the string overload crashed the summary.
        $schedule = $schedule instanceof \BackedEnum ? $schedule->value : $schedule;
        $today = Carbon::today();

        return match ($schedule) {
            'weekly' => $today->copy()->next(Carbon::MONDAY)->toDateString(),
            'biweekly' => $today->copy()->next(Carbon::MONDAY)->addWeeks(
                $today->weekOfYear % 2 === 0 ? 0 : 1
            )->toDateString(),
            default => $today->copy()->firstOfMonth()->addMonth()->toDateString(), // monthly
        };
    }

    /**
     * Commission rates the vendor pays, read only from the category edit page settings
     * (country override first, then the category's own FBP rate).
     *
     * @return list<array<string, mixed>>
     */
    public function resolveCommissionRates(Vendor $vendor): array
    {
        $overrides = CountryCategory::where('country_id', $vendor->country_id)->get()->keyBy('category_id');

        return Category::where('is_active', true)
            ->orderBy('name_en')
            ->get()
            ->map(function (Category $category) use ($overrides) {
                $rate = (float) ($overrides->get($category->id)?->commission_fbp_pct ?? $category->commission_fbp_pct ?? 0);

                return [
                    'category_id' => $category->id,
                    'category_name' => $category->name_en,
                    'rate_pct' => $rate,
                    'rate_type' => 'percentage',
                    'min_commission' => null,
                    'max_commission' => null,
                    'effective_from' => null,
                    'effective_until' => null,
                    'scope' => 'category',
                ];
            })
            ->filter(fn (array $row) => $row['rate_pct'] > 0)
            ->values()
            ->all();
    }

    public function createBankAccount(Vendor $vendor, array $data): VendorBankAccount
    {
        $account = new VendorBankAccount([
            'vendor_id' => $vendor->id,
            'account_holder_name' => $data['account_holder_name'],
            'bank_name' => $data['bank_name'],
            'iban' => $data['iban'] ?? null,
            'account_number_encrypted' => encrypt($data['account_number']),
            'currency' => $data['currency'],
            'branch' => $data['branch'] ?? null,
            'swift_code' => $data['swift_code'] ?? null,
            'is_primary' => false,
            'verification_status' => 'pending',
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
