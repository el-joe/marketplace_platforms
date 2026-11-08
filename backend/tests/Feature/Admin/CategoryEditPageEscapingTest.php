<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA "اخر المشاكل" #4: names with an apostrophe were escaped twice in the admin breadcrumb and browser title
 * ("Women&#039;s Abayas"), because both @section('title', …) and the topbar already escape their values.
 */
class CategoryEditPageEscapingTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_name_with_an_apostrophe_is_escaped_once_in_title_and_breadcrumb(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']));
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']));

        $category = Category::create([
            'id' => (string) Str::uuid(), 'name_en' => "Women's Abayas", 'name_ar' => 'عبايات حريمي',
            'slug' => 'womens-abayas-'.Str::lower(Str::random(5)), 'commission_rate' => 5, 'is_active' => true, 'is_visible' => true,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.categories.edit', $category->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('&amp;#039;', $html);
        $this->assertMatchesRegularExpression('/<title>[^<]*Women&#039;s Abayas/', $html);
        $this->assertStringContainsString('<span class="text-gray-900 font-medium">Women&#039;s Abayas</span>', $html);
    }
}
