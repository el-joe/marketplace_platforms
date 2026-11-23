<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Support\PermissionLabels;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Client note #16: the role form listed permission groups and actions in English
 * ("Faqs" — View / Create / Edit / Delete) while the panel was in Arabic.
 */
class PermissionLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_permission_has_arabic_and_english_labels(): void
    {
        $this->seed(PermissionSeeder::class);

        $permissions = Permission::where('guard_name', 'admin')->pluck('name');
        $this->assertNotEmpty($permissions);

        foreach (['en', 'ar'] as $locale) {
            $missing = $permissions->reject(fn (string $name) => PermissionLabels::isTranslated($name, $locale))->values()->all();

            $this->assertSame([], $missing, "Permissions without a {$locale} group/action label in lang/{$locale}/permissions.php");
        }
    }

    public function test_role_form_shows_arabic_permission_labels_in_the_arabic_panel(): void
    {
        foreach (['vendors.assigned_only', 'roles.view', 'roles.create', 'faqs.view', 'faqs.create'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $admin = Admin::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']));
        $admin->givePermissionTo(['roles.view', 'roles.create']);

        $html = $this->withSession(['locale' => 'ar'])
            ->actingAs($admin, 'admin')
            ->get(route('admin.roles.create'))
            ->assertOk()
            ->getContent();

        $groups = $this->groupsFromHtml($html);
        $faqs = collect($groups)->firstWhere('key', 'faqs');

        $this->assertSame('المحتوى — الأسئلة الشائعة', $faqs['label']);
        $this->assertEqualsCanonicalizing(['عرض', 'إنشاء'], array_column($faqs['permissions'], 'label'));
        $this->assertStringContainsString('Create', collect($faqs['permissions'])->firstWhere('name', 'faqs.create')['search_text']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupsFromHtml(string $html): array
    {
        $this->assertSame(1, preg_match('/groups: (\[.*?\]),\s*query:/s', html_entity_decode($html, ENT_QUOTES), $matches));

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
