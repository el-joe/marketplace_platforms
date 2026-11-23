<?php

namespace Tests\Feature\Admin;

use App\Enums\CommissionVertical;
use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\MarketerDefaultCommission;
use App\Services\Marketer\MarketerDefaultCommissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** WS12 #25: per-classified-category marketer commission settings, stored in marketer_default_commissions. */
class ClassifiedCategoryCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['vendors.assigned_only', 'classifieds.view', 'marketer_fee_settings.edit'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $this->country = Country::factory()->create(['is_active' => true]);

        // Country default for the whole classified vertical (Marketer Settings).
        MarketerDefaultCommission::create([
            'country_id' => $this->country->id, 'category_type' => 'classified', 'category_id' => null,
            'influencer_fixed_amount' => 100, 'commission_fee_percentage' => 10, 'commission_fee_fixed' => 2,
        ]);
    }

    private function admin(bool $canEdit = true): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($canEdit ? ['classifieds.view', 'marketer_fee_settings.edit'] : ['classifieds.view']);

        return $admin;
    }

    private function resolver(): MarketerDefaultCommissionResolver
    {
        return new MarketerDefaultCommissionResolver;
    }

    public function test_a_category_overrides_only_the_fields_it_fills_and_inherits_the_rest(): void
    {
        $section = ClassifiedCategory::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.classifieds.categories.commission.edit', $section))
            ->assertOk()
            ->assertSee(__('classified_commission.help.platform'));

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.classifieds.categories.commission.update', $section), [
                'settings' => [$this->country->id => ['influencer_fixed_amount' => '', 'commission_fee_percentage' => '15', 'commission_fee_fixed' => '']],
            ])
            ->assertRedirect(route('admin.classifieds.categories.commission.edit', $section))
            ->assertSessionHasNoErrors();

        $row = MarketerDefaultCommission::where('category_id', $section->id)->sole();
        $this->assertNull($row->influencer_fixed_amount);
        $this->assertNull($row->commission_fee_fixed);

        $this->assertEquals(100, $this->resolver()->influencerFixedAmount($this->country->id, CommissionVertical::Classified, $section->id));
        $fee = $this->resolver()->commissionFee($this->country->id, CommissionVertical::Classified, $section->id);
        $this->assertEquals(15, $fee['percentage']);
        $this->assertEquals(2, $fee['fixed']);
        $this->assertSame('category', $fee['source']);
    }

    public function test_a_new_sub_category_inherits_its_sections_settings_and_an_explicit_zero_is_kept(): void
    {
        $section = ClassifiedCategory::factory()->create();
        $child = ClassifiedCategory::factory()->create(['parent_id' => $section->id]);

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.classifieds.categories.commission.update', $section), [
                'settings' => [$this->country->id => ['influencer_fixed_amount' => '0', 'commission_fee_percentage' => '5', 'commission_fee_fixed' => '1']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(0, $this->resolver()->influencerFixedAmount($this->country->id, CommissionVertical::Classified, $child->id));
        $this->assertSame(['percentage' => 5.0, 'fixed' => 1.0, 'source' => 'category'], $this->resolver()->commissionFee($this->country->id, CommissionVertical::Classified, $child->id));

        // A category without any settings, anywhere, falls back to the country default.
        $other = ClassifiedCategory::factory()->create();
        $this->assertEquals(100, $this->resolver()->influencerFixedAmount($this->country->id, CommissionVertical::Classified, $other->id));
    }

    public function test_clearing_every_field_removes_the_override(): void
    {
        $section = ClassifiedCategory::factory()->create();
        MarketerDefaultCommission::create([
            'country_id' => $this->country->id, 'category_type' => 'classified', 'category_id' => $section->id, 'influencer_fixed_amount' => 50,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.classifieds.categories.commission.update', $section), [
                'settings' => [$this->country->id => ['influencer_fixed_amount' => '', 'commission_fee_percentage' => '', 'commission_fee_fixed' => '']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, MarketerDefaultCommission::where('category_id', $section->id)->count());
    }

    public function test_saving_needs_the_marketer_fee_permission_and_valid_values(): void
    {
        $section = ClassifiedCategory::factory()->create();

        $this->actingAs($this->admin(canEdit: false), 'admin')
            ->put(route('admin.classifieds.categories.commission.update', $section), [
                'settings' => [$this->country->id => ['commission_fee_percentage' => '5']],
            ])
            ->assertForbidden();

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.classifieds.categories.commission.update', $section), [
                'settings' => [$this->country->id => ['commission_fee_percentage' => '120']],
            ])
            ->assertSessionHasErrors("settings.{$this->country->id}.commission_fee_percentage");

        $this->assertSame(0, MarketerDefaultCommission::where('category_id', $section->id)->count());
    }
}
