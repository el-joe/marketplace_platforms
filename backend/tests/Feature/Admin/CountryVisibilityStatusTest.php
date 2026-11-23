<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Client item #32: Admin → Countries shows the real visibility state (Draft / Live / Hidden (was live)) and
 * whether vendors may use the country, a launched country can be moved back to Draft (countries.launch),
 * and the "accept vendor registrations / listings before launch" switch is saved from the edit form (#23).
 */
class CountryVisibilityStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['countries.view', 'countries.edit', 'countries.launch', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function adminWith(array $permissions): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    /**
     * @return array<string, array<string, mixed>> datatable rows keyed by country id
     */
    private function datatableRows(Admin $admin): array
    {
        $rows = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.countries.datatable'), ['draw' => 1, 'start' => 0, 'length' => 100])
            ->assertOk()
            ->json('data');

        return collect($rows)->keyBy('id')->all();
    }

    public function test_list_reports_draft_live_and_hidden_distinctly_with_vendor_access(): void
    {
        $draft = Country::factory()->create(['is_active' => true, 'is_launched' => false]);
        $openDraft = Country::factory()->create(['is_active' => true, 'is_launched' => false, 'accepts_vendors_before_launch' => true]);
        $live = Country::factory()->create(['is_active' => true, 'is_launched' => true]);
        $hidden = Country::factory()->create(['is_active' => false, 'is_launched' => true]);

        $rows = $this->datatableRows($this->adminWith(['countries.view']));

        $this->assertSame([Country::STATUS_DRAFT, false], [$rows[$draft->id]['status'], $rows[$draft->id]['vendors_open']]);
        $this->assertSame([Country::STATUS_DRAFT, true], [$rows[$openDraft->id]['status'], $rows[$openDraft->id]['vendors_open']]);
        $this->assertSame([Country::STATUS_LIVE, true], [$rows[$live->id]['status'], $rows[$live->id]['vendors_open']]);
        $this->assertSame([Country::STATUS_HIDDEN, false], [$rows[$hidden->id]['status'], $rows[$hidden->id]['vendors_open']]);
    }

    public function test_index_page_renders_the_status_legend(): void
    {
        $this->actingAs($this->adminWith(['countries.view']), 'admin')
            ->get(route('admin.countries.index'))
            ->assertOk()
            ->assertSee(__('admin.geography.status_legend_hidden'))
            ->assertSee(__('admin.geography.status_hidden'));
    }

    public function test_revert_to_draft_hides_a_launched_country_and_logs_it(): void
    {
        $country = Country::factory()->create(['is_active' => false, 'is_launched' => true, 'launched_at' => now()->subWeek()]);
        $admin = $this->adminWith(['countries.view', 'countries.launch']);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.countries.revert-to-draft', $country->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $country->refresh();
        $this->assertSame(Country::STATUS_DRAFT, $country->visibilityStatus());
        $this->assertTrue($country->is_active);
        $this->assertNull($country->launched_at);
        $this->assertFalse(Country::availableForVendors()->whereKey($country->id)->exists());

        $log = DB::table('activity_log')->where('subject_id', $country->id)->where('event', 'reverted_to_draft')->sole();
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertFalse(json_decode($log->properties, true)['was_active']);
    }

    public function test_edit_page_offers_revert_to_draft_only_to_admins_who_can_launch(): void
    {
        $country = Country::factory()->create(['is_active' => false, 'is_launched' => true]);
        $url = route('admin.countries.revert-to-draft', $country->id);

        $this->actingAs($this->adminWith(['countries.view', 'countries.launch']), 'admin')
            ->get(route('admin.countries.edit', $country->id))
            ->assertOk()
            ->assertSee(__('admin.geography.status_hidden'))
            ->assertSee($url, false);

        $this->actingAs($this->adminWith(['countries.view', 'countries.edit']), 'admin')
            ->get(route('admin.countries.edit', $country->id))
            ->assertOk()
            ->assertDontSee($url, false);
    }

    public function test_revert_to_draft_requires_the_launch_permission(): void
    {
        $country = Country::factory()->create(['is_active' => true, 'is_launched' => true]);

        $this->actingAs($this->adminWith(['countries.view', 'countries.edit']), 'admin')
            ->postJson(route('admin.countries.revert-to-draft', $country->id))
            ->assertForbidden();

        $this->assertTrue($country->fresh()->is_launched);
    }

    public function test_revert_to_draft_refuses_a_country_that_already_is_a_draft(): void
    {
        $country = Country::factory()->create(['is_active' => true, 'is_launched' => false]);

        $this->actingAs($this->adminWith(['countries.view', 'countries.launch']), 'admin')
            ->postJson(route('admin.countries.revert-to-draft', $country->id))
            ->assertUnprocessable();

        $this->assertSame(0, DB::table('activity_log')->where('subject_id', $country->id)->count());
    }

    public function test_edit_form_saves_and_clears_the_vendor_access_switch(): void
    {
        $country = Country::factory()->create(['is_active' => true, 'is_launched' => false, 'vat_rate' => 5]);
        $admin = $this->adminWith(['countries.view', 'countries.edit']);
        $form = [
            'iso_code_2' => $country->iso_code_2,
            'name_en' => $country->name_en,
            'name_ar' => $country->name_ar,
            'vat_rate' => '5',
        ];

        $this->actingAs($admin, 'admin')
            ->put(route('admin.countries.update', $country->id), $form + ['accepts_vendors_before_launch' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($country->fresh()->accepts_vendors_before_launch);
        $this->assertSame(1, DB::table('activity_log')->where('subject_id', $country->id)->where('description', 'vendor_access_before_launch_changed')->count());

        // An unchecked toggle is not submitted at all.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.countries.update', $country->id), $form)
            ->assertSessionHasNoErrors();
        $this->assertFalse($country->fresh()->accepts_vendors_before_launch);
    }
}
