<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Marketer;
use App\Models\MarketerCommissionRule;
use App\Models\OpenMarketCategoryCommission;
use App\Models\TravelCategory;
use App\Models\ClassifiedCategory;
use App\Models\Category;
use App\Models\Country;
use App\Models\MarketerCategoryCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MarketerCategoryCommissionStoreTest extends TestCase
{
    use RefreshDatabase;

    private function storeCommission(array $data)
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketers.manage', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketers.view', 'guard_name' => 'admin']);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['marketers.manage', 'marketers.view']);
        $marketer = Marketer::create([
            'name' => 'M', 'email' => 'm-'.uniqid().'@example.test', 'phone' => '+9715'.random_int(10000000, 99999999),
            'marketer_type' => 'influencer', 'global_status' => 'active', 'approved_at' => now(),
        ]);

        return [$marketer, $this->actingAs($admin, 'admin')
            ->post(route('admin.marketers.category-commissions.store', $marketer), $data)];
    }

    public function test_both_mode(): void
    {
        [$m, $r] = $this->storeCommission(['commission_rate' => 10, 'commission_flat_amount' => 5]);
        $r->assertSessionHasNoErrors();
        $c = MarketerCategoryCommission::where('marketer_id', $m->id)->first();
        $this->assertSame('both', $c->commission_mode);
        $this->assertSame(15, $c->resolveAmount(100));
    }

    public function test_fixed_and_percentage_modes(): void
    {
        [$m] = $this->storeCommission(['commission_flat_amount' => 7]);
        $this->assertSame('fixed', MarketerCategoryCommission::where('marketer_id', $m->id)->first()->commission_mode);
        [$m2] = $this->storeCommission(['commission_rate' => 5]);
        $this->assertSame('percentage', MarketerCategoryCommission::where('marketer_id', $m2->id)->first()->commission_mode);
    }

    public function test_requires_at_least_one(): void
    {
        [, $r] = $this->storeCommission(['commission_rate' => 0, 'commission_flat_amount' => 0]);
        $r->assertSessionHasErrors('commission_rate');
    }

    public function test_store_per_scope_and_category_validation(): void
    {
        $tc = TravelCategory::create(['name_en' => 'T', 'name_ar' => 'س', 'slug' => 't-'.uniqid(), 'is_active' => true]);
        [$m, $r] = $this->storeCommission(['scope' => 'travel', 'category_id' => $tc->id, 'commission_rate' => 3]);
        $r->assertSessionHasNoErrors();
        $this->assertSame(1, MarketerCommissionRule::where('marketer_id', $m->id)->where('scope', 'travel')->count());

        [$m2, $r2] = $this->storeCommission(['scope' => 'products', 'category_id' => $tc->id, 'commission_rate' => 3]);
        $r2->assertSessionHasErrors('category_id');
        $this->assertSame(0, MarketerCommissionRule::where('marketer_id', $m2->id)->count());

        [$m3] = $this->storeCommission(['scope' => 'open_market', 'commission_rate' => 2]);
        $this->assertSame(1, OpenMarketCategoryCommission::where('marketer_id', $m3->id)->count());
    }

    public function test_delete_removes_both_tables(): void
    {
        [$m] = $this->storeCommission(['scope' => 'open_market', 'commission_rate' => 2]);
        [$m2] = $this->storeCommission(['commission_rate' => 4]);
        $admin = Admin::first();
        foreach ([$m, $m2] as $mk) {
            $rule = MarketerCommissionRule::where('marketer_id', $mk->id)->first();
            $this->actingAs($admin, 'admin')
                ->delete(route('admin.marketers.category-commissions.destroy', [$mk, $rule->id]))->assertSessionHasNoErrors();
        }
        $this->assertSame(0, MarketerCommissionRule::count());
        $this->assertSame(0, OpenMarketCategoryCommission::count());
        $this->assertSame(0, MarketerCategoryCommission::count());
    }

    public function test_currency_from_marketer_country(): void
    {
        [$m] = $this->storeCommission(['commission_flat_amount' => 5]);
        $country = Country::factory()->create(['currency_code' => 'EGP']);
        $m->update(['country_id' => $country->id]);
        $this->actingAs(Admin::first(), 'admin')->get(route('admin.marketers.show', $m))
            ->assertOk()->assertSee('5 EGP');
    }
}
