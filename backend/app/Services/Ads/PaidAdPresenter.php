<?php

namespace App\Services\Ads;

use App\Models\PaidAdBooking;
use App\Models\VendorListing;
use App\Services\Media\ListingImageResolver;

class PaidAdPresenter
{
    public static function present(PaidAdBooking $b, ?VendorListing $listing = null): array
    {
        $creative = $b->currentCreative;

        $title = ['en' => $creative->title_en, 'ar' => $creative->title_ar];
        $linkReferenceId = $creative->destination_reference_id;
        $productId = null;

        if ($b->slot?->derivesCreativeFromProduct()) {
            $listing ??= $creative->destination_reference_id
                ? VendorListing::with('productVariant.product')->find($creative->destination_reference_id)
                : null;
            $variant = $listing?->productVariant;
            $url = $variant ? app(ListingImageResolver::class)->primary($variant->id) : null;
            $desktop = $mobile = ['en' => $url, 'ar' => $url];
            $product = $variant?->product;
            $productId = $product?->id;
            $title = ['en' => $product?->name_en, 'ar' => $product?->name_ar ?? $product?->name_en];
            $linkReferenceId = $listing?->id;
        } else {
            $desktop = $creative->imagePair('desktop');
            $mobile = $creative->imagePair('mobile');
        }

        $advertiserName = $b->advertiserName();

        return [
            'image_url' => $desktop,
            'mobile_image_url' => $mobile,
            'title' => $title,
            'subtitle' => ['en' => $creative->subtitle_en, 'ar' => $creative->subtitle_ar],
            'cta_label' => ['en' => $creative->cta_label_en, 'ar' => $creative->cta_label_ar],
            'link_url' => $creative->destination_url,
            'link_type' => $creative->destination_type,
            'link_reference_id' => $linkReferenceId,
            'product_id' => $productId,
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
