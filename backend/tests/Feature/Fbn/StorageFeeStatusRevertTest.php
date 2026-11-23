<?php

namespace Tests\Feature\Fbn;

use App\Enums\FbnStorageFeeStatus;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\FbnStorageFee;
use App\Models\Payout;
use App\Models\VendorBankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client item #9: storage fee status moves forward (Mark Invoiced / Mark Paid)
 * and back (revert, permission + reason), every change in the activity log, and
 * a fee already deducted in a vendor payout is never returned to pending.
 */
class StorageFeeStatusRevertTest extends TestCase
{
    use RefreshDatabase;

    private const REVERT = 'fbn_storage_fees.revert_status';

    private MarketplaceScenario $scenario;

    private Admin $finance;

    private Admin $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();

        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => self::REVERT, 'guard_name' => 'admin']);

        $this->finance = Admin::factory()->create();
        $this->finance->givePermissionTo([self::REVERT, 'fbn_storage_fees.view', 'fbn_storage_fees.manage']);
        $this->operator = Admin::factory()->create();
        $this->operator->givePermissionTo(['fbn_storage_fees.view', 'fbn_storage_fees.manage']);
    }

    private function fee(FbnStorageFeeStatus $status, array $attributes = []): FbnStorageFee
    {
        return FbnStorageFee::create($attributes + [
            'vendor_id' => $this->scenario->vendor->id,
            'warehouse_inventory_id' => $this->scenario->vendorListingFbnInventory->id,
            'month' => now()->subMonths(random_int(1, 120))->startOfMonth()->toDateString(),
            'units_stored' => 4,
            'rate_per_unit' => 100,
            'total_fee' => 12.5,
            'currency' => 'AED',
            'status' => $status,
        ]);
    }

    private function revert(Admin $admin, FbnStorageFee $fee, ?string $reason = 'Marked by mistake'): TestResponse
    {
        return $this->actingAs($admin, 'admin')
            ->postJson(route('admin.fbn.storage-fees.revert-status', $fee), ['reason' => $reason]);
    }

    private function payout(): Payout
    {
        $account = VendorBankAccount::create([
            'vendor_id' => $this->scenario->vendor->id,
            'account_holder_name' => 'Test Vendor',
            'bank_name' => 'Test Bank',
            'iban' => 'AE070331234567890123456',
            'account_number_encrypted' => 'encrypted-account-number',
            'currency' => 'AED',
            'is_primary' => true,
            'verification_status' => 'verified',
        ]);

        return Payout::create([
            'payout_number' => 'PO-'.Str::upper(Str::random(8)),
            'vendor_id' => $this->scenario->vendor->id,
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_sales' => 100,
            'commission' => 10,
            'refunds_deducted' => 0,
            'chargebacks_deducted' => 0,
            'storage_fees' => 12.5,
            'net_amount' => 77.5,
            'currency' => 'AED',
            'status' => 'pending',
            'bank_account_id' => $account->id,
            'payout_method' => 'bank_transfer',
        ]);
    }

    public function test_mark_invoiced_and_paid_are_logged_with_actor_and_statuses(): void
    {
        $fee = $this->fee(FbnStorageFeeStatus::Pending);
        $url = route('admin.fbn.storage-fees.status', $fee);

        $this->actingAs($this->operator, 'admin')->postJson($url, ['status' => 'invoiced'])->assertOk();
        $this->actingAs($this->operator, 'admin')->postJson($url, ['status' => 'paid'])->assertOk();

        $fee->refresh();
        $this->assertSame(FbnStorageFeeStatus::Paid, $fee->status);
        $this->assertNotNull($fee->invoiced_at);
        $this->assertNotNull($fee->paid_at);

        $events = Activity::where('subject_id', $fee->id)->orderBy('id')->get();
        $this->assertSame(['storage_fee_invoiced', 'storage_fee_paid'], $events->pluck('event')->all());
        $this->assertSame($this->operator->id, (string) $events[0]->causer_id);
        $this->assertSame('pending', $events[0]->properties['from_status']);
        $this->assertSame('invoiced', $events[0]->properties['to_status']);
    }

    public function test_skipping_a_step_is_refused(): void
    {
        $fee = $this->fee(FbnStorageFeeStatus::Pending);

        $this->actingAs($this->operator, 'admin')
            ->postJson(route('admin.fbn.storage-fees.status', $fee), ['status' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('admin.fbn_section.status_change_invalid'));

        $this->assertSame(FbnStorageFeeStatus::Pending, $fee->fresh()->status);
    }

    public function test_paid_reverts_to_invoiced_then_pending_with_reason_in_the_activity_log(): void
    {
        $fee = $this->fee(FbnStorageFeeStatus::Paid, ['invoiced_at' => now(), 'paid_at' => now()]);

        $this->revert($this->finance, $fee, 'Bank transfer bounced')->assertOk()->assertJsonPath('status', 'invoiced');
        $this->assertNull($fee->fresh()->paid_at);

        $this->revert($this->finance, $fee, 'Invoice sent to wrong vendor')->assertOk()->assertJsonPath('status', 'pending');
        $this->assertSame(FbnStorageFeeStatus::Pending, $fee->fresh()->status);

        $reverts = Activity::where('subject_id', $fee->id)->where('event', 'storage_fee_status_reverted')->orderBy('id')->get();
        $this->assertCount(2, $reverts);
        $this->assertSame($this->finance->id, (string) $reverts[1]->causer_id);
        $this->assertSame(['invoiced', 'pending', 'Invoice sent to wrong vendor'], [
            $reverts[1]->properties['from_status'],
            $reverts[1]->properties['to_status'],
            $reverts[1]->properties['reason'],
        ]);
    }

    public function test_reverting_requires_the_permission(): void
    {
        $fee = $this->fee(FbnStorageFeeStatus::Paid);

        $this->revert($this->operator, $fee)->assertForbidden();

        $this->assertSame(FbnStorageFeeStatus::Paid, $fee->fresh()->status);
    }

    public function test_reverting_requires_a_reason(): void
    {
        $fee = $this->fee(FbnStorageFeeStatus::Paid);

        $this->revert($this->finance, $fee, '')->assertJsonValidationErrors('reason');

        $this->assertSame(FbnStorageFeeStatus::Paid, $fee->fresh()->status);
    }

    public function test_a_pending_fee_cannot_be_reverted(): void
    {
        $this->revert($this->finance, $this->fee(FbnStorageFeeStatus::Pending))->assertUnprocessable();
    }

    public function test_a_fee_deducted_in_a_payout_is_not_returned_to_pending(): void
    {
        $payout = $this->payout();
        $fee = $this->fee(FbnStorageFeeStatus::Invoiced, ['payout_id' => $payout->id, 'invoiced_at' => now()]);

        $this->revert($this->finance, $fee)
            ->assertUnprocessable()
            ->assertJsonPath('message', __('admin.fbn_section.revert_blocked_payout', ['payout' => $payout->payout_number]));

        $this->assertSame(FbnStorageFeeStatus::Invoiced, $fee->fresh()->status);
        $this->assertFalse(Activity::where('subject_id', $fee->id)->exists());
    }

    public function test_a_legacy_invoiced_fee_is_not_returned_to_pending_when_a_later_payout_deducted_storage_fees(): void
    {
        $fee = $this->fee(FbnStorageFeeStatus::Invoiced);
        $this->payout();

        $this->revert($this->finance, $fee)->assertUnprocessable();

        $this->assertSame(FbnStorageFeeStatus::Invoiced, $fee->fresh()->status);
    }

    public function test_a_manually_invoiced_fee_returns_to_pending(): void
    {
        $fee = $this->fee(FbnStorageFeeStatus::Invoiced, ['invoiced_at' => now()]);

        $this->revert($this->finance, $fee)->assertOk();

        $this->assertSame(FbnStorageFeeStatus::Pending, $fee->fresh()->status);
        $this->assertNull($fee->fresh()->invoiced_at);
    }

    public function test_revert_buttons_are_only_offered_to_admins_with_the_permission(): void
    {
        $this->fee(FbnStorageFeeStatus::Paid);
        $url = route('admin.fbn.storage-fees.datatable');
        $params = ['draw' => 1, 'start' => 0, 'length' => 10];

        $withPermission = $this->actingAs($this->finance, 'admin')->postJson($url, $params)->assertOk();
        $this->assertStringContainsString('btn-revert-fee', $withPermission->json('data.0.actions'));
        $this->assertStringContainsString(e(__('admin.fbn_section.revert_to_invoiced')), $withPermission->json('data.0.actions'));

        $withoutPermission = $this->actingAs($this->operator, 'admin')->postJson($url, $params)->assertOk();
        $this->assertStringNotContainsString('btn-revert-fee', (string) $withoutPermission->json('data.0.actions'));
    }
}
