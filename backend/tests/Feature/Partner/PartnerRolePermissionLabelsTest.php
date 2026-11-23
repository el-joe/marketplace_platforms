<?php

namespace Tests\Feature\Partner;

use App\Models\VendorAdmin;
use Database\Seeders\TravelAgencyPermissionSeeder;
use Database\Seeders\VendorPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * The seller panel and travel-agency role forms listed permissions as English ucwords
 * ("Pricing Edit", "Invoices Export") in the Arabic panel; they now use lang/{ar,en}/permissions.php.
 */
class PartnerRolePermissionLabelsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: class-string, 1: string}> */
    public static function guards(): array
    {
        return [
            'seller panel' => [VendorPermissionSeeder::class, 'vendor'],
            'travel agency' => [TravelAgencyPermissionSeeder::class, 'travel_agency'],
        ];
    }

    #[DataProvider('guards')]
    public function test_every_permission_of_the_guard_has_translated_group_and_label(string $seeder, string $guard): void
    {
        $this->seed($seeder);
        $permissions = Permission::where('guard_name', $guard)->pluck('name');
        $this->assertNotEmpty($permissions);

        foreach (['en', 'ar'] as $locale) {
            $section = (array) trans('permissions.guards', [], $locale)[$guard];
            $groups = $section['groups'];
            $labels = $section['permissions'] + trans('permissions.actions', [], $locale);

            $untranslated = $permissions->reject(function (string $name) use ($groups, $labels) {
                [$prefix, $action] = explode('.', $name, 2);

                return isset($groups[$prefix]) && (isset($labels[$name]) || isset($labels[$action]));
            })->values()->all();

            $this->assertSame([], $untranslated, "{$guard} permissions without a {$locale} label");
        }
    }

    public function test_seller_role_form_lists_arabic_labels_in_the_arabic_panel(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->vendor->forceFill(['onboarding_completed_at' => now()])->save();
        foreach (['roles.view', 'listings.pricing.edit', 'finance.invoices.export'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']);
        }
        $admin = VendorAdmin::create([
            'vendor_id' => $scenario->vendor->id, 'name' => 'V', 'email' => 'roles-'.Str::random(8).'@example.test',
            'password' => 'password', 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        $admin->givePermissionTo('roles.view');

        $groups = collect($this->actingAs($admin, 'vendor')
            ->withSession(['locale_override' => 'ar'])
            ->getJson(route('partner.roles.permissions'))
            ->assertOk()
            ->json('data'))->keyBy('key');

        $this->assertSame('العروض', $groups['listings']['label']);
        $this->assertSame('تعديل الأسعار', collect($groups['listings']['permissions'])->firstWhere('name', 'listings.pricing.edit')['label']);
        $this->assertSame('تصدير الفواتير', collect($groups['finance']['permissions'])->firstWhere('name', 'finance.invoices.export')['label']);
    }
}
