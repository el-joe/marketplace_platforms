<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CancelActor;
use App\Enums\VendorGlobalStatus;
use App\Jobs\GenerateVendorPayoutsJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\MarketerCampaignSample;
use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\SubOrder;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Customer\CartService;
use App\Services\OrderStateMachine;
use App\Services\PayoutCalculationService;
use App\Services\Vendor\TransactionFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P8 (R5): the weekly statement carries each dispatched promotion sample as a
 * 0-priced line that deducts only the admin commission, once.
 */
class CampaignSamplesPayoutTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Permission::firstOrCreate(['name' => 'payouts.approve', 'guard_name' => 'admin']);
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        VendorBankAccount::create([
            'vendor_id' => $this->s->vendor->id, 'account_holder_name' => 'Test Vendor', 'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456', 'account_number_encrypted' => 'enc', 'currency' => $this->s->country->currency_code,
            'is_primary' => true, 'verification_status' => 'verified',
        ]);
        $this->s->vendor->update(['global_status' => VendorGlobalStatus::Active]);
    }

    /** A delivered, payout-eligible sale of the FBP listing (net payout > the sample commission). */
    private function eligibleSale(): SubOrder
    {
        $this->actingAs($this->s->customer, 'customer');
        $cart = app(CartService::class)
            ->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'vendor_listing_id' => $this->s->vendorListingFbp->id, 'quantity' => 2,
            'unit_price' => (int) $this->s->vendorListingFbp->getRawOriginal('price'), 'added_at' => now(),
        ]);
        $response = $this->postJson("/api/customer/v1/{$this->s->country->site_code}/checkout/place-order", [
            'address_id' => $this->s->customerAddress->id,
            'country_payment_gateway_id' => $this->s->countryPaymentGateways['wallet']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $response->assertStatus(201);
        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
        $subOrder = $order->subOrders()->where('vendor_id', $this->s->vendor->id)->firstOrFail();

        $machine = app(OrderStateMachine::class);
        foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
            $machine->transition($subOrder, $step, CancelActor::System);
        }
        $subOrder->refresh()->items()->update(['return_eligible_until' => now()->subDay()->toDateString()]);

        return $subOrder->refresh();
    }

    /** Run the job inline (Queue::fake() would swallow dispatchSync). */
    private function runPayouts($from, $to): void
    {
        (new GenerateVendorPayoutsJob($from, $to))->handle(app(PayoutCalculationService::class));
    }

    private function dispatchedSample(int $influencerQty = 1): MarketerCampaignSample
    {
        Queue::fake();
        F::categoryQty($this->s, platform: 1, influencer: $influencerQty);
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);
        $sample = $campaign->samples()->where('sample_type', 'admin')->sole();
        app(CampaignSampleService::class)->dispatch($sample);

        return $sample->fresh();
    }

    public function test_dispatched_sample_is_a_zero_priced_statement_line_charged_once(): void
    {
        $subOrder = $this->eligibleSale();
        $sample = $this->dispatchedSample();
        $commission = (int) floor(1200 * 12 / 100) + 300; // FBN listing: 12% + 300 fixed
        $this->assertSame($commission, $sample->commission_amount);

        $this->runPayouts(now()->subDays(7), now());

        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();
        $line = PayoutItem::where('campaign_sample_id', $sample->id)->sole();
        $this->assertSame('promotion_sample', $line->item_type);
        $this->assertNull($line->sub_order_id);
        $this->assertEquals($payout->id, $line->payout_id);
        $this->assertSame(0, (int) $line->gross, 'price shown to the seller is zero');
        $this->assertSame($commission, (int) $line->commission);
        $this->assertSame(-$commission, (int) $line->net);
        $this->assertStringContainsString('عينة ترويج مع المسوقين', $line->description);

        // The sale and the sample commission both land in the statement.
        $this->assertSame((int) $subOrder->vendor_payout - $commission, (int) $payout->net_amount);
        $this->assertSame((int) $subOrder->platform_commission + $commission, (int) $payout->commission);

        // A later run (new sale, overlapping period) must not charge the sample again.
        $this->eligibleSale();
        $this->runPayouts(now()->subDays(3), now()->addDay());
        $this->assertSame(1, PayoutItem::where('campaign_sample_id', $sample->id)->count());
        $this->assertSame(
            1,
            PayoutItem::where('item_type', 'promotion_sample')->count()
        );
    }

    public function test_a_sample_that_does_not_fit_the_statement_rolls_forward_and_is_not_lost(): void
    {
        $this->eligibleSale();
        $sample = $this->dispatchedSample();
        $sample->update(['commission_amount' => 900_000_000]);

        $this->runPayouts(now()->subDays(7), now());

        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();
        $this->assertGreaterThan(0, (int) $payout->net_amount);
        $this->assertSame(0, PayoutItem::where('campaign_sample_id', $sample->id)->count());
        $this->assertCount(1, app(CampaignSampleService::class)->unsettledFor($this->s->vendor, 'AED'));
    }

    public function test_cancelled_or_undispatched_samples_never_reach_the_statement(): void
    {
        $this->eligibleSale();
        F::categoryQty($this->s, platform: 1, influencer: 1);
        Queue::fake();
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);
        $pending = $campaign->samples()->where('sample_type', 'admin')->sole();

        $this->runPayouts(now()->subDays(7), now());

        $this->assertSame(0, PayoutItem::where('campaign_sample_id', $pending->id)->count());
        $this->assertCount(0, app(CampaignSampleService::class)->unsettledFor($this->s->vendor, 'AED'));
    }

    public function test_transaction_feed_shows_the_sample_with_its_commission_and_statement(): void
    {
        $this->eligibleSale();
        $sample = $this->dispatchedSample();
        $feed = app(TransactionFeedService::class);

        $rows = collect($feed->getFeed($this->s->vendor, 'promotion_sample', null, null, 1, 50)['items']);
        $this->assertCount(1, $rows);
        $this->assertSame(0, $rows[0]['gross']);
        $this->assertSame($sample->commission_amount, $rows[0]['commission']);
        $this->assertSame(-$sample->commission_amount, $rows[0]['net']);
        $this->assertSame('unsettled', $rows[0]['payout_status']);

        Cache::flush();
        $this->runPayouts(now()->subDays(7), now());
        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();

        $rows = collect($feed->getFeed($this->s->vendor, 'promotion_sample', null, null, 1, 50)['items']);
        $this->assertStringContainsString($payout->payout_number, $rows[0]['payout_status']);
    }

    public function test_vendor_finance_page_and_admin_payout_page_show_the_sample_line(): void
    {
        $this->withoutVite();
        $this->eligibleSale();
        $sample = $this->dispatchedSample();
        $this->runPayouts(now()->subDays(7), now());
        $payout = Payout::where('vendor_id', $this->s->vendor->id)->sole();

        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'fin-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        $vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'finance.view', 'guard_name' => 'vendor']));

        $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.finance.transactions', ['type' => 'samples', 'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee(__('partner.finance.promotion_sample'))
            ->assertSee(number_format($sample->commission_amount, 2));

        foreach (['payouts.view'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'admin']);
        }
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('payouts.view');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payouts.show', $payout->id))
            ->assertOk()
            ->assertSee(__('admin.payouts.promotion_sample'))
            ->assertSee('عينة ترويج مع المسوقين');
    }
}
