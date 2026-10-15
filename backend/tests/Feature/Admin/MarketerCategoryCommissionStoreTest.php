<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCommissionRule;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\TravelCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** R4: type-aware per-category commission rules on the admin marketer profile. */
class MarketerCategoryCommissionStoreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        foreach (['vendors.assigned_only', 'marketers.manage', 'marketers.view'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['marketers.manage', 'marketers.view']);

        return $admin;
    }

    private function marketerOf(string ...$types): Marketer
    {
        $marketer = Marketer::create([
            'name' => 'M', 'email' => 'm-'.uniqid().'@example.test', 'phone' => '+9715'.random_int(10000000, 99999999),
            'global_status' => 'active', 'approved_at' => now(),
        ]);
        foreach ($types as $type) {
            $job = MarketerJob::firstOrCreate(['key' => $type], ['name_ar' => $type, 'name_en' => $type, 'is_active' => true, 'sort_order' => 1]);
            MarketerMarketerJob::firstOrCreate(['marketer_id' => $marketer->id, 'marketer_job_id' => $job->id]);
        }

        return $marketer;
    }

    private function store(Marketer $marketer, array $data)
    {
        return $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.marketers.category-commissions.store', $marketer), $data + ['scope' => 'products']);
    }

    public function test_influencer_gets_a_fixed_amount_only_and_a_percentage_is_rejected(): void
    {
        $m = $this->marketerOf('influencer');

        $this->store($m, ['commission_rate' => 5])->assertSessionHasErrors('commission_rate');
        $this->store($m, [])->assertSessionHasErrors('commission_flat_amount');
        $this->assertSame(0, MarketerCommissionRule::where('marketer_id', $m->id)->count());

        $this->store($m, ['commission_flat_amount' => 200])->assertSessionHasNoErrors();
        $rule = MarketerCommissionRule::where('marketer_id', $m->id)->sole();
        $this->assertSame('influencer', $rule->marketer_type);
        $this->assertSame('fixed', $rule->commission_mode);
        $this->assertEquals(200, $rule->commission_flat_amount);
        $this->assertEquals(0, $rule->commission_rate);
    }

    public function test_affiliate_gets_a_percentage_only_and_a_fixed_amount_is_rejected(): void
    {
        $m = $this->marketerOf('affiliate');

        $this->store($m, ['commission_flat_amount' => 5])->assertSessionHasErrors('commission_flat_amount');
        $this->store($m, [])->assertSessionHasErrors('commission_rate');

        $this->store($m, ['commission_rate' => 8])->assertSessionHasNoErrors();
        $rule = MarketerCommissionRule::where('marketer_id', $m->id)->sole();
        $this->assertSame(['affiliate', 'percentage'], [$rule->marketer_type, $rule->commission_mode]);
        $this->assertNull($rule->commission_flat_amount);
    }

    public function test_broker_may_use_fixed_percentage_or_both(): void
    {
        $m = $this->marketerOf('broker');

        $this->store($m, [])->assertSessionHasErrors('commission_rate');
        $this->store($m, ['commission_flat_amount' => 15, 'commission_rate' => 5])->assertSessionHasNoErrors();
        $rule = MarketerCommissionRule::where('marketer_id', $m->id)->sole();
        $this->assertSame(['broker', 'both'], [$rule->marketer_type, $rule->commission_mode]);
        $this->assertEquals(65.0, $rule->resolveAmount(1000));

        $this->store($m, ['commission_flat_amount' => 7])->assertSessionHasNoErrors();
        $this->assertSame('fixed', $rule->fresh()->commission_mode);
    }

    public function test_the_fee_override_is_stored_and_empty_means_default(): void
    {
        $m = $this->marketerOf('affiliate');

        $this->store($m, ['commission_rate' => 8])->assertSessionHasNoErrors();
        $rule = MarketerCommissionRule::where('marketer_id', $m->id)->sole();
        $this->assertNull($rule->fee_percentage);
        $this->assertNull($rule->fee_fixed);

        $this->store($m, ['commission_rate' => 8, 'fee_percentage' => 12.5, 'fee_fixed' => 2])->assertSessionHasNoErrors();
        $rule->refresh();
        $this->assertEquals(12.5, (float) $rule->fee_percentage);
        $this->assertEquals(2, $rule->fee_fixed);
    }

    public function test_a_type_the_marketer_does_not_hold_is_rejected(): void
    {
        $m = $this->marketerOf('affiliate');

        $this->store($m, ['marketer_type' => 'influencer', 'commission_flat_amount' => 10])->assertSessionHasErrors('marketer_type');
        $this->assertSame(0, MarketerCommissionRule::where('marketer_id', $m->id)->count());
    }

    public function test_store_per_scope_and_category_validation(): void
    {
        $tc = TravelCategory::create(['name_en' => 'T', 'name_ar' => 'س', 'slug' => 't-'.uniqid(), 'is_active' => true]);
        $m = $this->marketerOf('broker');

        $this->store($m, ['scope' => 'travel', 'category_id' => $tc->id, 'commission_rate' => 3])->assertSessionHasNoErrors();
        $this->assertSame(1, MarketerCommissionRule::where('marketer_id', $m->id)->where('scope', 'travel')->count());

        $m2 = $this->marketerOf('broker');
        $this->store($m2, ['scope' => 'products', 'category_id' => $tc->id, 'commission_rate' => 3])->assertSessionHasErrors('category_id');
        $this->assertSame(0, MarketerCommissionRule::where('marketer_id', $m2->id)->count());
    }

    public function test_delete_removes_the_rule(): void
    {
        $m = $this->marketerOf('affiliate');
        $this->store($m, ['commission_rate' => 4]);
        $rule = MarketerCommissionRule::where('marketer_id', $m->id)->sole();

        $this->actingAs(Admin::first(), 'admin')
            ->delete(route('admin.marketers.category-commissions.destroy', [$m, $rule->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, MarketerCommissionRule::count());
    }

    public function test_profile_page_shows_the_heading_for_the_type_and_the_currency(): void
    {
        $m = $this->marketerOf('influencer');
        $country = Country::factory()->create(['currency_code' => 'EGP']);
        $m->update(['country_id' => $country->id]);
        $this->store($m, ['commission_flat_amount' => 5]);

        $this->actingAs(Admin::first(), 'admin')->get(route('admin.marketers.show', $m))
            ->assertOk()
            ->assertSee(__('marketer_commissions.heading_influencer'))
            ->assertSee('5 EGP');
    }
}
