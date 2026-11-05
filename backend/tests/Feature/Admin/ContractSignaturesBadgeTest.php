<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Services\NavigationService;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Support\ContractDocumentFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class ContractSignaturesBadgeTest extends TestCase
{
    use ContractDocumentFixtures;
    use RefreshDatabase;

    private Admin $admin;

    private ?MarketplaceScenario $scenario = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakePrivateDisk();

        foreach (['vendors.view', 'vendors.assigned_only', 'classifieds.view'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $this->admin = Admin::factory()->create(['contracts_seen_at' => now()->subHour()]);
        $this->admin->givePermissionTo(['vendors.view', 'vendors.assigned_only', 'classifieds.view']);
    }

    private function badge(): ?int
    {
        $this->actingAs($this->admin, 'admin');

        foreach (app(NavigationService::class)->adminNavigation() as $group) {
            foreach ($group['items'] as $item) {
                if (($item['route'] ?? null) === 'admin.contracts.signatures.index') {
                    return $item['badge'];
                }
            }
        }

        $this->fail('The contract signatures item is missing from the sidebar.');
    }

    private function sign(string $method = 'click_wrap', ?object $template = null): void
    {
        // One vendor signs several times; the scenario inserts fixed rows (country iso code), so build it once.
        $this->scenario ??= MarketplaceScenario::make()->build();
        $scenario = $this->scenario;
        $template ??= $this->platformFileTemplate();
        app(SellerAgreementService::class)->sign($scenario->vendor, null, $template, 'ar', 'Owner', '127.0.0.1', 'phpunit', $method);
    }

    public function test_no_badge_until_a_vendor_signs_then_the_count_clears_when_the_page_is_opened(): void
    {
        $this->assertNull($this->badge());

        $this->sign('registration');
        Cache::flush();
        $this->assertSame(1, $this->badge());

        $this->actingAs($this->admin, 'admin')->get(route('admin.contracts.signatures.index'))->assertOk();

        $this->assertNotNull($this->admin->fresh()->contracts_seen_at);
        $this->assertNull($this->badge());

        // timestamps have second precision: sign after the visit, not within the same second
        $this->travel(2)->seconds();
        $this->sign('click_wrap', $this->platformFileTemplate(version: 2, published: false));
        Cache::flush();
        $this->assertSame(1, $this->badge(), 'only signatures after the last visit count');
    }

    public function test_signatures_made_before_the_last_visit_and_automatic_acceptances_are_not_counted(): void
    {
        $template = $this->platformFileTemplate();
        $this->sign('registration', $template);
        $this->admin->forceFill(['contracts_seen_at' => now()->addMinute()])->save();
        $this->sign('implicit', $template);
        Cache::flush();

        $this->assertNull($this->badge());
    }
}
