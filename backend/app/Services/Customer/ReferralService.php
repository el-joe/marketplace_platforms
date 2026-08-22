<?php

namespace App\Services\Customer;

use App\Jobs\ReferralRewardJob;
use App\Models\Customer;
use Illuminate\Support\Str;

class ReferralService
{
    private const CODE_LENGTH = 8;
    private const MAX_RETRIES = 10;

    public function generateUniqueCode(): string
    {
        $attempts = 0;

        do {
            $code = strtoupper(Str::random(self::CODE_LENGTH));
            $exists = Customer::where('referral_code', $code)->exists();
            $attempts++;
        } while ($exists && $attempts < self::MAX_RETRIES);

        if ($exists) {
            // Extremely unlikely — fallback to UUID-derived code
            $code = strtoupper(substr(str_replace('-', '', Str::uuid()->toString()), 0, self::CODE_LENGTH));
        }

        return $code;
    }

    public function applyReferral(Customer $referred, string $code): void
    {
        $referrer = Customer::where('referral_code', $code)
            ->where('status', 'active')
            ->first();

        if (!$referrer || $referrer->id === $referred->id) {
            return;
        }

        $referred->update(['referred_by' => $referrer->id]);

        ReferralRewardJob::dispatch($referrer, $referred);
    }
}
