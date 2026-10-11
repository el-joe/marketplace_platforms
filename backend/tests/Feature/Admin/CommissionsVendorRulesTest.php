<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Vendor;
use App\Services\Customer\CartService;
use App\Services\Vendor\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CommissionsVendorRulesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): Admin
    {
        foreach (array_merge($permissions, ['vendors.assigned_only']) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function manager(): Admin
    {
        return $this->admin(['vendors.view', 'commission_rules.manage']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'vendor_id' => Vendor::factory()->create()->id,
            'category_id' => '',
            'rate_pct' => '6.5',
            'rate_type' => 'flat',
            'min_commission' => '10',
            'max_commission' => '500',
            'effective_from' => '2026-01-01',
            'effective_until' => '',
            'priority' => '5',
        ], $overrides);
    }

    private function rule(array $attributes = []): Commission
    {
        return Commission::create($attributes + [
            'vendor_id' => Vendor::factory()->create()->id, 'rate_pct' => 5, 'rate_type' => 'flat',
            'min_commission' => 0, 'effective_from' => '2026-01-01', 'priority' => 0,
        ]);
    }

    public function test_creates_rule_logs_the_change_and_returns_to_the_vendor_rules_tab(): void
    {
        $payload = $this->payload();

        $this->actingAs($this->manager(), 'admin')
            ->post(route('admin.commissions.vendor-rules.store'), $payload)
            ->assertRedirect(route('admin.commissions.index', ['tab' => 'vendor-rules']))
            ->assertSessionHas('success');

        $rule = Commission::firstOrFail();
        $this->assertSame($payload['vendor_id'], $rule->vendor_id);
        $this->assertNull($rule->category_id);
        $this->assertSame('6.50', (string) $rule->rate_pct);
        $this->assertSame(10, (int) $rule->min_commission);
        $this->assertSame(500, (int) $rule->max_commission);
        $this->assertSame(5, $rule->priority);
        $this->assertNull($rule->effective_until);

        $log = Activity::where('event', 'commission_rule_created')->firstOrFail();
        $this->assertSame($rule->id, $log->subject_id);
        $this->assertSame($payload['vendor_id'], $log->properties['after']['vendor_id']);
    }

    public function test_updates_rule_and_logs_before_and_after(): void
    {
        $rule = $this->rule(['rate_pct' => 5]);

        $this->actingAs($this->manager(), 'admin')
            ->put(route('admin.commissions.vendor-rules.update', $rule), $this->payload(['vendor_id' => $rule->vendor_id, 'rate_pct' => '8']))
            ->assertRedirect(route('admin.commissions.index', ['tab' => 'vendor-rules']));

        $this->assertSame('8.00', (string) $rule->fresh()->rate_pct);
        $log = Activity::where('event', 'commission_rule_updated')->firstOrFail();
        $this->assertSame('5.00', (string) $log->properties['before']['rate_pct']);
        $this->assertSame('8.00', (string) $log->properties['after']['rate_pct']);
    }

    public function test_deletes_rule_and_logs_the_removed_values(): void
    {
        $rule = $this->rule();

        $this->actingAs($this->manager(), 'admin')
            ->delete(route('admin.commissions.vendor-rules.destroy', $rule))
            ->assertRedirect(route('admin.commissions.index', ['tab' => 'vendor-rules']));

        $this->assertModelMissing($rule);
        $this->assertSame('5.00', (string) Activity::where('event', 'commission_rule_deleted')->firstOrFail()->properties['before']['rate_pct']);
    }

    public function test_forbids_write_actions_for_admin_who_can_only_view(): void
    {
        $viewer = $this->admin(['vendors.view']);
        $rule = $this->rule();

        $this->actingAs($viewer, 'admin')->post(route('admin.commissions.vendor-rules.store'), $this->payload())->assertForbidden();
        $this->actingAs($viewer, 'admin')->put(route('admin.commissions.vendor-rules.update', $rule), $this->payload())->assertForbidden();
        $this->actingAs($viewer, 'admin')->delete(route('admin.commissions.vendor-rules.destroy', $rule))->assertForbidden();

        $this->assertSame(1, Commission::count());
        $this->assertSame('5.00', (string) $rule->fresh()->rate_pct);
    }

    public function test_forbids_scoped_assigned_only_admin_even_with_manage_permission(): void
    {
        $scoped = $this->admin(['vendors.view', 'commission_rules.manage', 'vendors.assigned_only']);
        $rule = $this->rule();

        $this->actingAs($scoped, 'admin')->post(route('admin.commissions.vendor-rules.store'), $this->payload())->assertForbidden();
        $this->actingAs($scoped, 'admin')->put(route('admin.commissions.vendor-rules.update', $rule), $this->payload())->assertForbidden();
        $this->actingAs($scoped, 'admin')->delete(route('admin.commissions.vendor-rules.destroy', $rule))->assertForbidden();

        $this->assertSame(1, Commission::count());
    }

    public function test_redirects_guest_instead_of_writing(): void
    {
        $this->post(route('admin.commissions.vendor-rules.store'), $this->payload())->assertRedirect(route('admin.login'));

        $this->assertSame(0, Commission::count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'rate above 100' => [['rate_pct' => '100.01'], 'rate_pct'],
            'negative rate' => [['rate_pct' => '-1'], 'rate_pct'],
            'non numeric rate' => [['rate_pct' => 'abc'], 'rate_pct'],
            'three decimal places' => [['rate_pct' => '5.123'], 'rate_pct'],
            'missing rate' => [['rate_pct' => ''], 'rate_pct'],
            'tiered type checkout ignores' => [['rate_type' => 'tiered'], 'rate_type'],
            'unknown type' => [['rate_type' => 'percentage'], 'rate_type'],
            'negative minimum' => [['min_commission' => '-5'], 'min_commission'],
            'fractional minimum' => [['min_commission' => '1.5'], 'min_commission'],
            'maximum below minimum' => [['min_commission' => '100', 'max_commission' => '50'], 'max_commission'],
            'end before start' => [['effective_from' => '2026-05-01', 'effective_until' => '2026-04-01'], 'effective_until'],
            'missing start date' => [['effective_from' => ''], 'effective_from'],
            'invalid date' => [['effective_from' => 'not-a-date'], 'effective_from'],
            'priority too high' => [['priority' => '1001'], 'priority'],
            'unknown vendor' => [['vendor_id' => '11111111-1111-4111-8111-111111111111'], 'vendor_id'],
            'unknown category' => [['category_id' => '11111111-1111-4111-8111-111111111111'], 'category_id'],
            'vendor id not a uuid' => [['vendor_id' => '1 OR 1=1'], 'vendor_id'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_input_without_saving(array $override, string $errorField): void
    {
        $this->actingAs($this->manager(), 'admin')
            ->post(route('admin.commissions.vendor-rules.store'), $this->payload($override))
            ->assertSessionHasErrors($errorField);

        $this->assertSame(0, Commission::count());
    }

    public function test_platform_wide_rule_requires_explicit_confirmation(): void
    {
        $manager = $this->manager();
        $platformWide = $this->payload(['vendor_id' => '', 'category_id' => '']);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.commissions.vendor-rules.store'), $platformWide)
            ->assertSessionHasErrors('confirm_platform_wide');
        $this->assertSame(0, Commission::count());

        $this->actingAs($manager, 'admin')
            ->post(route('admin.commissions.vendor-rules.store'), $platformWide + ['confirm_platform_wide' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertNull(Commission::firstOrFail()->vendor_id);
    }

    public function test_category_scoped_rule_needs_no_platform_wide_confirmation(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->manager(), 'admin')
            ->post(route('admin.commissions.vendor-rules.store'), $this->payload(['vendor_id' => '', 'category_id' => $category->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($category->id, Commission::firstOrFail()->category_id);
    }

    public function test_saved_rule_is_returned_by_the_vendor_finance_resolver(): void
    {
        $payload = $this->payload(['rate_pct' => '12']);

        $this->actingAs($this->manager(), 'admin')->post(route('admin.commissions.vendor-rules.store'), $payload);

        $resolved = app(FinanceService::class)->resolveCommissionRates(Vendor::findOrFail($payload['vendor_id']));
        $this->assertSame(12.0, $resolved[0]['rate_pct']);
        $this->assertSame('vendor', $resolved[0]['scope']);
    }

    public function test_saved_rule_sets_the_commission_charged_at_checkout(): void
    {
        Notification::fake();
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        // Same isolation the reconciliation suite uses: remove other sources that outrank the category chain.
        Commission::query()->delete();
        $s->vendor->forceFill(['commission_rate' => 0])->save();

        $this->actingAs($this->manager(), 'admin')->post(route('admin.commissions.vendor-rules.store'), $this->payload([
            'vendor_id' => $s->vendor->id, 'rate_pct' => '5', 'min_commission' => '0', 'max_commission' => '', 'effective_from' => now()->subDay()->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'vendor_listing_id' => $s->vendorListingFbp->id, 'quantity' => 2,
            'unit_price' => (int) $s->vendorListingFbp->getRawOriginal('price'), 'added_at' => now(),
        ]);
        $response = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
        $item = $order->subOrders()->firstOrFail()->items()->firstOrFail();

        $this->assertSame((int) round((int) $item->line_subtotal * 5 / 100), (int) $item->commission_amount);
    }
}
