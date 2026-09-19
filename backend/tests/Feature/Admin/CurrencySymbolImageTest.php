<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CurrencySymbolImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'admin']);
        $a = Admin::factory()->create();
        Permission::firstOrCreate(['name' => 'countries.view', 'guard_name' => 'admin']);
        $a->givePermissionTo(['settings.edit', 'countries.view']);

        return $a;
    }

    private function code(): string
    {
        Currency::query()->firstOrCreate(['code' => 'OMR'], ['name' => 'Omani Rial', 'symbol' => 'RO', 'is_active' => true]);

        return 'OMR';
    }

    public function test_valid_svg_accepted_and_stored(): void
    {
        Storage::fake('public');
        $code = $this->code();
        $svg = UploadedFile::fake()->createWithContent('s.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1"/></svg>');
        $r = $this->actingAs($this->admin(), 'admin')->postJson(route('admin.currencies.symbol-image.upload', $code), ['symbol_image' => $svg]);
        $r->assertOk()->assertJsonPath('success', true);
        $path = Currency::find($code)->symbol_image;
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.svg', $path);
    }

    public function test_malicious_svg_is_sanitised(): void
    {
        Storage::fake('public');
        $code = $this->code();
        $evil = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script>'
            .'<a href="javascript:alert(1)"><rect onclick="x()" width="1" height="1"/></a>'
            .'<image href="https://evil.test/x.png"/><foreignObject><div/></foreignObject></svg>';
        $this->actingAs($this->admin(), 'admin')->postJson(route('admin.currencies.symbol-image.upload', $code), [
            'symbol_image' => UploadedFile::fake()->createWithContent('e.svg', $evil),
        ])->assertOk();
        $stored = strtolower(Storage::disk('public')->get(Currency::find($code)->symbol_image));
        foreach (['<script', 'onload', 'onclick', 'javascript:', 'evil.test', 'foreignobject'] as $bad) {
            $this->assertStringNotContainsString($bad, $stored, $bad);
        }
    }

    public function test_non_image_and_non_svg_content_rejected_and_auth_required(): void
    {
        Storage::fake('public');
        $code = $this->code();
        $this->actingAs($this->admin(), 'admin')->postJson(route('admin.currencies.symbol-image.upload', $code), [
            'symbol_image' => UploadedFile::fake()->createWithContent('n.svg', 'just text'),
        ])->assertStatus(422);
        $this->actingAs($this->admin(), 'admin')->postJson(route('admin.currencies.symbol-image.upload', $code), [
            'symbol_image' => UploadedFile::fake()->create('x.php', 1, 'application/x-php'),
        ])->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->postJson(route('admin.currencies.symbol-image.upload', $code), [])->assertStatus(401);
    }
}
