<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductImage;
use App\Models\SubOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-17 tasks 5 & 6: orders:backfill-snapshot-images and
 * images:audit.
 */
class ImageMaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_snapshot_images_fills_missing_image_and_variant_id(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $order = Order::create([
            'customer_id' => $scenario->customer->id,
            'country_id' => $scenario->country->id,
            'order_number' => 'ORD-TEST-' . Str::random(6),
            'status' => 'placed',
            'currency' => 'AED',
            'subtotal' => 1000,
            'shipping' => 0,
            'tax' => 0,
            'total' => 1000,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'shipping_address_snapshot' => [],
            'ip_address' => '127.0.0.1',
            'placed_at' => now(),
        ]);

        $subOrder = SubOrder::create([
            'order_id' => $order->id,
            'sub_order_number' => 'SO-TEST-' . Str::random(6),
            'vendor_id' => $scenario->vendor->id,
            'status' => 'placed',
            'fulfillment_model' => 'fbm',
            'subtotal' => 1000,
            'shipping' => 0,
            'tax' => 0,
            'platform_commission' => 0,
            'vendor_payout' => 1000,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'sub_order_id' => $subOrder->id,
            'product_variant_id' => $scenario->variants[0]->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'vendor_id' => $scenario->vendor->id,
            'sku' => $scenario->vendorListingFbp->productVariant->sku,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_subtotal' => 1000,
            'line_discount' => 0,
            'line_tax' => 0,
            'line_total' => 1000,
            'commission_rate_pct' => 0,
            'commission_amount' => 0,
            'product_snapshot' => ['name_en' => 'Old snapshot, no image'],
        ]);

        $this->artisan('orders:backfill-snapshot-images')->assertSuccessful();

        $item->refresh();
        $this->assertSame($scenario->variants[0]->id, $item->product_snapshot['variant_id']);
        $this->assertNotNull($item->product_snapshot['thumbnail_url']);
        $this->assertStringStartsWith('http', $item->product_snapshot['thumbnail_url']);
    }

    public function test_images_audit_reports_orphans_and_deletes_with_fix_force(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        ProductImage::create([
            'product_id' => null,
            'product_variant_id' => null,
            'path' => 'products/orphan.jpg',
            'disk' => 'public',
            'position' => 0,
            'is_primary' => false,
        ]);

        $this->artisan('images:audit')
            ->expectsOutputToContain('Orphan rows (both FKs null): 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('product_images', ['path' => 'products/orphan.jpg']);

        $this->artisan('images:audit --fix --force')->assertSuccessful();

        $this->assertDatabaseMissing('product_images', ['path' => 'products/orphan.jpg']);
    }
}
