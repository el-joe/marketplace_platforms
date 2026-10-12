<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\ReleaseMarketerPendingCommissionJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\LedgerEntry;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerProfile;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Customer\CartService;
use App\Services\MarketerCommissionStatementService;
use App\Services\MarketerConversionReversalService;
use App\Services\OrderStateMachine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P11 follow-up: every approved conversion also posts gross / platform cut / VAT / net to the ledger and the wallet
 * history, and the admin wallet + withdrawal screens show the breakdown.
 */
class ConversionStatementLedgerTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6)), 'vat_rate' => 10]);
        MarketerProfile::updateOrCreate(
            ['marketer_id' => $this->s->marketer->id],
            ['platform_cut_percentage' => 30, 'profile_slug' => 'p-'.Str::lower(Str::random(6))]
        );
    }

    /** gross 100, cut 30%, VAT 10% of the cut => 30 / 3 / net 67. */
    private function approved(): MarketerCampaignConversion
    {
        $s = $this->s;
        $this->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
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
        foreach ($order->subOrders()->get() as $so) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($so, $step, CancelActor::System);
            }
        }
        $item = $order->items()->get()->firstWhere('marketer_listing_id', $s->marketerListing->id);
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        MarketerCampaignConversion::where('order_item_id', $item->id)->firstOrFail()
            ->update(['commission_amount' => 100, 'flash_sale_bonus_amount' => null]);
        app()->call([new ApproveMarketerConversionsJob, 'handle']);

        return MarketerCampaignConversion::where('order_item_id', $item->id)->firstOrFail();
    }

    private function statementEntries(MarketerCampaignConversion $c, string $reference = MarketerCommissionStatementService::LEDGER_REFERENCE)
    {
        return LedgerEntry::where('reference_type', $reference)->where('reference_id', $c->id)->get();
    }

    public function test_approval_posts_balanced_gross_cut_vat_net_entries_once(): void
    {
        $c = $this->approved();
        $this->assertSame([100, 30, 3, 67], [(int) $c->gross_commission_amount, (int) $c->platform_cut_amount, (int) $c->platform_cut_vat_amount, (int) $c->net_commission_amount]);

        $entries = $this->statementEntries($c);
        $this->assertSame(100, (int) $entries->where('account_type', 'marketer_commission_payable')->sum('debit'));
        $this->assertSame(67, (int) $entries->where('account_type', 'marketer_payable')->sum('credit'));
        $this->assertSame($this->s->marketer->id, $entries->firstWhere('account_type', 'marketer_payable')->account_holder_id);
        $this->assertSame(30, (int) $entries->where('account_type', 'platform_commission')->sum('credit'));
        $this->assertSame(3, (int) $entries->where('account_type', 'tax_payable')->sum('credit'));
        $this->assertSame((int) $entries->sum('debit'), (int) $entries->sum('credit'));

        // Running the approval job (or the posting) again does not duplicate anything.
        app()->call([new ApproveMarketerConversionsJob, 'handle']);
        app(MarketerCommissionStatementService::class)->postApprovalLedger($c->fresh());
        $this->assertCount($entries->count(), $this->statementEntries($c));
    }

    public function test_zero_cut_posts_only_the_net_side(): void
    {
        MarketerProfile::where('marketer_id', $this->s->marketer->id)->update(['platform_cut_percentage' => 0]);
        $c = $this->approved();
        $entries = $this->statementEntries($c);
        $this->assertSame([], $entries->whereIn('account_type', ['platform_commission', 'tax_payable'])->all());
        $this->assertSame(100, (int) $entries->sum('debit'));
        $this->assertSame(100, (int) $entries->where('account_type', 'marketer_payable')->sum('credit'));
    }

    public function test_release_writes_a_wallet_transaction_with_the_breakdown_once(): void
    {
        $c = $this->approved();
        $c->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();
        (new ReleaseMarketerPendingCommissionJob)->handle();

        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $this->s->marketer->id)->firstOrFail();
        $tx = WalletTransaction::where('wallet_id', $wallet->id)->where('source_type', 'marketer_conversion')->get();
        $this->assertCount(1, $tx);
        $this->assertSame(67, $tx->first()->amount);
        $this->assertSame(67, $tx->first()->balance_after);
        foreach (['100', '30', '3', '67'] as $n) {
            $this->assertStringContainsString($n, $tx->first()->description);
        }
    }

    public function test_reversal_mirrors_the_ledger_and_debits_the_released_net_once(): void
    {
        $c = $this->approved();
        $c->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();

        $service = app(MarketerConversionReversalService::class);
        $service->reverseOne($c->fresh());
        $service->reverseOne($c->fresh());

        $reversal = $this->statementEntries($c, MarketerCommissionStatementService::LEDGER_REFERENCE.'_reversal');
        $original = $this->statementEntries($c);
        $this->assertCount($original->count(), $reversal);
        $this->assertSame((int) $original->sum('debit'), (int) $reversal->sum('credit'));
        $this->assertSame(0, (int) $original->where('account_type', 'marketer_payable')->sum('credit') - (int) $reversal->where('account_type', 'marketer_payable')->sum('debit'));

        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $this->s->marketer->id)->firstOrFail();
        $this->assertSame(0, $wallet->balance);
        $debits = WalletTransaction::where('wallet_id', $wallet->id)->where('source_type', 'marketer_conversion')->where('direction', 'debit')->get();
        $this->assertCount(1, $debits);
        $this->assertSame(67, $debits->first()->amount);
    }

    // ───────── admin screens ─────────

    private function admin(): Admin
    {
        foreach (['wallets.view', 'vendors.assigned_only'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }

        return Admin::factory()->create();
    }

    public function test_admin_withdrawal_queue_and_wallet_page_show_gross_cut_vat_net(): void
    {
        $c = $this->approved();
        $c->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();
        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $this->s->marketer->id)->firstOrFail();
        app(WalletService::class)->requestWithdrawal($wallet, 67, ['bank_name' => 'B', 'bank_iban' => 'AE00']);
        $admin = $this->admin();

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $expected = __('admin.wallets.breakdown_commissions', ['gross' => 100, 'cut' => 30, 'vat' => 3, 'net' => 67]);

            $queue = $this->withSession(['locale' => $locale])->actingAs($admin, 'admin')->get(route('admin.wallets.withdrawals'))
                ->assertOk()->assertSee('data-marketer-breakdown', false)->getContent();
            $this->assertStringContainsString(e($expected), $queue);

            $page = $this->withSession(['locale' => $locale])->actingAs($admin, 'admin')->get(route('admin.wallets.show', $wallet))
                ->assertOk()->assertSee('data-breakdown-commissions', false)->getContent();
            $this->assertStringContainsString(e($expected), $page);
        }
    }

    public function test_marketer_wallet_page_lists_the_commission_transaction(): void
    {
        $c = $this->approved();
        $c->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();
        $this->s->marketer->forceFill(['country_id' => $this->s->country->id, 'onboarding_completed_at' => now()])->save();

        $this->actingAs(F::marketerLogin($this->s->marketer), 'marketer')
            ->get('http://marketer.localhost/finance/wallet', ['HTTP_HOST' => 'marketer.localhost'])
            ->assertOk()
            ->assertSee(e(__('marketer.finance.tx_commission', ['order' => $c->order->order_number, 'gross' => 100, 'cut' => 30, 'vat' => 3, 'net' => 67])), false);
    }
}
