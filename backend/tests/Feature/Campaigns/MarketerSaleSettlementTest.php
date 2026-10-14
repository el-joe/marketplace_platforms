<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Jobs\ReleaseMarketerPendingCommissionJob;
use App\Jobs\SettleMarketerSalesJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\LedgerEntry;
use App\Models\Marketer;
use App\Models\MarketerListing;
use App\Models\MarketerSaleSettlement;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Customer\CartService;
use App\Services\LedgerService;
use App\Services\Marketer\MarketerSaleSettlementService;
use App\Services\OrderStateMachine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PeerPromotionFixtures as P;
use Tests\TestCase;

/**
 * Marketer-as-seller money: sub_orders with seller_type = 'marketer' (own-FBN listing sold directly, or sold through a
 * peer promoter's proxy listing) settle into the OWNER marketer's wallet like a vendor payout, with a snapshot.
 */
class MarketerSaleSettlementTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
    }

    private function order(MarketerListing $listing, int $qty = 2, string $gateway = 'cod'): Order
    {
        $this->actingAs($this->s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $listing->id, 'quantity' => $qty,
            'unit_price' => (int) $listing->price, 'added_at' => now(),
        ]);
        $response = $this->postJson("/api/customer/v1/{$this->s->country->site_code}/checkout/place-order", [
            'address_id' => $this->s->customerAddress->id,
            'country_payment_gateway_id' => $this->s->countryPaymentGateways[$gateway]->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertSame(201, $response->status(), substr((string) $response->getContent(), 0, 500));

        return Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
    }

    private function deliver(Order $order, bool $returnWindowPassed = true): void
    {
        $machine = app(OrderStateMachine::class);
        foreach ($order->subOrders()->get() as $so) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($so, $step, CancelActor::System);
            }
            // COD cash remitted by the delivery agent.
            $so->forceFill(['cod_remittance_confirmed' => true])->save();
        }
        $order->items()->update(['return_eligible_until' => $returnWindowPassed ? now()->subDay()->toDateString() : now()->addDays(5)->toDateString()]);
    }

    private function wallet(string $marketerId): ?Wallet
    {
        return Wallet::where('owner_type', 'marketer')->where('owner_id', $marketerId)->first();
    }

    public function test_direct_own_listing_sale_credits_owner_net_of_commission_and_vat(): void
    {
        $owner = F::influencer($this->s);
        $listing = P::ownListing($this->s, $owner, 20);
        $order = $this->order($listing, 2);
        $sub = $order->subOrders()->sole();

        // Category commission + VAT were charged to the marketer seller at checkout, exactly like a vendor.
        $this->assertSame('marketer', $sub->seller_type);
        $this->assertGreaterThan(0, (int) round((float) $sub->platform_commission));
        $this->assertGreaterThan(0, (int) round((float) $sub->commission_vat));

        $this->deliver($order);
        (new SettleMarketerSalesJob)->handle(app(MarketerSaleSettlementService::class));

        $row = MarketerSaleSettlement::where('sub_order_id', $sub->id)->sole();
        $this->assertSame($owner->id, $row->marketer_id);
        $this->assertSame('direct', $row->sold_via);
        $this->assertSame(40000, $row->gross_amount);
        $this->assertSame((int) round((float) $sub->platform_commission), $row->platform_commission_amount);
        $this->assertSame((int) round((float) $sub->commission_vat), $row->commission_vat_amount);
        $this->assertSame(0, $row->peer_commission_amount);
        $this->assertSame((int) round((float) $sub->vendor_payout), $row->net_amount);
        // Every deduction is accounted for: gross - parts = net.
        $this->assertSame(
            $row->gross_amount,
            $row->net_amount + $row->coupon_cost_amount + $row->platform_commission_amount + $row->commission_vat_amount
                + $row->gateway_fee_amount + $row->peer_commission_amount + $row->other_deduction_amount
        );
        $this->assertSame($row->net_amount, $this->wallet($owner->id)->pending_balance);
        $this->assertSame(0, $this->wallet($owner->id)->balance);
    }

    public function test_settlement_is_idempotent_and_waits_for_return_window_and_cod_remittance(): void
    {
        $owner = F::influencer($this->s);
        $order = $this->order(P::ownListing($this->s, $owner, 20), 1);
        $sub = $order->subOrders()->sole();

        $this->deliver($order, returnWindowPassed: false);
        $service = app(MarketerSaleSettlementService::class);
        $this->assertSame(0, $service->settleAllEligible());
        $this->assertSame(0, MarketerSaleSettlement::count());

        $order->items()->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        $sub->refresh()->forceFill(['cod_remittance_confirmed' => false])->save();
        $this->assertSame(0, $service->settleAllEligible(), 'COD cash not remitted yet');

        $sub->refresh()->forceFill(['cod_remittance_confirmed' => true])->save();
        $this->assertSame(1, $service->settleAllEligible());
        $net = $this->wallet($owner->id)->pending_balance;
        $this->assertGreaterThan(0, $net);

        $this->assertSame(0, $service->settleAllEligible(), 'second run credits nothing');
        $this->assertNull($service->settle($sub->fresh()), 'direct call is a no-op as well');
        $this->assertSame(1, MarketerSaleSettlement::count());
        $this->assertSame($net, $this->wallet($owner->id)->pending_balance);
    }

    public function test_peer_proxy_sale_settles_to_the_owner_who_pays_the_promoter(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        P::promoterRule($this->s, $promoter, 3000);
        $campaign = P::request($promoter, P::ownListing($this->s, $owner, 20));
        $proxy = MarketerListing::where('invitation_id', $campaign->invitations()->sole()->id)->sole();

        $order = $this->order($proxy, 2);
        $sub = $order->subOrders()->sole();
        $this->deliver($order);
        app(MarketerSaleSettlementService::class)->settleAllEligible();

        $row = MarketerSaleSettlement::where('sub_order_id', $sub->id)->sole();
        $this->assertSame($owner->id, $row->marketer_id);
        $this->assertSame('peer', $row->sold_via);
        $this->assertSame(6000, $row->peer_commission_amount);
        // net = gross - commission - VAT - promoter commission (no gateway fee on COD).
        $this->assertSame(
            40000 - $row->platform_commission_amount - $row->commission_vat_amount - 6000,
            $row->net_amount
        );
        $this->assertSame($row->net_amount, $this->wallet($owner->id)->pending_balance);
        $this->assertNull($this->wallet($promoter->id), 'the promoter earns through the conversion flow, not this settlement');
    }

    public function test_release_moves_net_to_balance_and_writes_a_wallet_transaction_once(): void
    {
        $owner = F::influencer($this->s);
        $order = $this->order(P::ownListing($this->s, $owner, 20), 2);
        $this->deliver($order);
        app(MarketerSaleSettlementService::class)->settleAllEligible();
        $row = MarketerSaleSettlement::sole();

        (new ReleaseMarketerPendingCommissionJob)->handle();
        $this->assertSame(0, $this->wallet($owner->id)->balance, 'still inside the clearing window');

        $row->update(['credited_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();
        (new ReleaseMarketerPendingCommissionJob)->handle();

        $wallet = $this->wallet($owner->id);
        $this->assertSame($row->net_amount, $wallet->balance);
        $this->assertSame(0, $wallet->pending_balance);
        $tx = WalletTransaction::where('wallet_id', $wallet->id)->where('source_type', 'marketer_sale')->get();
        $this->assertCount(1, $tx);
        $this->assertSame($row->net_amount, $tx->first()->amount);
        $this->assertSame($row->net_amount, $tx->first()->balance_after);
    }

    public function test_order_capture_ledger_books_the_marketer_net_as_seller_payable(): void
    {
        $owner = F::influencer($this->s);
        $order = $this->order(P::ownListing($this->s, $owner, 20), 2);
        $sub = $order->subOrders()->sole();

        app(LedgerService::class)->postOrderCapture($order->fresh(), (int) $order->total);

        $payable = LedgerEntry::where('transaction_group_id', $order->id)->where('account_type', 'seller_payable')
            ->where('account_holder_type', 'marketer')->where('account_holder_id', $owner->id)->sole();
        $this->assertSame((int) round((float) $sub->vendor_payout), (int) $payable->credit);

        $entries = LedgerEntry::where('transaction_group_id', $order->id)->get();
        $this->assertSame((int) $entries->sum('debit'), (int) $entries->sum('credit'));
    }

    // ───────── panels ─────────

    private function settledOwner(): Marketer
    {
        $owner = F::influencer($this->s);
        $order = $this->order(P::ownListing($this->s, $owner, 20), 2);
        $this->deliver($order);
        app(MarketerSaleSettlementService::class)->settleAllEligible();

        return $owner;
    }

    public function test_marketer_finance_pages_show_sales_lines_in_both_languages(): void
    {
        $owner = $this->settledOwner();
        $owner->forceFill(['country_id' => $this->s->country->id])->save();
        $login = F::marketerLogin($owner);
        $row = MarketerSaleSettlement::sole();
        $host = ['HTTP_HOST' => 'marketer.localhost'];

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            $html = $this->withSession(['locale' => $locale])->actingAs($login, 'marketer')->get('http://marketer.localhost/finance/commissions', $host)
                ->assertOk()->assertSee('data-sales-lines', false)->assertSee(__('marketer.finance.sales_title'))->getContent();
            $this->assertMatchesRegularExpression('/data-gross>\s*40,000\s*</', $html);
            $this->assertMatchesRegularExpression('/data-commission>\s*−'.number_format($row->platform_commission_amount).'\s*</u', $html);
            $this->assertMatchesRegularExpression('/data-net>\s*'.number_format($row->net_amount).'\b/', $html);

            $this->withSession(['locale' => $locale])->actingAs($login, 'marketer')->get('http://marketer.localhost/finance/wallet', $host)
                ->assertOk()->assertSee('data-wallet-sales', false)->assertSee(number_format($row->net_amount));
        }
    }

    public function test_marketer_without_sales_sees_no_sales_block_and_other_marketers_do_not_leak(): void
    {
        $this->settledOwner();
        $other = F::influencer($this->s);
        $other->forceFill(['country_id' => $this->s->country->id])->save();
        $html = $this->actingAs(F::marketerLogin($other), 'marketer')
            ->get('http://marketer.localhost/finance/commissions', ['HTTP_HOST' => 'marketer.localhost'])
            ->assertOk()->getContent();
        $this->assertStringNotContainsString('data-sales-lines', $html);
    }

    public function test_admin_marketer_page_and_withdrawal_screen_show_the_sales_statement(): void
    {
        $owner = $this->settledOwner();
        $row = MarketerSaleSettlement::sole();
        foreach (['marketers.view', 'marketers.manage', 'vendors.assigned_only'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['marketers.view', 'marketers.manage']);

        $html = $this->actingAs($admin, 'admin')->get(route('admin.marketers.show', $owner))
            ->assertOk()->assertSee('sales-statement', false)->assertSee('data-sale-row', false)->getContent();
        $this->assertStringContainsString(number_format($row->net_amount), $html);
        $this->assertStringContainsString(number_format($row->platform_commission_amount), $html);
    }

    public function test_admin_withdrawal_queue_shows_the_sales_breakdown_for_the_owner_wallet(): void
    {
        $owner = $this->settledOwner();
        $row = MarketerSaleSettlement::sole();
        $row->update(['credited_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob)->handle();
        $wallet = $this->wallet($owner->id);
        app(WalletService::class)->requestWithdrawal($wallet, $row->net_amount, ['bank_name' => 'B', 'bank_iban' => 'AE00']);

        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $admin = Admin::factory()->create();

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $expected = __('admin.wallets.breakdown_sales', [
                'gross' => number_format($row->gross_amount), 'commission' => number_format($row->platform_commission_amount),
                'vat' => number_format($row->commission_vat_amount), 'peer' => 0, 'net' => number_format($row->net_amount),
            ]);
            $html = $this->withSession(['locale' => $locale])->actingAs($admin, 'admin')->get(route('admin.wallets.withdrawals'))
                ->assertOk()->assertSee('data-breakdown-sales', false)->getContent();
            $this->assertStringContainsString(e($expected), $html);
        }
    }
}
