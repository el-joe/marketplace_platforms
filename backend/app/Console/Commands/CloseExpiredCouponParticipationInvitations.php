<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Client feature request #3.2: once an open coupon participation invitation
 * either reaches max_participants approved participants or its
 * registration_deadline passes, this command:
 *   - marks it 'fulfilled' (max reached) or 'closed' (deadline passed,
 *     whether or not it has approved participants),
 *   - creates the linked coupon from the invitation's data if coupon_id was
 *     null (draft invitation), matching Admin\CouponService::create()'s
 *     platform-scope defaults,
 *   - links every approved participant into coupon_vendors/coupon_marketers
 *     for that coupon.
 *
 * Ambiguous/judgment call: the plan doesn't specify what coupon fields
 * (type/value/scope/validity window) a draft invitation should produce — a
 * minimal, safe platform-scope coupon is created here (0% inert value is
 * never valid, so this only fires when there's no linked coupon at all;
 * an admin who wants specific discount terms should create the coupon
 * up-front and set coupon_id on the invitation instead of leaving it null).
 */
class CloseExpiredCouponParticipationInvitations extends Command
{
    protected $signature = 'coupons:close-expired-participation-invitations';

    protected $description = 'Close/fulfill coupon participation invitations that hit max participants or passed their registration deadline, and link approved participants into coupon_vendors/coupon_marketers';

    public function handle(): int
    {
        $closed = 0;
        $fulfilled = 0;

        CouponParticipationInvitation::query()
            ->open()
            ->withCount('approvedRequests')
            ->chunkById(50, function ($invitations) use (&$closed, &$fulfilled) {
                foreach ($invitations as $invitation) {
                    $isFull = $invitation->approved_requests_count >= $invitation->max_participants;
                    $isExpired = $invitation->isExpired();

                    if (! $isFull && ! $isExpired) {
                        continue;
                    }

                    DB::transaction(function () use ($invitation, $isFull) {
                        $invitation = CouponParticipationInvitation::whereKey($invitation->id)->lockForUpdate()->first();
                        if (! $invitation || $invitation->status !== CouponParticipationInvitation::STATUS_OPEN) {
                            return; // already processed concurrently
                        }

                        $hasApproved = $invitation->approvedRequests()->exists();
                        $coupon = $invitation->coupon;

                        // No participants: nothing to create/activate, just close.
                        if ($hasApproved) {
                            $coupon ??= $this->createDraftCoupon($invitation);
                            if ($coupon) {
                                $this->linkApprovedParticipants($invitation, $coupon);
                            }
                            // Activate an admin-prepared coupon (a generated
                            // draft coupon stays inactive: value 0 needs admin terms).
                            if ($coupon && $invitation->coupon_id !== null && ! $coupon->is_active) {
                                $coupon->update(['is_active' => true]);
                            }
                        }

                        $invitation->update([
                            'coupon_id' => $coupon?->id,
                            'status' => $isFull
                                ? CouponParticipationInvitation::STATUS_FULFILLED
                                : CouponParticipationInvitation::STATUS_CLOSED,
                        ]);
                    });

                    $isFull ? $fulfilled++ : $closed++;
                }
            });

        $this->info("Fulfilled {$fulfilled} invitation(s), closed {$closed} invitation(s).");

        return self::SUCCESS;
    }

    private function createDraftCoupon(CouponParticipationInvitation $invitation): ?Coupon
    {
        // coupons.created_by_user_id is NOT NULL: use the invitation's creating admin.
        if ($invitation->created_by_admin_id === null) {
            $this->warn("Invitation {$invitation->id} has no coupon and no creating admin; closing without a coupon.");

            return null;
        }

        return Coupon::query()->create([
            'id' => Str::uuid()->toString(),
            'code' => strtoupper(Str::random(10)),
            'name' => $invitation->title ?? 'Coupon Participation Invitation',
            'type' => 'fixed_amount',
            'value' => 0,
            'currency' => $invitation->currency,
            'scope' => 'platform',
            'shipping_type_restriction' => 'all',
            'customer_eligibility' => 'all',
            'usage_limit_per_customer' => 1,
            'funded_by' => 'platform',
            'valid_from' => now(),
            'valid_until' => now()->addMonths(3),
            'is_active' => false,
            'created_by_user_id' => $invitation->created_by_admin_id,
        ]);
    }

    private function linkApprovedParticipants(CouponParticipationInvitation $invitation, Coupon $coupon): void
    {
        $approved = $invitation->requests()
            ->whereIn('status', [CouponParticipationRequest::STATUS_APPROVED, CouponParticipationRequest::STATUS_PAID])
            ->get();

        $vendorIds = $approved->where('participant_type', CouponParticipationRequest::TYPE_VENDOR)->pluck('participant_id')->all();
        $marketerIds = $approved->where('participant_type', CouponParticipationRequest::TYPE_MARKETER)->pluck('participant_id')->all();

        if (! empty($vendorIds)) {
            $coupon->vendors()->syncWithoutDetaching($vendorIds);
        }

        if (! empty($marketerIds)) {
            $coupon->marketers()->syncWithoutDetaching($marketerIds);
        }
    }
}
