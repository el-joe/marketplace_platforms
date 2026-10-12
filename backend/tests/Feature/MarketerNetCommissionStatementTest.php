<?php

namespace Tests\Feature;

use App\Enums\CancelActor;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\ReleaseMarketerPendingCommissionJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\MarketerAdmin;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerProfile;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletWithdrawalRequest;
use App\Services\Customer\CartService;
use App\Services\MarketerCommissionStatementService;
use App\Services\MarketerConversionReversalService;
use App\Services\OrderStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** P11: gross -> admin cut -> discount -> VAT -> net, on both panels + service. */
class MarketerNetCommissionStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
    }

    // ───────── service ─────────

    public static function breakdowns(): array
    {
        return [
            'spec example 100 @30%' => [100, 30, null, 0, ['cut' => 30, 'vat' => 0, 'net' => 70, 'discount' => 0]],
            'zero cut' => [100, 0, null, 15, ['cut' => 0, 'vat' => 0, 'net' => 100, 'discount' => 0]],
            'vat 14% on cut 30 keeps decimals' => [100, 30, null, 14, ['cut' => 30, 'vat' => 4.2, 'net' => 65.8, 'discount' => 0]],
            'decimal cut 33.5 is kept' => [335, 10, null, 0, ['cut' => 33.5, 'vat' => 0, 'net' => 301.5, 'discount' => 0]],
            'half up at 2 decimals' => [1.25, 10, null, 0, ['cut' => 0.13, 'vat' => 0, 'net' => 1.12, 'discount' => 0]],
            'flat discount reduces cut' => [1000, 30, ['flat', 100, 0], 10, ['cut' => 200, 'vat' => 20, 'net' => 780, 'discount' => 100]],
            'flat discount capped at cut' => [100, 30, ['flat', 999, 0], 10, ['cut' => 0, 'vat' => 0, 'net' => 100, 'discount' => 30]],
            'percentage discount of cut' => [1000, 30, ['percentage', 0, 50], 15, ['cut' => 150, 'vat' => 22.5, 'net' => 827.5, 'discount' => 150]],
            'no float drift 0.1+0.2 style' => [3, 33.33, null, 0, ['cut' => 1, 'vat' => 0, 'net' => 2, 'discount' => 0]],
        ];
    }

    #[DataProvider('breakdowns')]
    public function test_breakdown_math(int|float $gross, float $cut, ?array $disc, float $vat, array $expect): void
    {
        $profile = new MarketerProfile;
        if ($disc) {
            $profile->commission_discount_type = $disc[0];
            $profile->commission_discount_flat = $disc[1];
            $profile->commission_discount_percentage = $disc[2];
        }
        $b = app(MarketerCommissionStatementService::class)->breakdown($gross, $cut, $profile, $vat);
        foreach ($expect as $k => $v) {
            $this->assertEquals($v, $b[$k], $k);
        }
        $this->assertEqualsWithDelta($gross, $b['net'] + $b['cut'] + $b['vat'], 0.0001, 'parts must sum to gross');
    }

    // ───────── full flow helpers ─────────

    private function scenario(): MarketplaceScenario
    {
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6)), 'vat_rate' => 0]);

        return $s;
    }

    private function approvedConversion(MarketplaceScenario $s, float $cutPct, float $vat = 0, array $profileExtra = []): MarketerCampaignConversion
    {
        $s->country->update(['vat_rate' => $vat]);
        MarketerProfile::updateOrCreate(
            ['marketer_id' => $s->marketer->id],
            ['platform_cut_percentage' => $cutPct, 'profile_slug' => 'p-'.Str::lower(Str::random(6))] + $profileExtra
        );

        $this->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $s->marketerListing->id, 'quantity' => 1,
            'unit_price' => (int) $s->marketerListing->price, 'added_at' => now(),
        ]);
        $res = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201);
        $order = Order::where('order_number', $res->json('data.order.order_number') ?? $res->json('data.order_number'))->firstOrFail();

        $machine = app(OrderStateMachine::class);
        $order->load('subOrders');
        foreach ($order->subOrders as $so) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($so, $step, CancelActor::System);
            }
        }
        $order->load('items');
        $item = $order->items->firstWhere('marketer_listing_id', $s->marketerListing->id);
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);

        $conv = MarketerCampaignConversion::where('order_item_id', $item->id)->firstOrFail();
        $conv->update(['commission_amount' => 100, 'flash_sale_bonus_amount' => null]);
        app()->call([new ApproveMarketerConversionsJob, 'handle']);

        return $conv->fresh();
    }

    private function marketerAdmin(MarketplaceScenario $s): MarketerAdmin
    {
        $s->marketer->forceFill(['onboarding_completed_at' => now(), 'country_id' => $s->country->id])->save();

        return MarketerAdmin::create([
            'marketer_id' => $s->marketer->id, 'name' => 'A', 'email' => Str::random(8).'@example.test',
            'password' => Hash::make('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    // ───────── marketer panel + job ─────────

    public function test_approval_snapshots_100_to_70_and_wallet_gets_net_and_withdrawal_uses_net(): void
    {
        $s = $this->scenario();
        $c = $this->approvedConversion($s, 30);

        $this->assertSame(100, (int) $c->gross_commission_amount);
        $this->assertSame(30, (int) $c->platform_cut_amount);
        $this->assertSame(0, (int) $c->platform_cut_vat_amount);
        $this->assertSame(70, (int) $c->net_commission_amount);
        $this->assertSame('30.00', (string) $c->platform_cut_percentage);

        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $s->marketer->id)->firstOrFail();
        $this->assertSame(70, $wallet->pending_balance);

        $c->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();
        $wallet->refresh();
        $this->assertSame(70, $wallet->balance);
        $this->assertSame(0, $wallet->pending_balance);

        // Changing the profile % later must not rewrite the snapshot.
        $s->marketer->marketerProfile->update(['platform_cut_percentage' => 90]);
        $this->assertSame(70, (int) $c->fresh()->net_commission_amount);

        $ma = $this->marketerAdmin($s);
        $host = ['HTTP_HOST' => 'marketer.localhost'];
        $s->marketer->update(['country_id' => $s->country->id]);

        // Cannot withdraw the gross.
        $this->actingAs($ma, 'marketer')->post('http://marketer.localhost/finance/wallet/withdraw',
            ['amount' => 100, 'bank_name' => 'B', 'bank_iban' => 'AE00'], $host);
        $this->assertSame(0, WalletWithdrawalRequest::where('wallet_id', $wallet->id)->count());

        $this->actingAs($ma, 'marketer')->post('http://marketer.localhost/finance/wallet/withdraw',
            ['amount' => 70, 'bank_name' => 'B', 'bank_iban' => 'AE00'], $host);
        $this->assertSame(70, (int) WalletWithdrawalRequest::where('wallet_id', $wallet->id)->firstOrFail()->amount);
        $this->assertSame(0, $wallet->fresh()->balance);
    }

    public function test_marketer_finance_pages_show_gross_cut_vat_net(): void
    {
        $s = $this->scenario();
        $this->approvedConversion($s, 30, 10);   // cut 30, vat 3, net 67
        $s->marketer->update(['country_id' => $s->country->id]);
        $ma = $this->marketerAdmin($s);
        $host = ['HTTP_HOST' => 'marketer.localhost'];

        $html = $this->actingAs($ma, 'marketer')->get('http://marketer.localhost/finance/commissions', $host)
            ->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-gross>\s*100\s*</', $html);
        $this->assertMatchesRegularExpression('/data-cut>\s*−30\s*</u', $html);
        $this->assertMatchesRegularExpression('/data-vat>\s*−3\s*</u', $html);
        $this->assertMatchesRegularExpression('/data-net>\s*67\b/', $html);

        $wallet = $this->actingAs($ma, 'marketer')->get('http://marketer.localhost/finance/wallet', $host)
            ->assertOk()->getContent();
        $this->assertStringContainsString('data-wallet-statement', $wallet);
        $this->assertMatchesRegularExpression('/font-bold text-green-600">67</', $wallet);
    }

    public function test_discount_reduces_cut_then_vat(): void
    {
        $s = $this->scenario();
        $c = $this->approvedConversion($s, 40, 10, ['commission_discount_type' => 'flat', 'commission_discount_flat' => 10]);
        // cut_base 40, discount 10 => cut 30, vat 3, net 67
        $this->assertSame([40, 10, 30, 3, 67], [
            (int) $c->platform_cut_base_amount, (int) $c->platform_cut_discount_amount,
            (int) $c->platform_cut_amount, (int) $c->platform_cut_vat_amount, (int) $c->net_commission_amount,
        ]);
    }

    public function test_reversal_claws_back_net_not_gross(): void
    {
        $s = $this->scenario();
        $c = $this->approvedConversion($s, 30);
        app(MarketerConversionReversalService::class)->reverseOne($c);
        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $s->marketer->id)->firstOrFail();
        $this->assertSame(0, $wallet->pending_balance);
        $this->assertSame('reversed', $c->fresh()->status);
    }

    // ───────── admin panel ─────────

    private function admin(): Admin
    {
        foreach (['marketers.manage', 'marketers.view', 'vendors.assigned_only'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $a = Admin::factory()->create();
        $a->givePermissionTo(['marketers.manage', 'marketers.view']);

        return $a;
    }

    public function test_admin_sets_cut_with_validation_and_audit(): void
    {
        $s = $this->scenario();
        $admin = $this->admin();
        $url = route('admin.marketers.profile.update', $s->marketer);

        foreach ([-1, 101, 'abc', 10.123] as $bad) {
            $this->actingAs($admin, 'admin')->put($url, ['platform_cut_percentage' => $bad])
                ->assertSessionHasErrors('platform_cut_percentage');
        }

        $this->actingAs($admin, 'admin')->put($url, ['platform_cut_percentage' => 30.5])->assertSessionHasNoErrors();
        $this->assertSame('30.50', (string) $s->marketer->marketerProfile()->first()->platform_cut_percentage);

        $log = DB::table('activity_log')->where('description', 'platform_cut_percentage_changed')
            ->where('subject_id', $s->marketer->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->causer_id);
        $p = json_decode($log->properties, true);
        $this->assertEquals(0, $p['old']);
        $this->assertEquals(30.5, $p['new']);

        // Same value again => no second audit row; other profile saves do not reset the cut.
        $this->actingAs($admin, 'admin')->put($url, ['platform_cut_percentage' => 30.5]);
        $this->actingAs($admin, 'admin')->put($url, ['ad_price' => 5]);
        $this->assertSame(1, DB::table('activity_log')->where('description', 'platform_cut_percentage_changed')->count());
        $this->assertSame('30.50', (string) $s->marketer->marketerProfile()->first()->platform_cut_percentage);
    }

    public function test_admin_without_permission_cannot_set_cut(): void
    {
        $s = $this->scenario();
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $a = Admin::factory()->create();
        $this->actingAs($a, 'admin')->put(route('admin.marketers.profile.update', $s->marketer), ['platform_cut_percentage' => 5])
            ->assertStatus(403);
    }

    public function test_admin_marketer_page_shows_field_and_statement_per_conversion(): void
    {
        $s = $this->scenario();
        $this->approvedConversion($s, 30, 10);
        $html = $this->actingAs($this->admin(), 'admin')->get(route('admin.marketers.show', $s->marketer))
            ->assertOk()->getContent();
        $this->assertStringContainsString('name="platform_cut_percentage"', $html);
        $this->assertStringContainsString('commission-statement', $html);
        $this->assertStringContainsString('data-conversion-row', $html);
        $this->assertStringContainsString('100', $html);
        $this->assertMatchesRegularExpression('/data-conversion-row.*?>67</s', $html);
    }
}
