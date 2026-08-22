<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ReferralRewardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Customer $referrer,
        public readonly Customer $referred,
    ) {}

    public function handle(): void
    {
        if (! (bool) Setting::get('loyalty_enabled', true)) {
            return;
        }

        $referrerBonus = (float) Setting::get('loyalty_referral_bonus_points', 50);
        $refereeBonus  = (float) Setting::get('loyalty_new_customer_bonus_points', 50);

        DB::transaction(function () use ($referrerBonus, $refereeBonus): void {
            // Lock both rows to prevent concurrent point mutations.
            $referrer = Customer::where('id', $this->referrer->id)->lockForUpdate()->first();
            $referred = Customer::where('id', $this->referred->id)->lockForUpdate()->first();

            if ($referrer && $referrerBonus > 0) {
                $referrer->increment('loyalty_points', $referrerBonus);
            }

            if ($referred && $refereeBonus > 0) {
                $referred->increment('loyalty_points', $refereeBonus);
            }
        });
    }
}
