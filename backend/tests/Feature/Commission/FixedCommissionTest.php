<?php

namespace Tests\Feature\Commission;

use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Commission;
use App\Models\CountryCategory;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Customer\CartService;
use App\Services\Shared\FixedCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client rule: commission = fixed (global % x amount / 100 + per-country flat amount)
 *                         + dynamic (category % x amount / 100 + category flat amount).
 */
class FixedCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function grantCategoryPermissions(Admin $admin): void
    {
        foreach (['categories.view', 'categories.edit', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo(['categories.view', 'categories.edit']);
    }

    public function test_service_adds_percentage_and_per_unit_country_amount(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        app(FixedCommissionService::class)->update(2.5, [$scenario->country->id => '1.9']);

        $fixed = app(FixedCommissionService::class)->calculate(3000, $scenario->country->fresh(), 3);

        // 2.5% of 3000 = 75, plus 1.9 x 3 units = 5.7
        $this->assertEqualsWithDelta(80.7, (float) $fixed, 0.0001);
    }

    public function test_service_returns_zero_when_nothing_is_configured(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $this->assertSame(0, app(FixedCommissionService::class)->calculate(3000, $scenario->country, 3));
    }

    public function test_checkout_commission_is_fixed_plus_dynamic(): void
    {
        Notification::fake();
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        Commission::query()->delete();
        $s->vendor->forceFill(['commission_rate' => 0])->save();
        CountryCategory::where('category_id', $s->category->id)->delete();
        // dynamic: fixture category FBP = 10% + 200 per unit; fixed: 2% + 5 per unit
        app(FixedCommissionService::class)->update(2, [$s->country->id => 5]);

        $this->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'vendor_listing_id' => $s->vendorListingFbp->id, 'quantity' => 3,
            'unit_price' => (int) $s->vendorListingFbp->getRawOriginal('price'), 'added_at' => now(),
        ]);
        $response = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
        $sub = $order->subOrders()->firstOrFail();

        // dynamic: 10% of 3000 + 200 x 3 = 900; fixed: 2% of 3000 + 5 x 3 = 75
        $this->assertEqualsWithDelta(975.0, (float) $sub->platform_commission, 0.0001);
    }

    public function test_admin_can_save_fixed_commission_from_categories_list(): void
    {
        $s = MarketplaceScenario::make()->build();
        $admin = Admin::factory()->create();
        $this->grantCategoryPermissions($admin);

        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.categories.fixed-commission.update'), [
                'fixed_commission_pct' => 3.5,
                'amounts' => [$s->country->id => '2.25'],
            ])->assertOk();

        $this->assertSame(3.5, (float) Setting::get('fixed_commission_pct'));
        $this->assertEqualsWithDelta(2.25, (float) $s->country->fresh()->fixed_commission_amount, 0.0001);
    }

    public function test_fixed_commission_rejects_percentage_over_100(): void
    {
        $admin = Admin::factory()->create();
        $this->grantCategoryPermissions($admin);

        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.categories.fixed-commission.update'), ['fixed_commission_pct' => 150, 'amounts' => []])
            ->assertStatus(422);
    }
}
