<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Banner;
use App\Services\BannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression for the −4h schedule drift: opening the edit form and saving without touching the
 * schedule must keep starts_at / ends_at identical, no matter how many times it is saved.
 */
class BannerScheduleRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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

    private function inputValue(string $html, string $name): string
    {
        $this->assertMatchesRegularExpression('/<input[^>]*name="'.$name.'"[^>]*>/', $html);
        preg_match('/<input[^>]*name="'.$name.'"[^>]*>/', $html, $input);
        preg_match('/value="([^"]*)"/', $input[0], $value);

        return html_entity_decode($value[1] ?? '');
    }

    /** @return array{starts_at: string, ends_at: string} */
    private function storedSchedule(Banner $banner): array
    {
        return (array) DB::table('banners')->where('id', $banner->id)->first(['starts_at', 'ends_at']);
    }

    public function test_saving_the_edit_form_repeatedly_keeps_the_schedule_unchanged(): void
    {
        $admin = $this->admin();
        // 18 Nov 12:00 and 30 Dec 11:59 Oman time (UTC+4), stored in UTC.
        $banner = Banner::factory()->create([
            'name' => 'Cart banner',
            'placement_code' => 'marketers_list_hero',
            'is_global' => true,
            'starts_at' => '2026-11-18 08:00:00',
            'ends_at' => '2026-12-30 07:59:00',
            'status' => 'inactive',
        ]);
        app(BannerService::class)->storeImage(UploadedFile::fake()->image('desktop.png'), $banner, 'desktop_en');
        app(BannerService::class)->storeImage(UploadedFile::fake()->image('mobile.png'), $banner, 'mobile_en');
        $original = $this->storedSchedule($banner);

        for ($save = 1; $save <= 3; $save++) {
            $html = $this->actingAs($admin, 'admin')->get(route('admin.banners.edit', $banner))->assertOk()->getContent();

            $startsAt = $this->inputValue($html, 'starts_at');
            $endsAt = $this->inputValue($html, 'ends_at');
            $this->assertSame('2026-11-18T12:00', $startsAt, "edit form shows Oman time (save {$save})");
            $this->assertSame('2026-12-30T11:59', $endsAt, "edit form shows Oman time (save {$save})");

            $this->actingAs($admin, 'admin')->put(route('admin.banners.update', $banner), [
                'name' => 'Cart banner',
                'placement_code' => 'marketers_list_hero',
                'is_global' => 1,
                'gender_scope' => 'all',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'inactive',
                'device_target' => 'all',
                'audience' => 'all',
            ], ['Accept' => 'application/json'])->assertOk();

            $this->assertEquals($original, $this->storedSchedule($banner), "schedule drifted after save {$save}");
        }
    }
}
