<?php

namespace Tests\Feature\Cart;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCustomAttributeValue;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CustomAttributesHttpTest extends TestCase
{
    use RefreshDatabase;

    private $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->s->product->update(['has_custom_attributes' => true]);
    }

    private function admin(Vendor $v): VendorAdmin
    {
        return VendorAdmin::create([
            'vendor_id' => $v->id, 'name' => 'V', 'email' => 'v-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function cartUrl(): string
    {
        return "/api/customer/v1/{$this->s->country->site_code}/cart/items";
    }

    public function test_required_missing_returns_422_and_unchecked_required_checkbox_counts_missing(): void
    {
        $chk = $this->s->product->customAttributes()->create(['label' => 'Agree', 'type' => 'checkbox', 'is_required' => true]);
        $this->actingAs($this->s->customer, 'customer');
        $base = ['vendor_listing_id' => $this->s->vendorListingFbp->id, 'quantity' => 1];

        $this->postJson($this->cartUrl(), $base)->assertStatus(422);
        $this->postJson($this->cartUrl(), $base + ['custom_attribute_values' => [
            ['product_custom_attribute_id' => $chk->id, 'value' => '0'],
        ]])->assertStatus(422);
        $this->postJson($this->cartUrl(), $base + ['custom_attribute_values' => [
            ['product_custom_attribute_id' => $chk->id, 'value' => '1'],
        ]])->assertStatus(201);
    }

    public function test_partner_cannot_edit_another_vendors_product_attributes(): void
    {
        $other = $this->s->vendor->replicate();
        $other->email = 'o-'.Str::random(6).'@example.test';
        $other->store_name = 'Other '.Str::random(6);
        $other->store_slug = 'o-'.Str::random(8);
        $other->onboarding_completed_at = now();
        $other->phone = '+9715'.fake()->numerify('########');
        $other->save();
        $attr = $this->s->product->customAttributes()->create(['label' => 'L', 'type' => 'text']);
        $base = "/api/partner/v1/products/{$this->s->product->id}/custom-attributes";

        $this->actingAs($this->admin($other), 'vendor_api');
        $this->postJson($base, ['label' => 'Hack'])->assertForbidden();
        $this->putJson("$base/{$attr->id}", ['label' => 'Hack'])->assertForbidden();
        $this->deleteJson("$base/{$attr->id}")->assertForbidden();
        $this->assertSame('L', $attr->fresh()->label);
    }

    private function makeOrderItem(): OrderItem
    {
        $s = $this->s;
        $order = Order::create([
            'customer_id' => $s->customer->id, 'country_id' => $s->country->id,
            'order_number' => 'ORD-T-'.Str::random(6), 'status' => 'placed', 'currency' => 'AED',
            'subtotal' => 1000, 'shipping' => 0, 'tax' => 0, 'total' => 1000,
            'payment_method' => 'cod', 'payment_status' => 'pending',
            'shipping_address_snapshot' => [], 'ip_address' => '127.0.0.1', 'placed_at' => now(),
        ]);
        $sub = SubOrder::create([
            'order_id' => $order->id, 'sub_order_number' => 'SO-T-'.Str::random(6), 'vendor_id' => $s->vendor->id,
            'status' => 'placed', 'fulfillment_model' => 'fbm', 'subtotal' => 1000, 'shipping' => 0, 'tax' => 0,
            'platform_commission' => 0, 'vendor_payout' => 1000,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'sub_order_id' => $sub->id, 'product_variant_id' => $s->variants[0]->id,
            'vendor_listing_id' => $s->vendorListingFbp->id, 'vendor_id' => $s->vendor->id,
            'sku' => $s->vendorListingFbp->productVariant->sku, 'quantity' => 1, 'unit_price' => 1000,
            'line_subtotal' => 1000, 'line_discount' => 0, 'line_tax' => 0, 'line_total' => 1000,
            'commission_rate_pct' => 0, 'commission_amount' => 0, 'product_snapshot' => [],
        ]);
    }

    public function test_order_values_are_snapshots_immune_to_definition_edits(): void
    {
        $attr = $this->s->product->customAttributes()->create(['label' => 'Engrave', 'type' => 'text', 'unit' => 'u']);
        $item = $this->makeOrderItem();
        $val = OrderItemCustomAttributeValue::create([
            'order_item_id' => $item->id, 'product_custom_attribute_id' => $attr->id,
            'label' => 'Engrave', 'unit' => 'u', 'value' => 'Ali',
        ]);

        $this->actingAs($this->admin($this->s->vendor), 'vendor_api');
        $base = "/api/partner/v1/products/{$this->s->product->id}/custom-attributes";
        $this->putJson("$base/{$attr->id}", ['label' => 'Renamed'])->assertOk();
        $this->assertSame('Renamed', $attr->fresh()->label);
        $this->deleteJson("$base/{$attr->id}")->assertOk();

        $val->refresh();
        $this->assertSame('Engrave', $val->label);
        $this->assertSame('Ali', $val->value);
    }
}
