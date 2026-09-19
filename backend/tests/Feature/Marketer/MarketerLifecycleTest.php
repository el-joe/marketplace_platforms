<?php

namespace Tests\Feature\Marketer;

use App\Enums\CancelActor;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Jobs\ReleaseMarketerPendingCommissionJob;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\LedgerEntry;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerContract;
use App\Models\MarketerContractAcceptance;
use App\Models\MarketerContractVersion;
use App\Models\MarketerListing;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletWithdrawalRequest;
use App\Services\MarketerCampaignService;
use App\Services\OrderStateMachine;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-16 task 1: the full marketer lifecycle, end to end,
 * asserting real state at every step rather than "no exception thrown":
 *
 *   register -> admin approve -> marketer accepts onboarding contract
 *   -> receives a campaign invitation -> accepts it (marketer listing +
 *   referral_code/link/QR created) -> a customer clicks the referral link
 *   (X-Session-Id recorded, redirected to the MARKETER listing) -> buys
 *   through that listing -> order delivered -> ApproveMarketerConversionsJob
 *   (return window passed) approves the conversion and credits wallet
 *   pending_balance -> ReleaseMarketerPendingCommissionJob (clearing days
 *   passed) moves it into spendable balance -> marketer requests a
 *   withdrawal -> admin approves/pays it -> ledger entries balance and the
 *   conversion is marked paid/commissioned.
 */
class MarketerLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Prevents ProcessInvitationTimeoutJob (dispatched with a delay)
        // from running synchronously under QUEUE_CONNECTION=sync and
        // expiring/replacing the invitation before the test can accept it.
        \Illuminate\Support\Facades\Queue::fake();
    }

    private function approvedAdmin(): Admin
    {
        // 'vendors.assigned_only' is checked unconditionally by the global
        // ScopeAdminToAssignedVendor middleware on every admin request —
        // unrelated to marketers, but Spatie throws if the permission row
        // doesn't exist at all (vs. simply not granted).
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketers.view', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'marketers.manage', 'guard_name' => 'admin']);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['marketers.view', 'marketers.manage']);

        return $admin;
    }

    public function test_marketer_cannot_accept_invitation_without_approval_and_contract_acceptance(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-' . Str::lower(Str::random(6))]);

        $marketer = Marketer::create([
            'name'          => 'Gated Marketer',
            'email'         => 'gated-' . Str::lower(Str::random(6)) . '@example.test',
            'marketer_type' => 'affiliate',
            'country_id'    => $scenario->country->id,
            'global_status' => 'pending',
        ]);

        $campaignService = app(MarketerCampaignService::class);
        $campaign = $campaignService->createCampaign(
            CampaignOwner::vendor($scenario->vendor),
            CampaignSource::vendorListing($scenario->vendorListingFbp->id),
            [
                'country_id'                => $scenario->country->id,
                'currency'                  => 'AED',
                'commission_type'           => 'fixed',
                'max_commission_budget'     => 1000000,
                'marketer_commission_amount' => 5000,
                'marketer_ids'              => [], // pending marketer can't be invited yet
            ]
        );
        $admin = $this->approvedAdmin();
        $campaignService->approveCampaign($campaign, $admin);

        // Invite manually to exercise the gate regardless of active-only inviteMarketers().
        $invitation = $campaignService->dispatchInvitation($campaign, $marketer->id);

        // 1) Not yet approved -> rejected.
        try {
            $campaignService->acceptInvitation($invitation);
            $this->fail('Expected rejection: marketer not approved.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not approved', $e->getMessage());
        }

        // 2) Approve the marketer, but no contract accepted yet -> still rejected.
        $marketer->update(['global_status' => 'active', 'approved_at' => now(), 'approved_by_admin_id' => $admin->id]);
        $contract = MarketerContract::create(['marketer_id' => $marketer->id, 'current_version' => 1, 'is_required' => true]);
        $version = MarketerContractVersion::create([
            'marketer_contract_id' => $contract->id,
            'version_number'       => 1,
            'content_type'         => 'text',
            'text_content'         => 'Terms.',
            'is_active'            => true,
        ]);

        $invitation->refresh();
        try {
            $campaignService->acceptInvitation($invitation);
            $this->fail('Expected rejection: contract not accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('contract', $e->getMessage());
        }

        // 3) Accept the contract -> now it works.
        MarketerContractAcceptance::create([
            'marketer_id'                   => $marketer->id,
            'marketer_contract_version_id'  => $version->id,
            'accepted_at'                   => now(),
        ]);
        $this->assertTrue($marketer->hasAcceptedContract());

        $invitation->refresh();
        $campaignService->acceptInvitation($invitation);
        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);
    }

    public function test_full_marketer_lifecycle_register_through_withdrawal_payout(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-' . Str::lower(Str::random(6))]);

        // ── 1. Register ──────────────────────────────────────────────────
        $email = 'lifecycle-' . Str::lower(Str::random(6)) . '@example.test';
        $registerResponse = $this->post('http://marketer.localhost/register', [
            'name'                  => 'Lifecycle Marketer',
            'email'                 => $email,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'marketer_type'         => 'affiliate',
            'country_id'            => $scenario->country->id,
        ], ['HTTP_HOST' => 'marketer.localhost']);
        $registerResponse->assertStatus(302);

        $marketer = Marketer::where('email', $email)->firstOrFail();
        $this->assertSame('pending', $marketer->global_status->value);

        // ── 2. Admin approve ─────────────────────────────────────────────
        $admin = $this->approvedAdmin();
        $approveResponse = $this->actingAs($admin, 'admin')
            ->post("http://admin.localhost/marketers/{$marketer->id}/approve", [], ['HTTP_HOST' => 'admin.localhost']);
        $approveResponse->assertStatus(302);

        $marketer->refresh();
        $this->assertSame('active', $marketer->global_status->value);
        $this->assertNotNull($marketer->approved_at);

        // ── 3. Marketer accepts the onboarding contract ─────────────────
        $contract = MarketerContract::create(['marketer_id' => $marketer->id, 'current_version' => 1, 'is_required' => true]);
        $version = MarketerContractVersion::create([
            'marketer_contract_id' => $contract->id,
            'version_number'       => 1,
            'content_type'         => 'text',
            'text_content'         => 'Standard marketer terms.',
            'is_active'            => true,
        ]);

        $marketerAdmin = $marketer->marketerAdmins()->where('is_owner', true)->firstOrFail();
        $acceptContractResponse = $this->actingAs($marketerAdmin, 'marketer')
            ->post('http://marketer.localhost/contract/accept', [], ['HTTP_HOST' => 'marketer.localhost']);
        $acceptContractResponse->assertStatus(302);

        $this->assertTrue($marketer->hasAcceptedContract());
        $this->assertDatabaseHas('marketer_contract_acceptances', [
            'marketer_id' => $marketer->id,
            'marketer_contract_version_id' => $version->id,
        ]);

        // ── 4. Marketer receives a campaign invitation ───────────────────
        $campaignService = app(MarketerCampaignService::class);
        $campaign = $campaignService->createCampaign(
            CampaignOwner::vendor($scenario->vendor),
            CampaignSource::vendorListing($scenario->vendorListingFbp->id),
            [
                'country_id'                 => $scenario->country->id,
                'currency'                    => 'AED',
                'commission_type'             => 'fixed',
                'max_commission_budget'       => 1000000,
                'marketer_commission_amount'  => 5000,
                'marketer_ids'                => [$marketer->id],
            ]
        );
        $campaignService->approveCampaign($campaign, $admin);

        $invitation = MarketerCampaignInvitation::where('campaign_id', $campaign->id)
            ->where('marketer_id', $marketer->id)
            ->firstOrFail();
        $this->assertSame('pending', $invitation->status);
        $this->assertNotNull($invitation->referral_code);
        $this->assertNotNull($invitation->referral_link);

        // ── 5. Marketer accepts the invitation ───────────────────────────
        $campaignService->acceptInvitation($invitation);
        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);

        $marketerListing = MarketerListing::where('invitation_id', $invitation->id)->firstOrFail();
        $this->assertSame('active', $marketerListing->status);
        $this->assertSame($marketer->id, $marketerListing->marketer_id);

        // ── 6. Customer clicks the referral link ─────────────────────────
        $sessionId = (string) Str::uuid();
        $clickResponse = $this->getJson(
            "/api/r/{$invitation->referral_code}",
            ['X-Session-Id' => $sessionId, 'Accept' => 'application/json']
        );
        $clickResponse->assertOk();
        $this->assertSame(
            ['referral_code' => $invitation->referral_code],
            array_intersect_key(Cache::get("referral_click:{$sessionId}"), ['referral_code' => true])
        );
        $this->assertStringContainsString((string) $marketerListing->id, $clickResponse->json('destination'));

        // ── 7. Customer buys via the marketer listing ────────────────────
        $this->actingAs($scenario->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        CartItem::create([
            'cart_id'              => $cart->id,
            'marketer_listing_id'  => $marketerListing->id,
            'quantity'             => 1,
            'unit_price'           => (int) $marketerListing->price,
            'added_at'             => now(),
        ]);

        // The marketer's contract is required, so the customer must accept it first.
        $acceptanceId = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/marketers/{$marketer->id}/contract/accept",
            ['version_id' => $version->id]
        )->assertStatus(201)->json('acceptance_id');

        $placeResponse = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", [
            'contract_acceptance_ids'    => [$acceptanceId],
            'address_id'                 => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key'            => (string) Str::uuid(),
        ]);
        $placeResponse->assertStatus(201);

        $orderNumber = $placeResponse->json('data.order.order_number') ?? $placeResponse->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->assertMoneyBalanced($order);

        $order->load('items');
        $item = $order->items->firstWhere('marketer_listing_id', $marketerListing->id);
        $this->assertNotNull($item);
        $this->assertSame($invitation->id, $item->marketer_campaign_invitation_id);

        $conversion = MarketerCampaignConversion::where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame('pending', $conversion->status);
        $this->assertGreaterThan(0, $conversion->commission_amount);

        // ── 8. Order delivered ────────────────────────────────────────────
        $machine = app(OrderStateMachine::class);
        $order->load('subOrders');
        foreach ($order->subOrders as $subOrder) {
            foreach (['confirmed', 'processing', 'packed', 'shipped', 'delivered'] as $step) {
                $machine->transition($subOrder, $step, CancelActor::System);
            }
        }

        // Return window has not passed yet: still pending.
        (new ApproveMarketerConversionsJob())->handle();
        $conversion->refresh();
        $this->assertSame('pending', $conversion->status);

        // ── 9. ApproveMarketerConversionsJob after the return window ─────
        $item->update(['return_eligible_until' => now()->subDay()->toDateString()]);
        (new ApproveMarketerConversionsJob())->handle();

        $conversion->refresh();
        $this->assertSame('approved', $conversion->status);
        $this->assertNotNull($conversion->wallet_credited_at);

        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $marketer->id)->firstOrFail();
        $totalCommission = (int) $conversion->commission_amount + (int) ($conversion->flash_sale_bonus_amount ?? 0);
        $this->assertSame($totalCommission, $wallet->pending_balance);
        $this->assertSame(0, $wallet->balance);

        // ── 10. ReleaseMarketerPendingCommissionJob after clearing days ──
        $conversion->update(['approved_at' => now()->subDays(4)]);
        (new ReleaseMarketerPendingCommissionJob())->handle();

        $wallet->refresh();
        $conversion->refresh();
        $this->assertSame(0, $wallet->pending_balance);
        $this->assertSame($totalCommission, $wallet->balance);
        $this->assertNotNull($conversion->wallet_released_at);

        // ── 11. Marketer requests a withdrawal ────────────────────────────
        $withdrawResponse = $this->actingAs($marketerAdmin, 'marketer')
            ->post('http://marketer.localhost/finance/wallet/withdraw', [
                'amount'    => $totalCommission,
                'bank_name' => 'Test Bank',
                'bank_iban' => 'AE0000000000000000000',
            ], ['HTTP_HOST' => 'marketer.localhost']);
        $withdrawResponse->assertStatus(302);

        $wallet->refresh();
        $this->assertSame(0, $wallet->balance);
        $this->assertSame($totalCommission, $wallet->pending_balance);

        $withdrawal = WalletWithdrawalRequest::where('wallet_id', $wallet->id)->latest()->firstOrFail();
        $this->assertSame('pending', $withdrawal->status->value);
        $this->assertSame($totalCommission, $withdrawal->amount);

        // ── 12. Admin pays the withdrawal ─────────────────────────────────
        $payResponse = $this->actingAs($admin, 'admin')
            ->patch("http://admin.localhost/wallets/withdrawals/{$withdrawal->id}/approve", [], ['HTTP_HOST' => 'admin.localhost']);
        $payResponse->assertStatus(302);

        $withdrawal->refresh();
        $wallet->refresh();
        $this->assertSame('approved', $withdrawal->status->value);
        $this->assertSame(0, $wallet->pending_balance);
        $this->assertSame(0, $wallet->balance);

        // ── 13. Ledger balances + conversion marked paid/commissioned ────
        $conversion->refresh();
        $this->assertTrue((bool) $conversion->commissioned);
        $this->assertNotNull($conversion->paid_at);

        $withdrawalLedgerEntries = LedgerEntry::where('reference_type', 'withdrawal')
            ->where('reference_id', (string) $withdrawal->id)
            ->get();
        $this->assertGreaterThan(0, $withdrawalLedgerEntries->count());
        $this->assertSame(
            (int) $withdrawalLedgerEntries->sum('debit'),
            (int) $withdrawalLedgerEntries->sum('credit')
        );
    }
}
