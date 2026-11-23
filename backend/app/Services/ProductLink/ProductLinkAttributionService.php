<?php

namespace App\Services\ProductLink;

use App\Models\Country;
use App\Models\ProductLinkCommissionRule;
use App\Models\VendorListing;
use App\Services\Checkout\CartLineSource;
use App\Services\LastClickAttributionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * WS12 #24: remembers that a storefront session arrived through a vendor's own product link / QR code, and decides
 * at checkout which cart lines earn the platform-commission discount.
 *
 * Reuses the marketer referral mechanics (LastClickAttributionService): the storefront's X-Session-Id identifies the
 * visitor and the click lives in the cache for the attribution window. One slot per session + vendor + product, so
 * the latest click on that vendor's product link wins (last touch).
 *
 * A line qualifies when ALL of these hold:
 *  - the country has an active rule covering the line's sale model (fbm / fbn / cross_dock);
 *  - the customer buys the vendor's own listing (not a marketer's listing of it, not a platform listing);
 *  - this session clicked THAT vendor's link for THAT product inside the rule's window;
 *  - no marketer referral link was clicked after it (precedence: the most recent link wins; a newer marketer link
 *    means the marketer brought the customer, so no vendor discount).
 */
class ProductLinkAttributionService
{
    public function __construct(private readonly LastClickAttributionService $marketerAttribution) {}

    public function recordClick(VendorListing $listing, string $sessionId, string $channel): void
    {
        $productId = $listing->productVariant?->product_id;
        if (! $productId) {
            return;
        }

        Cache::put(self::key($sessionId, (string) $listing->vendor_id, (string) $productId), [
            'vendor_id' => (string) $listing->vendor_id,
            'product_id' => (string) $productId,
            'listing_id' => (string) $listing->id,
            'channel' => $channel === ProductShareLinkService::CHANNEL_QR ? ProductShareLinkService::CHANNEL_QR : ProductShareLinkService::CHANNEL_LINK,
            'clicked_at' => now()->toISOString(),
        ], now()->addDays(self::retentionDays()));
    }

    /**
     * @param  array<string, CartLineSource>  $cartLineSources  keyed by cart item id
     * @return array<string, array{rule: ProductLinkCommissionRule, channel: string, clicked_at: Carbon}> keyed by cart item id
     */
    public function resolveForCart(array $cartLineSources, Country $country, ?string $sessionId): array
    {
        if (! $sessionId || $cartLineSources === []) {
            return [];
        }

        $rule = ProductLinkCommissionRule::query()->where('country_id', $country->id)->where('is_active', true)->first();
        if (! $rule) {
            return [];
        }

        $marketerClickedAt = $this->latestMarketerClick($sessionId);
        $attributions = [];

        foreach ($cartLineSources as $cartItemId => $source) {
            if (! $source->sellable instanceof VendorListing || ! $rule->appliesTo($source->fulfillmentModel)) {
                continue;
            }

            $productId = $source->sellable->productVariant?->product_id;
            $click = $productId ? Cache::get(self::key($sessionId, (string) $source->sellable->vendor_id, (string) $productId)) : null;
            if (! is_array($click) || empty($click['clicked_at'])) {
                continue;
            }

            $clickedAt = Carbon::parse($click['clicked_at']);
            if ($clickedAt->lt(now()->subDays($rule->attribution_window_days))) {
                continue;
            }

            if ($marketerClickedAt && $marketerClickedAt->gt($clickedAt)) {
                continue;
            }

            $attributions[(string) $cartItemId] = ['rule' => $rule, 'channel' => (string) $click['channel'], 'clicked_at' => $clickedAt];
        }

        return $attributions;
    }

    /** Cache lifetime of a click: the longest active window (never shorter than the default). */
    public static function retentionDays(): int
    {
        $longest = (int) ProductLinkCommissionRule::query()->where('is_active', true)->max('attribution_window_days');

        return min(ProductLinkCommissionRule::MAX_WINDOW_DAYS, max(ProductLinkCommissionRule::DEFAULT_WINDOW_DAYS, $longest));
    }

    private function latestMarketerClick(string $sessionId): ?Carbon
    {
        $click = $this->marketerAttribution->clickFor($sessionId, 'product');
        if (! $click || empty($click['clicked_at'])) {
            return null;
        }

        $clickedAt = Carbon::parse($click['clicked_at']);
        $windowDays = (int) setting('marketer_attribution_window_days', LastClickAttributionService::DEFAULT_ATTRIBUTION_WINDOW_DAYS);

        return $clickedAt->gte(now()->subDays($windowDays)) ? $clickedAt : null;
    }

    private static function key(string $sessionId, string $vendorId, string $productId): string
    {
        return "product_link_click:{$sessionId}:{$vendorId}:{$productId}";
    }
}
