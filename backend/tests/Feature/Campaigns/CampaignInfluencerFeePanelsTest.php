<?php

namespace Tests\Feature\Campaigns;

use App\Enums\VendorGlobalStatus;
use App\Jobs\GenerateVendorPayoutsJob;
use App\Models\Admin;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerInfluencerFeeCountrySetting;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Services\MarketerCampaignService;
use App\Services\PayoutCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** P9 UI: quote + campaign page + finance/statement on the Partner panel, payout + campaign pages on Admin. */
class CampaignInfluencerFeePanelsTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private VendorAdmin $vendorAdmin;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        F::setting('marketer_influencer_fee_payer', 'vendor');
        MarketerInfluencerFeeCountrySetting::updateOrCreate(
            ['country_id' => $this->s->country->id],
            ['fee_per_influencer' => 1000, 'currency' => 'AED', 'updated_by_admin_id' => null]
        );

        $this->vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'Owner', 'email' => 'va-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'marketer_campaigns.view', 'marketer_campaigns.create', 'finance.view', 'finance.payouts.view'] as $name) {
            $this->vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }

        $perms = ['vendors.assigned_only', 'marketer_campaigns.view', 'payouts.view', 'payouts.approve'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(array_slice($perms, 1));
    }

    private function acceptedPending(): MarketerCampaignInvitation
    {
        $svc = app(MarketerCampaignService::class);
        $campaign = F::campaign($this->s, [F::influencer($this->s)]);
        $svc->approveCampaign($campaign, $this->admin);
        $inv = $campaign->invitations()->sole();
        $svc->acceptInvitation($inv);

        return $inv->fresh();
    }

    /** Statement for the vendor containing one pending-fee line (a payout item is enough for the views). */
    private function statementWithFee(MarketerCampaignInvitation $inv): Payout
    {
        $bank = VendorBankAccount::create([
            'vendor_id' => $this->s->vendor->id, 'account_holder_name' => 'Test Vendor', 'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456', 'account_number_encrypted' => 'enc', 'currency' => 'AED',
            'is_primary' => true, 'verification_status' => 'verified',
        ]);
        $payout = Payout::create([
            'payout_number' => 'PO-2026-'.random_int(10000, 99999), 'vendor_id' => $this->s->vendor->id,
            'period_start' => now()->subWeek()->toDateString(), 'period_end' => now()->toDateString(),
            'gross_sales' => 5000, 'commission' => 0, 'refunds_deducted' => 0, 'chargebacks_deducted' => 0,
            'other_adjustments' => -1050, 'net_amount' => 3950, 'currency' => 'AED', 'status' => 'pending',
            'bank_account_id' => $bank->id, 'payout_method' => 'bank_transfer',
        ]);
        PayoutItem::create([
            'payout_id' => $payout->id, 'item_type' => 'influencer_fee', 'invitation_id' => $inv->id,
            'gross' => 0, 'commission' => 0, 'vat_amount' => 50, 'net' => -1050,
            'description' => 'رسوم مؤثر — Influencer fee — Test — Campaign',
        ]);
        $inv->update(['platform_fee_status' => 'paid', 'platform_fee_paid_via' => 'statement', 'platform_fee_paid_at' => now()]);

        return $payout;
    }

    public function test_partner_quote_endpoint_returns_fee_plus_vat(): void
    {
        $a = F::influencer($this->s);
        $b = F::influencer($this->s);

        $json = $this->actingAs($this->vendorAdmin, 'vendor')
            ->getJson(route('partner.listings.marketer-fee', ['listing_id' => $this->s->vendorListingFbn->id, 'marketer_ids' => [$a->id, $b->id]]))
            ->assertOk()
            ->json();

        $this->assertSame(2000, $json['total_fee']);
        $this->assertEquals(5, $json['vat_rate']);
        $this->assertSame(100, $json['total_fee_vat']);
        $this->assertSame(2100, $json['total_fee_with_vat']);
    }

    public function test_partner_create_page_shows_the_vat_rows_in_both_languages(): void
    {
        F::influencer($this->s);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->actingAs($this->vendorAdmin, 'vendor')
                ->get(route('partner.marketer-campaigns.create', $this->s->vendorListingFbn->id))
                ->assertOk()
                ->assertSee('data-testid="quote-vat-row"', false)
                ->assertSee(__('partner.marketer_campaigns.fees_incl_vat'))
                ->assertSee(__('partner.marketer_campaigns.vat_label'));
        }
    }

    public function test_partner_campaign_page_shows_fee_and_vat_per_invitation_and_the_total(): void
    {
        $inv = $this->acceptedPending();

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.show', $inv->campaign_id))
            ->assertOk()
            ->assertSee('data-testid="invitation-fee-vat"', false)
            ->assertSee('data-testid="campaign-fee-summary"', false)
            ->assertSee(__('partner.marketer_campaigns.fee_summary', ['fee' => '1,000', 'vat' => '50', 'total' => '1,050', 'currency' => 'AED']))
            ->assertSee(__('partner.marketer_campaigns.fee_status_pending'));
    }

    public function test_partner_finance_page_lists_the_fee_line_and_statement_page_breaks_down_vat(): void
    {
        $inv = $this->acceptedPending();

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.finance.transactions', ['type' => 'influencer_fees', 'date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee(__('partner.finance.influencer_fee'))
            ->assertSee(__('partner.finance.fee_unsettled'))
            ->assertSee('1,050.00');

        $payout = $this->statementWithFee($inv);

        $this->actingAs($this->vendorAdmin, 'vendor')
            ->get(route('partner.payouts.show', $payout->payout_number))
            ->assertOk()
            ->assertSee('id="influencer-fee-lines"', false)
            ->assertSee(__('partner.payouts.influencer_fees_title'))
            ->assertSee(__('partner.payouts.fee_excl_vat'))
            ->assertSee('1,050.00');
    }

    public function test_admin_payout_page_shows_the_influencer_fee_line_with_vat(): void
    {
        $payout = $this->statementWithFee($this->acceptedPending());

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.payouts.show', $payout->id))
                ->assertOk()
                ->assertSee(__('admin.payouts.influencer_fee'))
                ->assertSee('Influencer fee')
                ->assertSee(__('admin.payouts.influencer_fee_vat_note', ['fee' => 'AED 1,000.00', 'vat' => 'AED 50.00']));
        }
    }

    public function test_admin_campaign_page_shows_the_fee_vat(): void
    {
        $inv = $this->acceptedPending();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', $inv->campaign_id))
            ->assertOk()
            ->assertSee(__('partner.marketer_campaigns.vat_label'));
    }

    public function test_admin_cannot_mark_a_fee_paid_that_is_already_settled(): void
    {
        Permission::firstOrCreate(['name' => 'marketer_campaigns.approve', 'guard_name' => 'admin']);
        $this->admin->givePermissionTo('marketer_campaigns.approve');
        $inv = $this->acceptedPending();
        $inv->update(['platform_fee_status' => 'paid', 'platform_fee_paid_via' => 'statement']);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.marketer-campaigns.invitations.mark-fee-paid', [$inv->campaign_id, $inv->id]))
            ->assertStatus(422);
        $this->assertSame('statement', $inv->fresh()->platform_fee_paid_via);
    }
}
