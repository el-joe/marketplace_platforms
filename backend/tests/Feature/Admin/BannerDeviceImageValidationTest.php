<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Banner;
use App\Services\BannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A banner cannot be saved without an image for every device it targets (it would be hidden there).
 */
class BannerDeviceImageValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function admin(): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach (['banners.view', 'banners.create', 'banners.edit'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['banners.view', 'banners.create', 'banners.edit']);

        return $admin;
    }

    /** @param array<string, mixed> $extra */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Device banner',
            'placement_code' => 'marketers_list_hero',
            'is_global' => 1,
            'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'status' => 'active',
            'audience' => 'all',
        ], $extra);
    }

    public function test_a_banner_for_all_devices_needs_a_desktop_and_a_mobile_image(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.banners.store'), $this->payload([
                'device_target' => 'all',
                'desktop_image_ar' => UploadedFile::fake()->image('d.png'),
            ]))
            ->assertUnprocessable()
            ->assertJsonMissingValidationErrors('desktop_image_en')
            ->assertJsonValidationErrors(['mobile_image_en' => __('admin.banners.image_required_mobile')]);

        $this->assertSame(0, Banner::count());
    }

    public function test_a_mobile_only_banner_saves_with_only_a_mobile_image(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.banners.store'), $this->payload([
                'device_target' => 'mobile',
                'mobile_image_en' => UploadedFile::fake()->image('m.png'),
            ]))
            ->assertOk();

        $this->assertSame(1, Banner::count());
    }

    public function test_a_desktop_only_banner_without_a_desktop_image_is_refused_in_the_panel_locale(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->admin(), 'admin')
            ->withHeader('Accept-Language', 'en')
            ->postJson(route('admin.banners.store'), $this->payload([
                'device_target' => 'desktop',
                'mobile_image_en' => UploadedFile::fake()->image('m.png'),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('desktop_image_en');
    }

    public function test_updating_counts_the_images_already_saved_and_a_new_target_needs_its_own(): void
    {
        $admin = $this->admin();
        $banner = Banner::factory()->create(['placement_code' => 'marketers_list_hero', 'device_target' => 'mobile']);
        app(BannerService::class)->storeImage(UploadedFile::fake()->image('m.png'), $banner, 'mobile_ar');

        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.banners.update', $banner), $this->payload(['device_target' => 'mobile']))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.banners.update', $banner), $this->payload(['device_target' => 'all']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('desktop_image_en');
    }
}
