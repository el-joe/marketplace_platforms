<?php

namespace Tests\Feature\Notifications;

use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\ShippingCompanySupervisor;
use App\Models\WarrantyClaim;
use App\Notifications\Admin\NewWarrantyClaimNotification as AdminNewWarrantyClaim;
use App\Notifications\Admin\PayoutBatchReadyForApproval;
use App\Notifications\Admin\WarrantyClaimVendorRepliedNotification;
use App\Notifications\NewAssignmentNotification;
use App\Notifications\Vendor\NewWarrantyClaimNotification as VendorNewWarrantyClaim;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * R12 (client retest 2026-11-24): notification/email buttons must open the right panel host — not the
 * API host root (url('/')), not a relative "/admin/..." path, and not a route that does not exist.
 */
class NotificationLinkTargetsTest extends TestCase
{
    public function test_new_assignment_email_opens_the_delivery_panel_for_riders_and_the_carrier_panel_for_supervisors(): void
    {
        $assignment = (new DeliveryAssignment)->forceFill(['id' => (string) Str::uuid()]);
        $notification = new NewAssignmentNotification($assignment);

        $this->assertSame(route('delivery.assignments.show', $assignment->id), $notification->toMail(new DeliveryAgent)->actionUrl);
        $this->assertSame(route('carrier.assignments.show', $assignment->id), $notification->toMail(new ShippingCompanySupervisor)->actionUrl);
    }

    public function test_warranty_claim_notifications_link_absolutely_to_the_admin_and_partner_panels(): void
    {
        $claim = (new WarrantyClaim)->forceFill(['id' => (string) Str::uuid(), 'claim_number' => 'WC-1']);

        $this->assertSame(route('admin.warranty-claims.show', $claim->id), (new AdminNewWarrantyClaim($claim))->notificationData(new \stdClass)['url']);
        $this->assertSame(route('admin.warranty-claims.show', $claim->id), (new WarrantyClaimVendorRepliedNotification($claim))->notificationData(new \stdClass)['url']);
        $this->assertSame(route('partner.warranty-claims.show', $claim->id), (new VendorNewWarrantyClaim($claim))->notificationData(new \stdClass)['url']);
    }

    public function test_marketer_payout_batch_notification_builds_a_link_to_the_admin_payouts_page(): void
    {
        $data = (new PayoutBatchReadyForApproval('marketer', 3, '2026-11-01', '2026-11-15'))->notificationData(new \stdClass);

        $this->assertSame(route('admin.payouts.index'), $data['url'] ?? $data['link'] ?? null);
    }
}
