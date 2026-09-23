<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Coupon;
use App\Models\CouponParticipationInvitation as Inv;
use App\Models\CouponParticipationRequest as Req;
use App\Models\MarketerAdmin;
use App\Models\VendorAdmin;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CouponParticipationInvitationTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->vendor->forceFill(['onboarding_completed_at' => now()])->save();
    }

    private function invitation(array $o = []): Inv
    {
        return Inv::create(array_merge([
            'title' => 'T', 'max_participants' => 2, 'min_fee_amount' => 100, 'currency' => 'AED',
            'registration_deadline' => now()->addDay(), 'status' => Inv::STATUS_OPEN,
        ], $o));
    }

    private function fund(string $type, string $id, int $amount): void
    {
        $svc = app(WalletService::class);
        $svc->credit($svc->getOrCreateWallet($type, $id, 'AED'), $amount, 'test', null, 'seed');
    }

    private function balance(string $type, string $id): int
    {
        return (int) app(WalletService::class)->getOrCreateWallet($type, $id, 'AED')->fresh()->balance;
    }

    private function marketerAdmin(): MarketerAdmin
    {
        return MarketerAdmin::create([
            'marketer_id' => $this->s->marketer->id, 'name' => 'M', 'email' => 'm-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function admin(): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'coupons.view', 'guard_name' => 'admin']);
        $a = Admin::factory()->create();
        $a->givePermissionTo('coupons.view');

        return $a;
    }

    private function makeReq(Inv $i, string $type, string $id, int $fee = 100): Req
    {
        return Req::create(['invitation_id' => $i->id, 'participant_type' => $type, 'participant_id' => $id,
            'offered_fee_amount' => $fee, 'status' => 'pending']);
    }

    // ── Marketer web apply validation ──

    public function test_marketer_can_apply_and_duplicates_low_fee_expired_are_rejected(): void
    {
        $inv = $this->invitation();
        $ma = $this->marketerAdmin();
        $url = route('marketer.coupon-participation.store', $inv->id);

        $this->actingAs($ma, 'marketer')->post($url, ['offered_fee_amount' => 50])->assertSessionHasErrors('offered_fee_amount');
        $this->assertDatabaseCount('coupon_participation_requests', 0);

        $this->actingAs($ma, 'marketer')->post($url, ['offered_fee_amount' => 150])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupon_participation_requests', ['invitation_id' => $inv->id, 'offered_fee_amount' => 150, 'status' => 'pending']);

        $this->actingAs($ma, 'marketer')->post($url, ['offered_fee_amount' => 150])->assertSessionHasErrors('invitation');
        $this->assertDatabaseCount('coupon_participation_requests', 1);

        $expired = $this->invitation(['registration_deadline' => now()->subHour()]);
        $this->actingAs($ma, 'marketer')->post(route('marketer.coupon-participation.store', $expired->id), ['offered_fee_amount' => 150])
            ->assertSessionHasErrors('invitation');

        $this->actingAs($ma, 'marketer')->get(route('marketer.coupon-participation.index'))->assertOk();
    }

    public function test_full_invitation_rejects_new_requests(): void
    {
        $inv = $this->invitation(['max_participants' => 1]);
        $r = $this->makeReq($inv, 'vendor', $this->s->vendor->id);
        $r->update(['status' => 'approved']);

        $this->actingAs($this->marketerAdmin(), 'marketer')
            ->post(route('marketer.coupon-participation.store', $inv->id), ['offered_fee_amount' => 100])
            ->assertSessionHasErrors('invitation');
    }

    // ── Vendor web + API ──

    public function test_vendor_web_panel_lists_and_applies(): void
    {
        $inv = $this->invitation();
        $va = VendorAdmin::create([
            'vendor_id' => $this->s->vendor->id, 'name' => 'V', 'email' => 'v-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);

        $this->actingAs($va, 'vendor')->get(route('partner.coupon-participation.index'))->assertOk()->assertSee('T');
        $this->actingAs($va, 'vendor')->post(route('partner.coupon-participation.store', $inv->id), ['offered_fee_amount' => 99])
            ->assertSessionHasErrors('offered_fee_amount');
        $this->actingAs($va, 'vendor')->post(route('partner.coupon-participation.store', $inv->id), ['offered_fee_amount' => 100])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupon_participation_requests', ['participant_type' => 'vendor', 'participant_id' => $this->s->vendor->id]);
    }

    // ── Admin approve / reject / wallet ──

    public function test_approve_charges_wallet_once_and_reject_refunds(): void
    {
        $inv = $this->invitation();
        $vid = $this->s->vendor->id;
        $r = $this->makeReq($inv, 'vendor', $vid, 300);
        $this->fund('vendor', $vid, 1000);
        $admin = $this->admin();

        $url = route('admin.coupon-participation-invitations.requests.approve', [$inv->id, $r->id]);
        $this->actingAs($admin, 'admin')->postJson($url)->assertOk();
        $this->assertSame(700, $this->balance('vendor', $vid));
        $this->assertSame('paid', $r->fresh()->status);

        // idempotent: second approve refused, no double charge
        $this->actingAs($admin, 'admin')->postJson($url)->assertStatus(422);
        $this->assertSame(700, $this->balance('vendor', $vid));

        $this->actingAs($admin, 'admin')->postJson(route('admin.coupon-participation-invitations.requests.reject', [$inv->id, $r->id]))->assertOk();
        $this->assertSame(1000, $this->balance('vendor', $vid));
        $this->assertSame('rejected', $r->fresh()->status);

        $this->actingAs($admin, 'admin')->postJson(route('admin.coupon-participation-invitations.requests.reject', [$inv->id, $r->id]))->assertStatus(422);
        $this->assertSame(1000, $this->balance('vendor', $vid));
    }

    public function test_approve_fails_with_insufficient_balance_and_leaves_pending(): void
    {
        $inv = $this->invitation();
        $r = $this->makeReq($inv, 'vendor', $this->s->vendor->id, 300);

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.coupon-participation-invitations.requests.approve', [$inv->id, $r->id]))
            ->assertStatus(422);
        $this->assertSame('pending', $r->fresh()->status);
    }

    public function test_reject_pending_needs_no_refund_and_approve_respects_max(): void
    {
        $inv = $this->invitation(['max_participants' => 1]);
        $a = $this->makeReq($inv, 'vendor', $this->s->vendor->id);
        $b = $this->makeReq($inv, 'marketer', $this->s->marketer->id);
        $this->fund('vendor', $this->s->vendor->id, 500);
        $this->fund('marketer', $this->s->marketer->id, 500);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson(route('admin.coupon-participation-invitations.requests.approve', [$inv->id, $a->id]))->assertOk();
        $this->actingAs($admin, 'admin')->postJson(route('admin.coupon-participation-invitations.requests.approve', [$inv->id, $b->id]))->assertStatus(422);
        $this->actingAs($admin, 'admin')->postJson(route('admin.coupon-participation-invitations.requests.reject', [$inv->id, $b->id]))->assertOk();
        $this->assertSame(500, $this->balance('marketer', $this->s->marketer->id));
    }

    // ── Command ──

    public function test_command_fulfils_when_full_and_links_participants_and_activates_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'CPI'.Str::random(5), 'name' => 'c', 'type' => 'fixed_amount', 'value' => 10, 'currency' => 'AED',
            'scope' => 'platform', 'shipping_type_restriction' => 'all', 'customer_eligibility' => 'all',
            'usage_limit_per_customer' => 1, 'funded_by' => 'platform', 'valid_from' => now(), 'valid_until' => now()->addMonth(),
            'is_active' => false, 'created_by_user_id' => $this->admin()->id,
        ]);
        $inv = $this->invitation(['max_participants' => 2, 'coupon_id' => $coupon->id]);
        $this->makeReq($inv, 'vendor', $this->s->vendor->id)->update(['status' => 'paid']);
        $this->makeReq($inv, 'marketer', $this->s->marketer->id)->update(['status' => 'approved']);

        $this->artisan('coupons:close-expired-participation-invitations')->assertSuccessful();

        $this->assertSame('fulfilled', $inv->fresh()->status);
        $coupon->refresh();
        $this->assertTrue((bool) $coupon->is_active);
        $this->assertSame([$this->s->vendor->id], $coupon->vendors()->pluck('vendors.id')->all());
        $this->assertSame([$this->s->marketer->id], $coupon->marketers()->pluck('marketers.id')->all());
    }

    public function test_command_closes_expired_and_skips_open_partial(): void
    {
        $open = $this->invitation(['max_participants' => 5]);
        $this->makeReq($open, 'vendor', $this->s->vendor->id)->update(['status' => 'approved']);
        $expired = $this->invitation(['registration_deadline' => now()->subMinute()]);
        $this->makeReq($expired, 'vendor', $this->s->vendor->id)->update(['status' => 'rejected']);

        $this->artisan('coupons:close-expired-participation-invitations')->assertSuccessful();

        $this->assertSame('open', $open->fresh()->status);
        $this->assertSame('closed', $expired->fresh()->status);
        $this->assertNull($expired->fresh()->coupon_id); // no approved participants: no coupon created
    }

    public function test_command_creates_inactive_draft_coupon_when_expired_with_participants(): void
    {
        $inv = $this->invitation(['registration_deadline' => now()->subMinute(), 'created_by_admin_id' => $this->admin()->id]);
        $this->makeReq($inv, 'vendor', $this->s->vendor->id)->update(['status' => 'paid']);

        $this->artisan('coupons:close-expired-participation-invitations')->assertSuccessful();

        $inv->refresh();
        $this->assertSame('closed', $inv->status);
        $this->assertNotNull($inv->coupon_id);
        $this->assertFalse((bool) $inv->coupon->is_active);
        $this->assertCount(1, $inv->coupon->vendors);
    }
}
