<?php

namespace Tests\Feature\Timezones;

use App\Http\Middleware\ConvertAdminDateTimeInputs;
use App\Models\Admin;
use App\Models\Country;
use App\Models\CurrencyExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * End-to-end: what an admin types (Oman time) is stored in UTC, shown back in Oman time,
 * and the storefront API tells the frontend each country's timezone.
 */
class AdminTimezoneFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.admin_timezone' => 'Asia/Muscat']);
    }

    private function admin(): Admin
    {
        foreach (['settings.view', 'settings.edit', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['settings.view', 'settings.edit']);

        return $admin;
    }

    public function test_admin_date_time_input_is_stored_in_utc(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.currency-exchange-rates.store'), [
                'from_currency_code' => 'OMR',
                'to_currency_code' => 'AED',
                'rate_numerator' => 955,
                'rate_denominator' => 100,
                'effective_at' => '2026-11-12T00:30', // datetime-local, Oman time
            ])
            ->assertRedirect();

        $rate = CurrencyExchangeRate::query()->latest('id')->firstOrFail();
        $this->assertSame('2026-11-11 20:30:00', $rate->effective_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_middleware_converts_nested_and_json_input_but_not_dates(): void
    {
        Route::post('/__tz-echo', fn () => response()->json(request()->all()))
            ->middleware(['web', ConvertAdminDateTimeInputs::class]);

        $this->postJson('/__tz-echo', [
            'starts_at' => '2026-11-12 00:00',
            'config' => ['ends_at' => '2026-11-12T04:15:30'],
            'valid_on' => '2026-11-12',
            'note' => 'Meet at 2026-11-12 00:00',
            'iso' => '2026-11-12T00:00:00Z',
        ])->assertExactJson([
            'starts_at' => '2026-11-11 20:00:00',
            'config' => ['ends_at' => '2026-11-12 00:15:30'],
            'valid_on' => '2026-11-12',
            'note' => 'Meet at 2026-11-12 00:00',
            'iso' => '2026-11-12T00:00:00Z',
        ]);
    }

    public function test_failed_validation_flashes_back_what_the_admin_typed(): void
    {
        Route::post('/__tz-validate', function () {
            request()->validate(['name' => 'required', 'starts_at' => 'required|date']);
        })->middleware(['web', ConvertAdminDateTimeInputs::class]);

        $this->from('/form')->post('/__tz-validate', ['starts_at' => '2026-11-12 00:00'])
            ->assertRedirect('/form')
            ->assertSessionHasErrors('name')
            ->assertSessionHasInput('starts_at', '2026-11-12 00:00');
    }

    public function test_date_picker_prefills_stored_utc_in_oman_time(): void
    {
        $html = $this->withViewErrors([])->blade(
            '<x-form.date-picker name="sale_starts_at" :value="$at" enableTime />',
            ['at' => Carbon::parse('2026-11-11 20:30:00', 'UTC')],
        );

        $html->assertSee('value="2026-11-12 00:30"', false);
        $html->assertSee('GMT+04:00');
    }

    public function test_storefront_countries_api_exposes_each_country_timezone(): void
    {
        $country = Country::factory()->create([
            'site_code' => 'eg-'.Str::lower(Str::random(5)),
            'timezone' => 'Africa/Cairo',
            'is_active' => true,
            'is_launched' => true,
        ]);

        $this->getJson('/api/customer/v1/countries')
            ->assertOk()
            ->assertJsonFragment(['site_code' => $country->site_code, 'timezone' => 'Africa/Cairo']);
    }
}
