<?php

namespace App\Services\Ads;

class PaidAdInjector
{
    public function __construct(private readonly PaidAdResolver $resolver)
    {
    }

    /** Flat shapes used by Shared\PageBuilderService and App\Services\PageBuilderService. */
    public function injectFlat(array $block, string $countryId, ?string $sessionId): array
    {
        $positions = $this->positionsFor($block['id'] ?? null, $countryId);

        if ($positions === null) {
            return $block;
        }

        return match ($block['block_type'] ?? null) {
            'hero_slider' => $this->applyList($block, 'slides', $positions, $sessionId,
                fn (array $ad) => $this->heroSlide($ad, false)),
            'ad_images_2col', 'ad_images_4col' => $this->applyList($block, 'items', $positions, $sessionId,
                fn (array $ad) => $this->adImageItem($ad, false)),
            'ad_images_3col' => $this->applyList($block, 'images', $positions, $sessionId,
                fn (array $ad) => $this->adImage3col($ad)),
            'image_slider' => $this->applyList($block, 'items', $positions, $sessionId,
                fn (array $ad) => $this->sliderItem($ad, false)),
            'promo_tiles' => $this->applyList($block, 'tiles', $positions, $sessionId,
                fn (array $ad) => $this->promoTile($ad)),
            'full_banner' => $this->applyBanner($block, $positions, $sessionId, false),
            default => $block,
        };
    }

    /** Nested shape used by Customer\PageRendererService: $block['data'] holds the item lists. */
    public function injectNested(array $block, string $countryId, ?string $sessionId): array
    {
        $positions = $this->positionsFor($block['id'] ?? null, $countryId);

        if ($positions === null) {
            return $block;
        }

        $type = $block['type'] ?? null;
        $data = $block['data'] ?? [];

        $data = match ($type) {
            'hero_slider' => $this->applyList($data, 'slides', $positions, $sessionId,
                fn (array $ad) => $this->heroSlide($ad, true)),
            'ad_images_2col', 'ad_images_4col', 'ad_images_3col' => $this->applyList($data, 'items', $positions, $sessionId,
                fn (array $ad) => $this->adImageItem($ad, true)),
            'image_slider' => $this->applyList($data, 'items', $positions, $sessionId,
                fn (array $ad) => $this->sliderItem($ad, true)),
            'promo_tiles' => $this->applyList($data, 'tiles', $positions, $sessionId,
                fn (array $ad) => $this->promoTile($ad)),
            'full_banner' => $this->applyBannerData($data, $positions, $sessionId),
            default => $data,
        };

        if ($type === 'image_slider' && isset($data['items'])) {
            $data['total_items'] = count($data['items']);
        }

        $block['data'] = $data;

        return $block;
    }

    private function positionsFor(?string $blockId, string $countryId): ?array
    {
        if (!$blockId) {
            return null;
        }

        $index = $this->resolver->activeIndex($countryId);

        return $index['page_block'][$blockId] ?? null;
    }

    private function applyList(array $container, string $key, array $positions, ?string $sessionId, \Closure $mapper): array
    {
        $list = $container[$key] ?? [];

        $ordered = $positions;
        krsort($ordered, SORT_NUMERIC);

        foreach ($ordered as $position => $candidates) {
            $picked = $this->resolver->pick($candidates, $sessionId, (string) $position);

            if (!$picked) {
                continue;
            }

            $adItem = $mapper($picked['payload']);
            $idx = max(0, ((int) $position) - 1);
            $fillMode = $picked['fill_mode'] ?? 'replace';

            if ($fillMode === 'insert') {
                array_splice($list, min($idx, count($list)), 0, [$adItem]);
            } else {
                if ($idx < count($list)) {
                    $list[$idx] = array_merge($list[$idx], $adItem);
                } else {
                    $list[] = $adItem;
                }
            }
        }

        $container[$key] = array_values($list);

        return $container;
    }

    private function applyBanner(array $block, array $positions, ?string $sessionId, bool $nested): array
    {
        $candidates = $positions['0'] ?? $positions[0] ?? reset($positions) ?: null;

        if (!$candidates) {
            return $block;
        }

        $picked = $this->resolver->pick($candidates, $sessionId, '0');

        if (!$picked) {
            return $block;
        }

        $existing = $block['banner'] ?? [];
        $block['banner'] = array_merge($existing, $this->bannerItem($picked['payload'], $existing));

        return $block;
    }

    private function applyBannerData(array $data, array $positions, ?string $sessionId): array
    {
        $candidates = $positions['0'] ?? $positions[0] ?? reset($positions) ?: null;

        if (!$candidates) {
            return $data;
        }

        $picked = $this->resolver->pick($candidates, $sessionId, '0');

        if (!$picked) {
            return $data;
        }

        return array_merge($data, $this->bannerItem($picked['payload'], $data));
    }

    private function heroSlide(array $ad, bool $nested): array
    {
        $base = [
            'title' => $ad['title'],
            'subtitle' => $ad['subtitle'],
            'cta_label' => $ad['cta_label'],
            'cta_url' => $ad['link_url'],
            'cta_open_new_tab' => $ad['is_external'],
            'link_type' => $ad['link_type'],
            'link_reference_id' => $ad['link_reference_id'],
            'text_color' => '#ffffff',
            'text_position' => 'left',
            'overlay_opacity' => 0,
            'is_paid' => true,
            'ad' => $ad['ad'],
        ];

        return $nested
            ? $base + ['desktop_image_url' => $ad['image_url'], 'mobile_image_url' => $ad['mobile_image_url']]
            : $base + ['desktop_url' => $ad['image_url'], 'mobile_url' => $ad['mobile_image_url']];
    }

    private function adImageItem(array $ad, bool $nested): array
    {
        return [
            'image_url' => $ad['image_url'],
            'title' => $ad['title'],
            'link_url' => $ad['link_url'],
            'link_open_new_tab' => $ad['is_external'],
            'alt_text' => $ad['title'],
            'show_title_overlay' => false,
            'is_paid' => true,
            'ad' => $ad['ad'],
        ];
    }

    private function adImage3col(array $ad): array
    {
        return [
            'image_url' => $ad['image_url'],
            'link_url' => $ad['link_url'],
            'title' => $ad['title'],
            'subtitle' => $ad['subtitle'],
            'is_paid' => true,
            'ad' => $ad['ad'],
        ];
    }

    private function sliderItem(array $ad, bool $nested): array
    {
        return [
            'image_url' => $ad['image_url'],
            'link_url' => $ad['link_url'],
            $nested ? 'link_new_tab' : 'link_open_new_tab' => $ad['is_external'],
            'title' => $ad['title'],
            'subtitle' => $ad['subtitle'],
            'badge' => ['en' => null, 'ar' => null],
            $nested ? 'alt' : 'alt_text' => $ad['title'],
            'is_paid' => true,
            'ad' => $ad['ad'],
        ];
    }

    private function promoTile(array $ad): array
    {
        return [
            'label' => $ad['title'],
            'badge' => ['en' => 'Ad', 'ar' => 'إعلان'],
            'image_url' => $ad['image_url'],
            'link_url' => $ad['link_url'],
            'is_paid' => true,
            'ad' => $ad['ad'],
        ];
    }

    private function bannerItem(array $ad, array $existing): array
    {
        return [
            'image_url' => $ad['image_url'],
            'mobile_image_url' => $ad['mobile_image_url'],
            'link_url' => $ad['link_url'],
            'link_type' => $ad['link_type'],
            'link_reference_id' => $ad['link_reference_id'],
            'alt_text' => $ad['title'],
            'aspect_ratio' => $existing['aspect_ratio'] ?? null,
            'mobile_aspect_ratio' => $existing['mobile_aspect_ratio'] ?? null,
            'is_paid' => true,
            'ad' => $ad['ad'],
        ];
    }
}
