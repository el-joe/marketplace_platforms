<?php

namespace Tests\Feature\Ads;

use App\Models\Admin;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Models\PaidAdSlot;
use App\Services\Customer\BuyBoxRebuildService;
use App\Services\Customer\ProductQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class ListingBoostRankingTest extends TestCase
{
    use RefreshDatabase;

    private function book($s, PaidAdSlot $slot, $listing, string $status = 'active'): void
    {
        $b = PaidAdBooking::create([
            'booking_reference' => 'ADB-'.Str::random(6), 'paid_ad_slot_id' => $slot->id,
            'advertiser_type' => 'vendor', 'vendor_id' => $listing->vendor_id, 'country_id' => $s->country->id,
            'pricing_model' => 'fixed_daily', 'pricing_units' => 1, 'unit_rate' => 1000, 'agreed_rate' => 1000,
            'quoted_amount' => 1000, 'tax_amount' => 0, 'booked_from' => today(), 'booked_until' => today()->addDays(5),
            'currency' => 'AED', 'status' => $status, 'payment_status' => 'paid', 'payment_method' => 'wallet',
        ]);
        PaidAdCreative::create([
            'paid_ad_booking_id' => $b->id, 'version' => 1, 'vendor_id' => $listing->vendor_id,
            'destination_url' => '/p', 'destination_type' => 'listing', 'destination_reference_id' => $listing->id,
            'status' => 'approved', 'is_current' => true, 'approved_at' => now(),
        ]);
    }

    private function slot($s, bool $popup): PaidAdSlot
    {
        return PaidAdSlot::create([
            'country_id' => $s->country->id, 'created_by_admin_id' => Admin::factory()->create()->id,
            'name' => 'Promo', 'slot_code' => 'p-'.Str::random(8), 'pricing_model' => 'fixed_daily',
            'base_rate' => 1000, 'currency' => 'AED', 'min_booking_days' => 1, 'max_booking_days' => 30,
            'is_available' => true, 'requires_approval' => false, 'max_concurrent' => 5,
            'lead_time_days' => 0, 'allowed_advertisers' => 'vendor', 'target_type' => 'listing_promotion',
            'shows_popup' => $popup,
        ]);
    }

    private function ids($s): array
    {
        $svc = app(ProductQueryService::class);
        $q = $svc->applyFilters($svc->baseQuery($s->country), []);

        return $svc->applySort($q, 'relevance')->pluck('bb.product_id')->all();
    }

    public function test_boosted_listing_ranks_first_by_tier_without_duplicates(): void
    {
        $s = MarketplaceScenario::make()->build();
        app(BuyBoxRebuildService::class)->rebuildCountry($s->country);

        // Clone the product + buy-box row so there are two ranked products.
        $orig = DB::table('products')->where('id', $s->product->id)->first();
        $newId = (string) Str::uuid();
        $prod = (array) $orig;
        $prod['id'] = $newId;
        $prod['slug'] = 'clone-'.Str::random(6);
        foreach (['sku', 'code'] as $k) {
            if (array_key_exists($k, $prod) && $prod[$k] !== null) {
                $prod[$k] = $prod[$k].'-c';
            }
        }
        DB::table('products')->insert($prod);
        $bb = (array) DB::table('product_country_buybox')->where('product_id', $s->product->id)->first();
        $bb['product_id'] = $newId;
        $bb['listing_id'] = (string) Str::uuid(); // not a boosted listing
        DB::table('product_country_buybox')->insert($bb);

        $listing = $s->vendorListingFbp;
        DB::table('product_country_buybox')->where('product_id', $s->product->id)
            ->update(['listing_type' => 'vendor', 'listing_id' => $listing->id]);

        // No bookings: rows unique, both present.
        $this->assertCount(2, $this->ids($s));

        // Plain promotion slot boost -> original first.
        $plain = $this->slot($s, false);
        $this->book($s, $plain, $listing);
        $this->assertSame([$s->product->id, $newId], $this->ids($s));

        // Two overlapping bookings (plain + popup) must not duplicate the row.
        $this->book($s, $this->slot($s, true), $listing);
        $ids = $this->ids($s);
        $this->assertCount(2, $ids);
        $this->assertSame($s->product->id, $ids[0]);

        // Cancelled bookings no longer rank.
        PaidAdBooking::query()->update(['status' => 'cancelled']);
        $this->assertCount(2, $this->ids($s));
    }

    public function test_tier_ordering_popup_then_plain_then_unboosted(): void
    {
        $s = MarketplaceScenario::make()->build();
        app(BuyBoxRebuildService::class)->rebuildCountry($s->country);

        $clone = function () use ($s): array {
            $prod = (array) DB::table('products')->where('id', $s->product->id)->first();
            $id = (string) Str::uuid();
            $prod['id'] = $id;
            $prod['slug'] = 'clone-'.Str::random(6);
            foreach (['sku', 'code'] as $k) {
                if (array_key_exists($k, $prod) && $prod[$k] !== null) {
                    $prod[$k] = $prod[$k].'-'.Str::random(3);
                }
            }
            DB::table('products')->insert($prod);
            $bb = (array) DB::table('product_country_buybox')->where('product_id', $s->product->id)->first();
            $bb['product_id'] = $id;

            return [$id, $bb];
        };

        [$plainId, $bbPlain] = $clone();
        [$freeId, $bbFree] = $clone();
        $popup = $s->vendorListingFbp;
        $plainListing = $s->vendorListingFbp->replicate();
        $plainListing->id = (string) Str::uuid();
        $plainListing->save();

        DB::table('product_country_buybox')->where('product_id', $s->product->id)
            ->update(['listing_type' => 'vendor', 'listing_id' => $popup->id]);
        $bbPlain['listing_type'] = 'vendor';
        $bbPlain['listing_id'] = $plainListing->id;
        $bbFree['listing_id'] = (string) Str::uuid();
        DB::table('product_country_buybox')->insert($bbPlain);
        DB::table('product_country_buybox')->insert($bbFree);

        $this->book($s, $this->slot($s, false), $plainListing);
        $this->book($s, $this->slot($s, true), $popup);

        $this->assertSame([$s->product->id, $plainId, $freeId], $this->ids($s));
    }
}
