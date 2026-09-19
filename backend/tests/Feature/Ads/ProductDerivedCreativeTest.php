<?php

namespace Tests\Feature\Ads;

use App\Models\Admin;
use App\Models\BannerPlacementDefinition;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Models\PaidAdSlot;
use App\Models\ProductImage;
use App\Services\Ads\AdCreativeService;
use App\Services\Ads\PaidAdPresenter;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class ProductDerivedCreativeTest extends TestCase
{
    use RefreshDatabase;

    private function make(): array
    {
        $s = MarketplaceScenario::make()->build();
        $def = BannerPlacementDefinition::where('code', 'product_page_top')->first() ?? BannerPlacementDefinition::create([
            'code' => 'product_page_top', 'name' => 'PPT', 'width_px' => 1200, 'height_px' => 200,
            'max_file_size_kb' => 256, 'allowed_formats' => ['jpg'], 'device_restriction' => 'all',
            'max_simultaneous' => 1, 'supports_vendor_ads' => true, 'base_rate_weekly' => 1,
            'is_active' => true, 'sort_order' => 1, 'creative_source' => 'product', 'allowed_destination_types' => ['listing'],
        ]);
        $slot = PaidAdSlot::create([
            'country_id' => $s->country->id, 'created_by_admin_id' => Admin::factory()->create()->id,
            'name' => 'S', 'slot_code' => 's-'.Str::random(6), 'pricing_model' => 'fixed_daily',
            'base_rate' => 1000, 'currency' => 'AED', 'min_booking_days' => 1, 'max_booking_days' => 30,
            'is_available' => true, 'requires_approval' => false, 'max_concurrent' => 1, 'lead_time_days' => 0,
            'allowed_advertisers' => 'vendor', 'target_type' => 'placement', 'placement_definition_id' => $def->id,
        ]);
        $booking = PaidAdBooking::create([
            'booking_reference' => 'ADB-'.Str::random(6), 'paid_ad_slot_id' => $slot->id,
            'advertiser_type' => 'vendor', 'vendor_id' => $s->vendor->id, 'country_id' => $s->country->id,
            'pricing_model' => 'fixed_daily', 'pricing_units' => 1, 'unit_rate' => 1000, 'agreed_rate' => 1000,
            'quoted_amount' => 1000, 'tax_amount' => 0, 'booked_from' => today(), 'booked_until' => today()->addDay(),
            'currency' => 'AED', 'status' => 'draft', 'payment_status' => 'unpaid', 'payment_method' => 'wallet',
        ]);

        return [$s, $slot, $booking];
    }

    public function test_product_mode_upload_without_files_succeeds(): void
    {
        [$s, $slot, $booking] = $this->make();
        $this->assertTrue($slot->fresh()->derivesCreativeFromProduct());

        $creative = app(AdCreativeService::class)->upload($booking, [
            'destination_type' => 'listing', 'destination_reference_id' => $s->vendorListingFbp->id,
        ], [], Admin::factory()->create());

        $this->assertSame('listing', $creative->destination_type);
        $this->assertCount(0, $creative->files);
    }

    public function test_product_mode_rejects_non_listing_destination(): void
    {
        [$s, , $booking] = $this->make();

        $this->expectException(DomainException::class);
        app(AdCreativeService::class)->upload($booking, ['destination_type' => 'store'], [], Admin::factory()->create());
    }

    public function test_presenter_uses_variant_primary_then_product_primary(): void
    {
        [$s, , $booking] = $this->make();
        $listing = $s->vendorListingFbp;
        $variant = $listing->productVariant;

        ProductImage::where('product_id', $variant->product_id)->delete();
        ProductImage::create(['product_id' => $variant->product_id, 'path' => 'p/product.jpg', 'disk' => 'public', 'position' => 0, 'is_primary' => true]);

        PaidAdCreative::create([
            'paid_ad_booking_id' => $booking->id, 'version' => 1, 'vendor_id' => $booking->vendor_id,
            'destination_url' => '/p', 'destination_type' => 'listing', 'destination_reference_id' => $listing->id,
            'status' => 'approved', 'is_current' => true, 'approved_at' => now(),
        ]);

        $out = PaidAdPresenter::present($booking->fresh(['currentCreative', 'slot.placementDefinition']));
        $this->assertStringContainsString('p/product.jpg', $out['image_url']['en']);

        ProductImage::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'path' => 'p/variant.jpg', 'disk' => 'public', 'position' => 0, 'is_primary' => true]);
        app(\App\Services\Media\ListingImageResolver::class)->clearMemo();
        \Illuminate\Support\Facades\Cache::forget(\App\Services\Media\ListingImageResolver::cacheKey($variant->id));

        $out = PaidAdPresenter::present($booking->fresh(['currentCreative', 'slot.placementDefinition']));
        $this->assertStringContainsString('p/variant.jpg', $out['mobile_image_url']['ar']);
        $this->assertSame($listing->id, $out['link_reference_id']);
    }
}
