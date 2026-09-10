<?php

namespace App\Services\Ads;

use App\Models\Country;
use App\Services\BannerService;

class PlacementAdService
{
    public function __construct(
        private readonly PaidAdResolver $resolver,
        private readonly BannerService $bannerService,
    ) {
    }

    public function resolve(
        string $code,
        Country $country,
        string $audience,
        ?string $sessionId,
        ?string $productId = null,
        ?string $categoryId = null,
    ): ?array {
        $paid = $this->resolvePaid($code, $country, $sessionId, $categoryId);

        if ($paid !== null) {
            return $paid;
        }

        $banner = $this->bannerService->getActivePlacement($code, $country->id, $audience, $productId);

        if (!$banner) {
            return null;
        }

        $desktopFile = $this->bannerService->getDesktopImage($banner);
        $mobileFile = $this->bannerService->getMobileImage($banner);

        return [
            'id' => $banner->id,
            'title_en' => $banner->title_en,
            'title_ar' => $banner->title_ar,
            'subtitle_en' => $banner->subtitle_en,
            'subtitle_ar' => $banner->subtitle_ar,
            'cta_label_en' => $banner->cta_label_en,
            'cta_label_ar' => $banner->cta_label_ar,
            'cta_url' => $banner->cta_url,
            'link_type' => $banner->link_type?->value,
            'link_reference_id' => $banner->link_reference_id,
            'desktop_image_url' => $desktopFile?->full_path,
            'mobile_image_url' => $mobileFile?->full_path,
            'desktop_image_url_ar' => $desktopFile?->full_path,
            'mobile_image_url_ar' => $mobileFile?->full_path,
            'is_external' => false,
            'is_paid' => false,
            'ad' => null,
        ];
    }

    private function resolvePaid(string $code, Country $country, ?string $sessionId, ?string $categoryId): ?array
    {
        $index = $this->resolver->activeIndex($country->id);
        $candidates = $index['placement'][$code] ?? [];

        if (empty($candidates)) {
            return null;
        }

        $scoped = array_filter(
            $candidates,
            fn (array $c) => $c['category_id'] === null || $c['category_id'] === $categoryId,
        );

        // Prefer category-scoped slots over unscoped ones.
        $categoryScoped = array_values(array_filter($scoped, fn (array $c) => $c['category_id'] !== null));
        $pool = !empty($categoryScoped) ? $categoryScoped : array_values($scoped);

        if (empty($pool)) {
            return null;
        }

        $picked = $this->resolver->pick($pool, $sessionId, $code);

        if (!$picked) {
            return null;
        }

        $ad = $picked['payload'];

        return [
            'id' => null,
            'title_en' => $ad['title']['en'],
            'title_ar' => $ad['title']['ar'],
            'subtitle_en' => $ad['subtitle']['en'],
            'subtitle_ar' => $ad['subtitle']['ar'],
            'cta_label_en' => $ad['cta_label']['en'],
            'cta_label_ar' => $ad['cta_label']['ar'],
            'cta_url' => $ad['link_url'],
            'link_type' => $ad['link_type'],
            'link_reference_id' => $ad['link_reference_id'],
            'desktop_image_url' => $ad['image_url']['en'],
            'mobile_image_url' => $ad['mobile_image_url']['en'],
            'desktop_image_url_ar' => $ad['image_url']['ar'],
            'mobile_image_url_ar' => $ad['mobile_image_url']['ar'],
            'is_external' => $ad['is_external'],
            'is_paid' => true,
            'ad' => $ad['ad'],
        ];
    }
}
