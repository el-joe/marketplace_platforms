<?php

namespace Tests\Feature\Admin;

use App\Enums\PortalContentType;
use App\Models\Admin;
use App\Models\PortalContent;
use Database\Seeders\Concerns\SyncsPortalContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PortalContentTest extends TestCase
{
    use RefreshDatabase;

    private function syncer(): object
    {
        return new class
        {
            use SyncsPortalContent {
                syncPortalContent as public;
            }
        };
    }

    private function admin(): Admin
    {
        foreach (['vendors.assigned_only', 'portal_content.view', 'portal_content.edit'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['portal_content.view', 'portal_content.edit']);

        return $admin;
    }

    public function test_sync_creates_refreshes_and_preserves_admin_edits(): void
    {
        $admin = Admin::factory()->create();
        PortalContent::create([
            'page_key' => 'zz_test', 'block_key' => 'hero', 'field_key' => 'title', 'type' => 'text',
            'value_en' => 'Sell on Nawy', 'value_ar' => 'بيع على ناوي',
        ]);
        PortalContent::create([
            'page_key' => 'zz_test', 'block_key' => 'hero', 'field_key' => 'cta', 'type' => 'text',
            'value_en' => 'Admin copy', 'value_ar' => 'نص المشرف', 'updated_by_admin_id' => $admin->id,
        ]);

        $stats = $this->syncer()->syncPortalContent([
            ['zz_test', 'hero', 'title', 'text', 'Sell on Nawy', 'بيع على ناوي', null, 1],
            ['zz_test', 'hero', 'cta', 'link', 'Sign Up Now', 'سجل الآن', '/register', 2],
            ['zz_test', 'hero', 'photo', 'image', 'Hero', 'البطل', '/images/nawy_hero.jpg', 3],
        ]);

        $this->assertSame(['created' => 1, 'refreshed' => 1, 'preserved' => 1], $stats);

        $title = PortalContent::where('page_key', 'zz_test')->where('field_key', 'title')->first();
        $this->assertSame('Sell on Nawy', $title->value_en);

        $cta = PortalContent::where('page_key', 'zz_test')->where('field_key', 'cta')->first();
        $this->assertSame('Admin copy', $cta->value_en);
        $this->assertNull($cta->value_url);
        $this->assertSame(PortalContentType::Link, $cta->type);
        $this->assertSame(2, $cta->sort_order);

        $this->assertTrue(PortalContent::where('page_key', 'zz_test')->where('field_key', 'photo')->first()->is_active);
    }

    public function test_resolve_url_keeps_site_paths_and_maps_disk_paths(): void
    {
        $this->assertSame('/images/nawy_ui.jpeg', PortalContent::resolveUrl('/images/nawy_ui.jpeg'));
        $this->assertSame('https://cdn.test/a.png', PortalContent::resolveUrl('https://cdn.test/a.png'));
        $this->assertSame('mailto:a@b.c', PortalContent::resolveUrl('mailto:a@b.c'));
        $this->assertSame(Storage::disk('public')->url('portal-content/home/a.png'), PortalContent::resolveUrl('portal-content/home/a.png'));
        $this->assertSame('', PortalContent::resolveUrl(null));
    }

    public function test_portal_image_falls_back_and_reads_site_path_rows(): void
    {
        $this->assertSame('https://fallback.test/x.png', portal_image('zz_test', 'hero', 'photo', 'https://fallback.test/x.png', 'A', 'ب')['src']);

        PortalContent::create([
            'page_key' => 'zz_test', 'block_key' => 'hero', 'field_key' => 'photo', 'type' => 'image',
            'value_en' => 'Hero', 'value_ar' => 'البطل', 'value_url' => '/images/nawy_hero.jpg',
        ]);
        PortalContent::flush('zz_test');

        $this->assertSame('/images/nawy_hero.jpg', portal_image('zz_test', 'hero', 'photo', 'https://fallback.test/x.png')['src']);
    }

    public function test_admin_can_set_image_url_reset_it_and_upload_svg(): void
    {
        Storage::fake('public');
        $row = PortalContent::create([
            'page_key' => 'zz_test', 'block_key' => 'logo', 'field_key' => 'image', 'type' => 'image',
            'value_en' => 'Nawy', 'value_ar' => 'Nawy', 'value_url' => '/images/nawy_logo_transparent.png',
        ]);
        $admin = $this->admin();
        $save = fn (array $field) => $this->actingAs($admin, 'admin')
            ->post(route('admin.portal-content.save', 'zz_test'), ['fields' => [$row->id => $field + ['value_en' => 'Nawy', 'value_ar' => 'Nawy', 'is_active' => '1']]])
            ->assertRedirect();

        $save(['value_url' => 'https://cdn.test/logo.png']);
        $this->assertSame('https://cdn.test/logo.png', $row->fresh()->value_url);
        $this->assertSame($admin->id, $row->fresh()->updated_by_admin_id);

        $save(['value_url' => 'https://cdn.test/logo.png', 'reset_image' => '1']);
        $this->assertNull($row->fresh()->value_url);

        $save(['value_file' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')]);
        $this->assertStringStartsWith('portal-content/zz_test/', $row->fresh()->value_url);
        Storage::disk('public')->assertExists($row->fresh()->value_url);
    }

    public function test_seed_data_rows_are_unique_and_well_formed(): void
    {
        $rows = require database_path('seeders/data/portal_content.php');
        $types = array_column(PortalContentType::cases(), 'value');

        $keys = array_map(fn (array $r) => "{$r[0]}.{$r[1]}.{$r[2]}", $rows);
        $this->assertSame(count($keys), count(array_unique($keys)));

        foreach ($rows as $r) {
            $this->assertCount(8, $r);
            $this->assertContains($r[3], $types, implode('.', array_slice($r, 0, 3)));
        }
    }
}
