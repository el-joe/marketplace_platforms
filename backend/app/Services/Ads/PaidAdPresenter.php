<?php

namespace App\Services\Ads;

use App\Models\PaidAdBooking;

class PaidAdPresenter
{
    public static function present(PaidAdBooking $b): array
    {
        $creative = $b->currentCreative;

        $desktop = $creative->imagePair('desktop');
        $mobile = $creative->imagePair('mobile');

        $advertiserName = $b->advertiserName();

        return [
            'image_url' => $desktop,
            'mobile_image_url' => $mobile,
            'title' => ['en' => $creative->title_en, 'ar' => $creative->title_ar],
            'subtitle' => ['en' => $creative->subtitle_en, 'ar' => $creative->subtitle_ar],
            'cta_label' => ['en' => $creative->cta_label_en, 'ar' => $creative->cta_label_ar],
            'link_url' => $creative->destination_url,
            'link_type' => $creative->destination_type,
            'link_reference_id' => $creative->destination_reference_id,
            'is_external' => in_array($creative->destination_type, ['external', 'campaign'], true),
            'ad' => [
                'id' => $b->id,
                'sig' => self::sign($b->id),
                'advertiser_label' => [
                    'en' => "Sponsored by {$advertiserName}",
                    'ar' => "إعلان من {$advertiserName}",
                ],
                'pricing_model' => self::pricingModel($b->pricing_model),
            ],
        ];
    }

    public static function verifySig(string $id, string $sig): bool
    {
        return hash_equals(self::sign($id), $sig);
    }

    private static function sign(string $id): string
    {
        return substr(hash_hmac('sha256', $id, config('app.key')), 0, 24);
    }

    private static function pricingModel(?string $pricingModel): string
    {
        return match (true) {
            $pricingModel === 'cpm' => 'cpm',
            $pricingModel === 'cpc' => 'cpc',
            default => 'fixed',
        };
    }
}
