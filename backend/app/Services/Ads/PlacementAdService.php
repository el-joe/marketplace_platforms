<?php

namespace App\Services\Ads;

use App\Models\Banner;
use App\Models\Country;
use App\Services\BannerService;

class PlacementAdService
{
    public function __construct(
        private readonly PaidAdResolver $resolver,
        private readonly BannerService $bannerService,
    ) {}

    public function resolve(
        string $code,
        Country $country,
        string $audience,
        ?string $sessionId,
        ?string $productId = null,
        ?string $categoryId = null,
    ): ?array {
        $paid = $this->resolvePaid($code, $country, $sessionId, $categoryId, $productId);

        if ($paid !== null) {
            return $paid;
        }

        ['desktop' => $desktop, 'mobile' => $mobile] = $this->bannerService
            ->getActivePlacementByDevice($code, $country->id, $audience, $productId);

        if (! $desktop && ! $mobile) {
            return null;
        }

        // Different banners won each device (e.g. one desktop-only + one mobile-only banner):
        // the top level carries the desktop banner, `mobile_variant` the mobile one.
        if ($desktop && $mobile && $desktop->isNot($mobile)) {
            return [
                ...$this->bannerPayload($desktop, ['desktop']),
                'mobile_variant' => $this->bannerPayload($mobile, ['mobile']),
            ];
        }

        return $this->bannerPayload($desktop ?? $mobile);
    }

    /**
     * @param  list<'desktop'|'mobile'>|null  $devices
     * @return array<string, mixed>
     */
    private function bannerPayload(Banner $banner, ?array $devices = null): array
    {
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
            'device_target' => $banner->device_target?->value,
            ...$this->bannerService->resolvedImageUrls($banner, $devices),
            'is_external' => false,
            'is_paid' => false,
            'ad' => null,
            'mobile_variant' => null,
        ];
    }

    private function resolvePaid(string $code, Country $country, ?string $sessionId, ?string $categoryId, ?string $productId = null): ?array
    {
        $index = $this->resolver->activeIndex($country->id);
        $candidates = $index['placement'][$code] ?? [];

        if (empty($candidates)) {
            return null;
        }

        $scoped = array_filter(
            $candidates,
            fn (array $c) => ($c['category_id'] === null || $c['category_id'] === $categoryId)
                && ! ($productId !== null && ($c['payload']['product_id'] ?? null) === $productId),
        );

        // Prefer category-scoped slots over unscoped ones.
        $categoryScoped = array_values(array_filter($scoped, fn (array $c) => $c['category_id'] !== null));
        $pool = ! empty($categoryScoped) ? $categoryScoped : array_values($scoped);

        if (empty($pool)) {
            return null;
        }

        $picked = $this->resolver->pick($pool, $sessionId, $code);

        if (! $picked) {
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
            'device_target' => 'all',
            ...BannerService::resolveImageGrid([
                'desktop_en' => $ad['image_url']['en'] ?? null,
                'desktop_ar' => $ad['image_url']['ar'] ?? null,
                'mobile_en' => $ad['mobile_image_url']['en'] ?? null,
                'mobile_ar' => $ad['mobile_image_url']['ar'] ?? null,
            ], ['desktop', 'mobile']),
            'is_external' => $ad['is_external'],
            'is_paid' => true,
            'ad' => $ad['ad'],
            'mobile_variant' => null,
        ];
    }
}
