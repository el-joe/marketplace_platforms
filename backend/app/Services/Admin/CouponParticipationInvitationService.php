<?php

namespace App\Services\Admin;

use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use App\Models\Marketer;
use App\Models\Vendor;
use App\Notifications\CouponParticipationInvitationNotification;
use App\Notifications\CouponParticipationRequestDecisionNotification;

class CouponParticipationInvitationService
{
    /**
     * Notify every active vendor and marketer that a new coupon
     * participation invitation opened. Mirrors how MarketerCampaignService
     * notifies vendor admins on invitation events.
     */
    public function notifyNewInvitation(CouponParticipationInvitation $invitation): void
    {
        Vendor::query()->with('vendorAdmins')->chunk(200, function ($vendors) use ($invitation) {
            foreach ($vendors as $vendor) {
                $vendor->vendorAdmins?->each(
                    fn ($va) => $va->notify(new CouponParticipationInvitationNotification($invitation))
                );
            }
        });

        Marketer::query()->with('marketerAdmins')->chunk(200, function ($marketers) use ($invitation) {
            foreach ($marketers as $marketer) {
                $marketer->marketerAdmins?->each(
                    fn ($ma) => $ma->notify(new CouponParticipationInvitationNotification($invitation))
                );
            }
        });
    }

    public function notifyRequestDecision(CouponParticipationRequest $participationRequest): void
    {
        if ($participationRequest->participant_type === CouponParticipationRequest::TYPE_VENDOR) {
            $vendor = Vendor::with('vendorAdmins')->find($participationRequest->participant_id);
            $vendor?->vendorAdmins?->each(
                fn ($va) => $va->notify(new CouponParticipationRequestDecisionNotification($participationRequest))
            );

            return;
        }

        $marketer = Marketer::with('marketerAdmins')->find($participationRequest->participant_id);
        $marketer?->marketerAdmins?->each(
            fn ($ma) => $ma->notify(new CouponParticipationRequestDecisionNotification($participationRequest))
        );
    }
}
