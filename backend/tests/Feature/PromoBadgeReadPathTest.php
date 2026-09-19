<?php

namespace Tests\Feature;

use App\Http\Controllers\Customer\ListingDetailController;
use App\Models\ProductPromoBadge;
use App\Services\Customer\ListingQueryService;
use App\Services\Customer\PromoBadgeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class PromoBadgeReadPathTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;
    private const KEYS = ['id', 'label', 'icon_key', 'color_hex', 'text_color_hex', 'sort_order'];

    protected function setUp(): void
    {
        parent::setUp();
        PromoBadgeResolver::instance()->flushMemo();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->forceFill(['site_code' => 'eg'])->save();
    }

    private function badge(array $owner, string $en, int $sort = 0, bool $active = true): ProductPromoBadge
    {
        return ProductPromoBadge::factory()->create($owner + [
            'label_en' => $en, 'label_ar' => $en . '-ar', 'sort_order' => $sort, 'is_active' => $active,
        ] + ['product_id' => $this->s->product->id]);
    }

    private function labels(array $badges): array
    {
        return array_map(fn ($b) => $b['label']['en'], $badges);
    }

    public function test_precedence_listing_over_product_level_for_each_owner_type(): void
    {
        $pid = $this->s->product->id;
        $this->badge(['vendor_listing_id' => null], 'product-level', 0);
        $this->badge(['vendor_listing_id' => $this->s->vendorListingFbp->id], 'vendor-own', 0);
        $this->badge(['admin_listing_id' => $this->s->adminListing->id], 'admin-own', 0);
        $this->badge(['marketer_listing_id' => $this->s->marketerListing->id], 'marketer-own', 0);

        $r = PromoBadgeResolver::instance();
        $this->assertSame(['vendor-own'], $this->labels($r->forOne('vendor', $this->s->vendorListingFbp->id, $pid)));
        $this->assertSame(['admin-own'], $this->labels($r->forOne('admin', $this->s->adminListing->id, $pid)));
        $this->assertSame(['marketer-own'], $this->labels($r->forOne('marketer', $this->s->marketerListing->id, $pid)));
        // listing with no own badges falls back to product level
        $this->assertSame(['product-level'], $this->labels($r->forOne('vendor', $this->s->vendorListingFbn->id, $pid)));
        // no listing, no product badges => []
        $this->assertSame([], $r->forOne('vendor', (string) Str::uuid(), (string) Str::uuid()));
    }

    public function test_inactive_are_excluded_and_inactive_only_listing_falls_back(): void
    {
        $pid = $this->s->product->id;
        $this->badge(['vendor_listing_id' => null], 'product-level');
        $this->badge(['vendor_listing_id' => $this->s->vendorListingFbp->id], 'off', 0, false);
        $this->badge(['vendor_listing_id' => null], 'product-off', 1, false);

        $out = (PromoBadgeResolver::instance())->forOne('vendor', $this->s->vendorListingFbp->id, $pid);
        $this->assertSame(['product-level'], $this->labels($out));
    }

    public function test_ordering_by_sort_order(): void
    {
        $id = $this->s->vendorListingFbp->id;
        $this->badge(['vendor_listing_id' => $id], 'c', 3);
        $this->badge(['vendor_listing_id' => $id], 'a', 1);
        $this->badge(['vendor_listing_id' => $id], 'b', 2);
        $out = (PromoBadgeResolver::instance())->forOne('vendor', $id, $this->s->product->id);
        $this->assertSame(['a', 'b', 'c'], $this->labels($out));
        $this->assertSame(self::KEYS, array_keys($out[0]));
        $this->assertSame(['ar', 'en'], array_keys($out[0]['label']));
    }

    public function test_batch_uses_exactly_one_query_for_20_items(): void
    {
        $this->badge(['vendor_listing_id' => null], 'product-level');
        $tuples = [];
        for ($i = 0; $i < 20; $i++) {
            $tuples[] = [['vendor', 'admin', 'marketer'][$i % 3], (string) Str::uuid(), $i < 10 ? $this->s->product->id : (string) Str::uuid()];
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $map = (PromoBadgeResolver::instance())->resolve($tuples);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $log, json_encode(array_column($log, 'query')));
        $this->assertCount(20, $map);
        $this->assertSame(['product-level'], $this->labels($map[PromoBadgeResolver::key(...$tuples[0])]));
        $this->assertSame([], $map[PromoBadgeResolver::key(...$tuples[15])]);
    }

    public function test_primed_card_shapes_do_not_query_and_match_pdp_shape(): void
    {
        $this->badge(['vendor_listing_id' => $this->s->vendorListingFbp->id], 'v', 1);
        $this->badge(['admin_listing_id' => $this->s->adminListing->id], 'a', 1);
        $this->badge(['marketer_listing_id' => $this->s->marketerListing->id], 'm', 1);

        $vl = $this->s->vendorListingFbp->load(['productVariant.product', 'vendor', 'primaryShippingMethod']);
        $al = $this->s->adminListing->load(['productVariant.product', 'primaryShippingMethod']);
        $ml = $this->s->marketerListing->load(['productVariant.product']);
        $listings = [$vl, $al, $ml];

        PromoBadgeResolver::instance()->flushMemo();
        PromoBadgeResolver::instance()->prime(PromoBadgeResolver::tuplesForListings($listings));

        $svc = app(ListingQueryService::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $cards = array_map(fn ($l) => $svc->toMixedCardShape($l, $l->productVariant->product, $this->s->country), $listings);
        $badgeQueries = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'product_promo_badges'));
        DB::disableQueryLog();
        $this->assertCount(0, $badgeQueries);

        $ctrl = app(ListingDetailController::class);
        $m = new \ReflectionMethod($ctrl, 'productShape');
        $m->setAccessible(true);
        foreach ($listings as $i => $l) {
            $product = $l->productVariant->product;
            $pdp = $m->invoke($ctrl, $product, $l, $this->s->country)['promo_badges'];
            $this->assertNotEmpty($cards[$i]['promo_badges']);
            $this->assertSame($cards[$i]['promo_badges'], $pdp);
            $this->assertSame(self::KEYS, array_keys($pdp[0]));
        }
    }

    public function test_two_consecutive_requests_see_badge_change(): void
    {
        $this->s->country->update(['site_code' => 'ae-' . Str::lower(Str::random(6))]);
        app(\App\Services\Customer\BuyBoxRebuildService::class)->rebuildCountry($this->s->country);
        $url = "/api/customer/v1/{$this->s->country->site_code}/browse/product/{$this->s->category->id}";

        $b = $this->badge(['vendor_listing_id' => null], 'first');
        $labels = fn () => collect($this->getJson($url)->assertOk()->json('data.listings.items') ?? [])
            ->flatMap(fn ($i) => array_map(fn ($x) => $x['label']['en'], $i['promo_badges'] ?? []))->all();

        $this->assertContains('first', $labels());
        $b->update(['label_en' => 'second']);
        $out = $labels();
        $this->assertContains('second', $out);
        $this->assertNotContains('first', $out);
    }
}
