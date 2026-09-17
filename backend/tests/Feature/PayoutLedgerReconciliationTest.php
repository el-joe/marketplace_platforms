<?php

namespace Tests\Feature;

use App\DTOs\Refund\RefundScope;
use App\Enums\CancelActor;
use App\Jobs\GenerateVendorPayoutsJob;
use App\Models\Order;
use App\Models\PayoutItem;
use App\Models\SubOrder;
use App\Models\VendorBankAccount;
use App\Services\FinancialReportService;
use App\Services\LedgerService;
use App\Services\OrderStateMachine;
use App\Services\RefundService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-11: ledger and payouts reconciliation.
 *
 *  - PayoutCalculationService/GenerateVendorPayoutsJob never pays the same
 *    sub-order twice across consecutive payout runs (payout_items guard).
 *  - The double-entry ledger trial balance is 0 for every transaction group,
 *    including refunded/cancelled orders.
 *  - FinancialReportService's reported net reconciles exactly against a
 *    direct query of the platform's ledger accounts for the same period.
 */
class PayoutLedgerReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // GenerateVendorPayoutsJob notifies admins with this permission when
        // payouts are generated; the test DB has no seeded permissions.
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'payouts.approve', 'guard_name' => 'admin']);
    }

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function cartFor(MarketplaceScenario $scenario)
    {
        $this->actingAs($scenario->customer, 'customer');

        return app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
    }

    private function placeOrder(MarketplaceScenario $scenario, string $gatewayCode, array $extra = []): Order
    {
        $payload = array_merge([
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways[$gatewayCode]->id,
            'idempotency_key' => (string) Str::uuid(),
        ], $extra);

        $response = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload);
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');

        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function deliverAndMakeEligible(SubOrder $subOrder): SubOrder
    {
        $machine = app(OrderStateMachine::class);
        foreach (['confirmed', 'processing', 'packed', 'shipped'] as $step) {
            $machine->transition($subOrder, $step, CancelActor::System);
        }
        $machine->transition($subOrder, 'delivered', CancelActor::System);

        $subOrder->refresh();
        // Push the return window into the past so the sub-order is payout-eligible.
        $subOrder->items()->update(['return_eligible_until' => now()->subDay()->toDateString()]);

        return $subOrder->refresh();
    }

    private function verifyBankAccount(MarketplaceScenario $scenario): void
    {
        VendorBankAccount::create([
            'vendor_id' => $scenario->vendor->id,
            'account_holder_name' => 'Test Vendor',
            'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456',
            'account_number_encrypted' => 'encrypted-account-number',
            'currency' => $scenario->country->currency_code,
            'is_primary' => true,
            'verification_status' => 'verified',
        ]);
        $scenario->vendor->update(['global_status' => \App\Enums\VendorGlobalStatus::Active]);
    }

    // ── Double-pay prevention ───────────────────────────────────────────────

    public function test_consecutive_payout_runs_never_pay_the_same_sub_order_twice(): void
    {
        $scenario = $this->buildScenario();
        $this->verifyBankAccount($scenario);

        $cart = $this->cartFor($scenario);
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $order = $this->placeOrder($scenario, 'wallet');
        $order->load('subOrders');
        $subOrder = $order->subOrders->where('vendor_id', $scenario->vendor->id)->first();
        $this->deliverAndMakeEligible($subOrder);

        // First payout run: period covers "this week".
        GenerateVendorPayoutsJob::dispatchSync(now()->subDays(7), now());

        $countAfterFirst = PayoutItem::where('sub_order_id', $subOrder->id)->count();
        $this->assertSame(1, $countAfterFirst, 'first payout run must create exactly one payout_items row for this sub-order');

        // Second, OVERLAPPING payout run — same sub-order must not be
        // selected again because it now has a payout_items row.
        GenerateVendorPayoutsJob::dispatchSync(now()->subDays(3), now());

        $countAfterSecond = PayoutItem::where('sub_order_id', $subOrder->id)->count();
        $this->assertSame(1, $countAfterSecond, 'a second overlapping payout run must not pay the same sub-order again');
    }

    // ── Ledger trial balance ────────────────────────────────────────────────

    public function test_ledger_trial_balance_is_zero_for_capture_cancellation_and_refund_groups(): void
    {
        $scenario = $this->buildScenario();

        // Order A: wallet capture only.
        $cartA = $this->cartFor($scenario);
        \App\Models\CartItem::create([
            'cart_id' => $cartA->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $orderA = $this->placeOrder($scenario, 'wallet');
        $this->assertLedgerBalanced((string) $orderA->id);

        // Order B: wallet capture then a partial card-liability refund.
        $cartB = $this->cartFor($scenario);
        \App\Models\CartItem::create([
            'cart_id' => $cartB->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 2,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $orderB = $this->placeOrder($scenario, 'wallet');
        $this->assertLedgerBalanced((string) $orderB->id);

        $orderB->load('subOrders.items');
        $refund = app(RefundService::class)->refund(
            order: $orderB,
            scope: RefundScope::amount(50),
            reason: 'customer_request',
            liability: 'platform',
            destination: 'wallet',
        );
        // A partial refund posts under its own new group id (refund_reversal),
        // tagged with the refund's own id as reference_id.
        $refundGroup = \App\Models\LedgerEntry::where('reference_type', 'refund_reversal')
            ->where('reference_id', (string) $refund->id)
            ->first()
            ?->transaction_group_id;
        $this->assertNotNull($refundGroup, 'partial refund must post a ledger reversal group');
        $this->assertLedgerBalanced($refundGroup);

        // Order C: wallet capture, then a full order cancellation.
        $cartC = $this->cartFor($scenario);
        \App\Models\CartItem::create([
            'cart_id' => $cartC->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $orderC = $this->placeOrder($scenario, 'wallet');
        $this->assertLedgerBalanced((string) $orderC->id);

        app(\App\Services\OrderCancellationService::class)->cancel(
            scope: $orderC,
            actor: CancelActor::Customer,
            reason: 'customer_request',
        );

        $cancelGroup = \App\Models\LedgerEntry::where('reference_type', 'order_capture_reversal')
            ->where('reference_id', (string) $orderC->id)
            ->first()
            ?->transaction_group_id;
        $this->assertNotNull($cancelGroup, 'cancellation must reverse the capture group');
        $this->assertLedgerBalanced($cancelGroup);
    }

    // ── Financial report reconciles against the ledger ─────────────────────

    public function test_financial_report_net_reconciles_with_direct_ledger_query(): void
    {
        $scenario = $this->buildScenario();

        $cart = $this->cartFor($scenario);
        \App\Models\CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->getRawOriginal('price'),
            'added_at' => now(),
        ]);
        $order = $this->placeOrder($scenario, 'wallet');
        $this->assertLedgerBalanced((string) $order->id);

        $from = now()->subDay();
        $to = now()->addDay();

        $report = app(FinancialReportService::class)->summaryForPeriod($from, $to, $order->currency);

        $directLedgerNet = (int) \App\Models\LedgerEntry::query()
            ->whereIn('account_type', ['platform_commission', 'shipping_revenue', 'warranty_revenue'])
            ->whereBetween('created_at', [$from, $to])
            ->where('currency', $order->currency)
            ->selectRaw('COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) as net')
            ->value('net');

        $this->assertSame($directLedgerNet, $report['net'], 'FinancialReportService net must equal a direct ledger query for the same period/currency');
    }
}
