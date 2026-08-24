<?php

namespace App\Http\Resources\Customer;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'is_tourist' => (bool) $this->is_tourist,
            'status' => $this->status?->value,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'profile_completion' => $this->profileCompletion(),
            'total_orders' => $this->total_orders,
            'total_spent' => (float) $this->total_spent,
            'loyalty_points' => (float) $this->loyalty_points,
            'loyalty_tier'   => $this->resolveLoyaltyTier(),
            'referral_code' => $this->referral_code,
            'referral_link' => $this->referral_code
                ? rtrim(config('app.url'), '/') . '/r/' . $this->referral_code
                : null,
            'qr_code_url'   => $this->qr_code_path
                ? asset('storage/' . $this->qr_code_path)
                : null,
            'email_verified' => $this->email_verified_at !== null,
            'phone_verified' => $this->phone_verified_at !== null,
            'member_since' => $this->created_at->toDateString(),
            'locale' => $this->locale,
            'marketing_preferences' => [
                'email' => $this->marketing_email_enabled,
                'sms' => $this->marketing_sms_enabled,
                'whatsapp' => $this->marketing_whatsapp_enabled,
            ],
        ];
    }

    private function profileCompletion(): array
    {
        $fields = [
            'name' => filled($this->name),
            'email' => filled($this->email),
            'phone' => filled($this->phone),
            'date_of_birth' => filled($this->date_of_birth),
            'gender' => filled($this->gender),
            'nationality' => filled($this->nationality),
            'email_verified' => $this->email_verified_at !== null,
            'phone_verified' => $this->phone_verified_at !== null,
        ];

        $completed = count(array_filter($fields));
        $total = count($fields);

        return [
            'percentage' => (int) round(($completed / $total) * 100),
            'completed' => $completed,
            'total' => $total,
            'missing' => array_keys(array_filter($fields, fn ($v) => !$v)),
        ];
    }

    private function resolveLoyaltyTier(): string
    {
        $points   = (float) $this->loyalty_points;
        $platinum = (int) Setting::get('loyalty_tier_platinum_points', 5000);
        $gold     = (int) Setting::get('loyalty_tier_gold_points', 2000);
        $silver   = (int) Setting::get('loyalty_tier_silver_points', 500);

        return match (true) {
            $points >= $platinum => 'platinum',
            $points >= $gold     => 'gold',
            $points >= $silver   => 'silver',
            default              => 'standard',
        };
    }
}
