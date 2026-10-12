<?php

namespace Tests\Feature\Admin;

use App\Enums\PromotionCategory;
use App\Models\Admin;
use App\Models\MarketerPromotionFine;
use App\Models\PromotionCategoryTarget;
use App\Services\Marketer\PromotionPenaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PromotionQuotaFixtures as Q;
use Tests\TestCase;

/** P12 on the admin panel: targets + penalties, listing category option, category product page, fines list. */
class PromotionCategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();

        $perms = ['marketer_commission_settings.view', 'marketer_fee_settings.edit', 'admin_listings.view', 'admin_listings.edit',
            'admin_listings.create', 'marketers.view', 'marketers.manage', 'vendors.assigned_only'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo($perms);
    }

    public function test_targets_and_penalty_amounts_are_set_per_category_and_shown(): void
    {
        foreach (PromotionCategory::cases() as $i => $case) {
            $this->actingAs($this->admin, 'admin')->post(route('admin.marketer-settings.promotion-targets.update'), [
                'promotion_category' => $case->value, 'country_id' => null, 'monthly_minimum' => 3 + $i, 'penalty_amount' => 1000 * ($i + 1),
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(4, PromotionCategoryTarget::count());
        $row = PromotionCategoryTarget::where('promotion_category', 4)->sole();
        $this->assertSame(6, $row->monthly_minimum);
        $this->assertSame(4000, $row->penalty_amount);

        // update in place, no duplicate row
        $this->actingAs($this->admin, 'admin')->post(route('admin.marketer-settings.promotion-targets.update'), [
            'promotion_category' => 1, 'monthly_minimum' => 9,
        ])->assertSessionHasNoErrors();
        $one = PromotionCategoryTarget::where('promotion_category', 1)->sole();
        $this->assertSame(9, $one->monthly_minimum);
        $this->assertSame(0, $one->penalty_amount);

        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-settings.index'))
            ->assertOk()->assertSee('4,000');

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.marketer-settings.promotion-targets.destroy', $one))->assertRedirect();
        $this->assertSame(3, PromotionCategoryTarget::count());
    }

    public function test_invalid_penalty_and_category_are_rejected(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.marketer-settings.promotion-targets.update'), [
            'promotion_category' => 7, 'monthly_minimum' => 3, 'penalty_amount' => -5,
        ])->assertSessionHasErrors(['promotion_category', 'penalty_amount']);
    }

    public function test_listing_form_offers_category_three_and_four_and_persists_the_choice(): void
    {
        $listing = $this->s->adminListing;

        $this->actingAs($this->admin, 'admin')->get(route('admin.admin-listings.edit', $listing))
            ->assertOk()->assertSee('name="promotion_category"', false);
        $this->actingAs($this->admin, 'admin')->get(route('admin.admin-listings.create', ['promotion_category' => 4]))
            ->assertOk()->assertSee('value="4" selected', false);

        $payload = [
            'warehouse_id' => $listing->warehouse_id, 'price' => $listing->price, 'condition' => 'new',
            'low_stock_threshold' => 1, 'status' => 'active', 'handling_class' => 'standard',
        ];

        $this->actingAs($this->admin, 'admin')->put(route('admin.admin-listings.update', $listing), $payload + ['promotion_category' => '3'])
            ->assertSessionHasNoErrors();
        $this->assertSame(PromotionCategory::AdminIntermediary, $listing->fresh()->promotion_category);

        $this->actingAs($this->admin, 'admin')->put(route('admin.admin-listings.update', $listing), $payload + ['promotion_category' => '4'])
            ->assertSessionHasNoErrors();
        $this->assertSame(PromotionCategory::NawyProducts, $listing->fresh()->promotion_category);

        // only 3 / 4 are allowed on a listing
        $this->actingAs($this->admin, 'admin')->put(route('admin.admin-listings.update', $listing), $payload + ['promotion_category' => '1'])
            ->assertSessionHasErrors('promotion_category');

        $this->actingAs($this->admin, 'admin')->put(route('admin.admin-listings.update', $listing), $payload + ['promotion_category' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($listing->fresh()->promotion_category);
    }

    public function test_product_page_converts_moves_and_removes_listings_per_category(): void
    {
        $listing = $this->s->adminListing;
        $name = $listing->productVariant->product->name_en;

        $this->actingAs($this->admin, 'admin')->get(route('admin.promotion-products.index', ['category' => 3]))
            ->assertOk()->assertSee(__('admin.promotion_products.empty'));

        $this->actingAs($this->admin, 'admin')->get(route('admin.promotion-products.index', ['category' => 3, 'search' => $name]))
            ->assertOk()->assertSee(__('admin.promotion_products.convert'));

        $this->actingAs($this->admin, 'admin')->post(route('admin.promotion-products.assign'), [
            'admin_listing_id' => $listing->id, 'promotion_category' => 3,
        ])->assertRedirect();
        $this->assertSame(3, $listing->fresh()->promotion_category->value);

        $this->actingAs($this->admin, 'admin')->get(route('admin.promotion-products.index', ['category' => 3]))
            ->assertOk()->assertSee(route('admin.admin-listings.show', $listing), false);

        $this->actingAs($this->admin, 'admin')->post(route('admin.promotion-products.assign'), [
            'admin_listing_id' => $listing->id, 'promotion_category' => 4,
        ]);
        $this->assertSame(4, $listing->fresh()->promotion_category->value);

        $this->actingAs($this->admin, 'admin')->post(route('admin.promotion-products.assign'), [
            'admin_listing_id' => $listing->id, 'promotion_category' => 2,
        ])->assertSessionHasErrors('promotion_category');

        $this->actingAs($this->admin, 'admin')->delete(route('admin.promotion-products.remove', $listing))->assertRedirect();
        $this->assertNull($listing->fresh()->promotion_category);
    }

    public function test_product_page_is_forbidden_without_listing_permission(): void
    {
        $plain = Admin::factory()->create();
        $this->actingAs($plain, 'admin')->get(route('admin.promotion-products.index'))->assertForbidden();
        $this->actingAs($plain, 'admin')->get(route('admin.marketer-fines.index'))->assertForbidden();
    }

    public function test_fines_list_filters_and_waives_pending_fines(): void
    {
        $m = Q::marketerIn($this->s, PromotionCategory::VendorRequests, 2, 4000);
        app(PromotionPenaltyService::class)->closeMonth(now()->subMonthNoOverflow()->startOfMonth());
        $fine = MarketerPromotionFine::sole();

        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-fines.index'))
            ->assertOk()->assertSee($m->name)->assertSee('4,000')->assertSee($fine->period);

        $this->actingAs($this->admin, 'admin')->get(route('admin.marketer-fines.index', ['status' => 'deducted']))
            ->assertOk()->assertDontSee($m->name);

        $this->actingAs($this->admin, 'admin')->post(route('admin.marketer-fines.waive', $fine), ['note' => 'ok'])
            ->assertSessionHas('success');
        $this->assertSame(MarketerPromotionFine::WAIVED, $fine->fresh()->status);

        $this->actingAs($this->admin, 'admin')->post(route('admin.marketer-fines.waive', $fine))
            ->assertSessionHas('error');
    }
}
