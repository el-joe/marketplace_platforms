<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CartItem;
use App\Models\MarketerContract;
use App\Models\MarketerContractAcceptance;
use App\Models\MarketerContractVersion;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerContractAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): MarketplaceScenario
    {
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $s;
    }

    private function admin(): Admin
    {
        foreach (['vendors.assigned_only', 'marketers.view', 'marketers.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['marketers.view', 'marketers.manage']);

        return $admin;
    }

    private function version(MarketerContract $c, int $n, bool $active): MarketerContractVersion
    {
        return MarketerContractVersion::create([
            'marketer_contract_id' => $c->id, 'version_number' => $n, 'content_type' => 'text',
            'text_content' => "Terms v{$n}", 'title_en' => "T{$n}", 'is_active' => $active,
        ]);
    }

    private function contractWithV1(MarketplaceScenario $s): array
    {
        $c = MarketerContract::create(['marketer_id' => $s->marketer->id, 'current_version' => 1, 'is_required' => true]);

        return [$c, $this->version($c, 1, true)];
    }

    private function base(MarketplaceScenario $s): string
    {
        return "/api/customer/v1/{$s->country->site_code}";
    }

    private function fillCart(MarketplaceScenario $s): void
    {
        $this->actingAs($s->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $s->marketerListing->id,
            'quantity' => 1, 'unit_price' => (int) $s->marketerListing->price, 'added_at' => now(),
        ]);
    }

    private function place(MarketplaceScenario $s, array $extra = [])
    {
        return $this->postJson($this->base($s).'/checkout/place-order', array_merge([
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ], $extra));
    }

    public function test_admin_upload_creates_v1_then_v2_keeping_v1_inactive(): void
    {
        Storage::fake('local');
        Notification::fake();
        $s = $this->scenario();
        $admin = $this->admin();
        $url = route('admin.marketers.contract.upload', $s->marketer->id);

        $this->actingAs($admin, 'admin')->post($url, ['content_type' => 'text', 'text_content' => 'one'])->assertSessionHasNoErrors();
        $this->actingAs($admin, 'admin')->post($url, [
            'content_type' => 'pdf', 'contract_file' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $c = MarketerContract::where('marketer_id', $s->marketer->id)->firstOrFail();
        $this->assertSame(2, $c->current_version);
        $v1 = $c->versions()->where('version_number', 1)->first();
        $v2 = $c->versions()->where('version_number', 2)->first();
        $this->assertFalse($v1->is_active);
        $this->assertTrue($v2->is_active);
        $this->assertNotNull($v2->file_url);
        Storage::disk('local')->assertExists($v2->file_url);
    }

    public function test_customer_show_returns_active_version_only(): void
    {
        $s = $this->scenario();
        [$c, $v1] = $this->contractWithV1($s);
        $v1->update(['is_active' => false]);
        $v2 = $this->version($c, 2, true);
        $c->update(['current_version' => 2]);

        $this->getJson($this->base($s)."/marketers/{$s->marketer->id}/contract")
            ->assertOk()->assertJsonPath('contract.version_id', $v2->id)->assertJsonPath('contract.version_number', 2);
    }

    public function test_accept_stores_ip_ua_marketer_and_version(): void
    {
        $s = $this->scenario();
        [, $v1] = $this->contractWithV1($s);
        $this->actingAs($s->customer, 'customer');

        $r = $this->withHeaders(['User-Agent' => 'AuditTest/1.0'])
            ->postJson($this->base($s)."/marketers/{$s->marketer->id}/contract/accept", ['version_id' => $v1->id]);
        $r->assertStatus(201);

        $a = MarketerContractAcceptance::findOrFail($r->json('acceptance_id'));
        $this->assertSame($s->marketer->id, $a->marketer_id);
        $this->assertSame($v1->id, $a->marketer_contract_version_id);
        $this->assertSame($s->customer->id, $a->customer_id);
        $this->assertSame('AuditTest/1.0', $a->user_agent);
        $this->assertNotNull($a->ip_address);
        $this->assertNotNull($a->accepted_at);
        $this->assertNull($a->order_id);
    }

    public function test_accept_rejects_inactive_and_other_marketer_version(): void
    {
        $s = $this->scenario();
        [$c, $v1] = $this->contractWithV1($s);
        $v1->update(['is_active' => false]);
        $this->version($c, 2, true);
        $this->actingAs($s->customer, 'customer');
        $url = $this->base($s)."/marketers/{$s->marketer->id}/contract/accept";

        $this->postJson($url, ['version_id' => $v1->id])->assertStatus(422);
        $this->postJson($this->base($s).'/marketers/'.Str::uuid().'/contract/accept', ['version_id' => $c->activeVersion->id])->assertStatus(422);
        $this->postJson($this->base($s).'/marketers/not-a-uuid/contract/accept', ['version_id' => $v1->id])->assertStatus(404);
        $this->assertSame(0, MarketerContractAcceptance::count());
    }

    public function test_place_order_blocked_without_acceptance_then_links_it(): void
    {
        Notification::fake();
        $s = $this->scenario();
        [, $v1] = $this->contractWithV1($s);
        $this->fillCart($s);

        $this->place($s)->assertStatus(422);
        $this->assertSame(0, Order::count());

        $acc = $this->postJson($this->base($s)."/marketers/{$s->marketer->id}/contract/accept", ['version_id' => $v1->id])
            ->assertStatus(201)->json('acceptance_id');

        $r = $this->place($s, ['contract_acceptance_ids' => [$acc]]);
        $r->assertStatus(201);
        $order = Order::where('order_number', $r->json('data.order.order_number') ?? $r->json('data.order_number'))->firstOrFail();
        $this->assertSame($acc, $order->marketer_contract_acceptance_id);
        $this->assertSame($order->id, MarketerContractAcceptance::find($acc)->order_id);
    }

    public function test_second_order_requires_and_creates_new_acceptance_row(): void
    {
        Notification::fake();
        $s = $this->scenario();
        [, $v1] = $this->contractWithV1($s);
        $this->fillCart($s);
        $url = $this->base($s)."/marketers/{$s->marketer->id}/contract/accept";

        $a1 = $this->postJson($url, ['version_id' => $v1->id])->json('acceptance_id');
        $this->place($s, ['contract_acceptance_ids' => [$a1]])->assertStatus(201);

        $this->fillCart($s);
        $this->place($s)->assertStatus(422);

        $a2 = $this->postJson($url, ['version_id' => $v1->id])->assertStatus(201)->json('acceptance_id');
        $this->assertNotSame($a1, $a2);
        $this->place($s, ['contract_acceptance_ids' => [$a2]])->assertStatus(201);
        $this->assertSame(2, MarketerContractAcceptance::whereNotNull('order_id')->count());
    }

    public function test_checkout_prepare_reports_gate(): void
    {
        $s = $this->scenario();
        $this->contractWithV1($s);
        $svc = app(\App\Services\MarketerContractGateService::class);
        $this->fillCart($s);
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code)->load('items');

        $this->assertSame([$s->marketer->id], $svc->requiredMarketerIds($cart));
        $this->assertFalse($svc->gates($s->customer, $cart)[0]['accepted']);
    }

    public function test_onboarding_has_accepted_contract_ignores_customer_rows(): void
    {
        $s = $this->scenario();
        [, $v1] = $this->contractWithV1($s);
        MarketerContractAcceptance::create([
            'marketer_contract_version_id' => $v1->id, 'customer_id' => $s->customer->id,
            'marketer_id' => $s->marketer->id, 'accepted_at' => now(),
        ]);
        $this->assertFalse($s->marketer->hasAcceptedContract());

        MarketerContractAcceptance::create([
            'marketer_contract_version_id' => $v1->id, 'marketer_id' => $s->marketer->id, 'accepted_at' => now(),
        ]);
        $this->assertTrue($s->marketer->fresh()->hasAcceptedContract());
    }

    public function test_admin_acceptances_page_loads_without_contract(): void
    {
        $s = $this->scenario();
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.marketers.contract.acceptances', $s->marketer->id))
            ->assertOk();
    }
}
