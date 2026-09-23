<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use App\Models\VendorAdmin;
use App\Services\Admin\CouponParticipationInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Vendor-facing side of coupon participation invitations (client feature
 * request #3.2): a vendor browses open invitations and submits a request to
 * participate, optionally offering a higher fee than min_fee_amount.
 *
 * Payment is NOT integrated yet — requests are created with status='pending'
 * (see CouponParticipationRequest doc block for details).
 */
class CouponParticipationController extends Controller
{
    private function actor(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    /**
     * Open invitations the vendor hasn't already requested to join.
     */
    public function index(): JsonResponse
    {
        abort_if(! $this->actor()->vendor->isProductVendor(), 403);
        $vendorId = $this->actor()->vendor_id;

        $invitations = CouponParticipationInvitation::query()
            ->open()
            ->where('registration_deadline', '>', now())
            ->withCount(['approvedRequests'])
            ->with(['requests' => fn ($q) => $q->where('participant_type', CouponParticipationRequest::TYPE_VENDOR)->where('participant_id', $vendorId)])
            ->latest()
            ->paginate(20);

        return ApiResponse::success($invitations->through(function (CouponParticipationInvitation $invitation) {
            return [
                'id' => $invitation->id,
                'title' => $invitation->title,
                'description' => $invitation->description,
                'max_participants' => $invitation->max_participants,
                'approved_participants' => $invitation->approved_requests_count,
                'min_fee_amount' => $invitation->min_fee_amount,
                'currency' => $invitation->currency,
                'registration_deadline' => $invitation->registration_deadline,
                'my_request' => $invitation->requests->first(),
            ];
        }));
    }

    public function store(Request $request, string $invitation): JsonResponse
    {
        $vendor = $this->actor()->vendor;
        abort_if(! $vendor->isProductVendor(), 403);
        $invitationModel = CouponParticipationInvitation::findOrFail($invitation);

        try {
            $participationRequest = app(CouponParticipationInvitationService::class)->submitRequest(
                $invitationModel,
                CouponParticipationRequest::TYPE_VENDOR,
                $vendor->id,
                $request->input('offered_fee_amount'),
                $request->input('payment_method', 'wallet'),
                $request->hasFile('bank_transfer_proof') ? $request->file('bank_transfer_proof')->store('coupon-participation-proofs', 'local') : null
            );
        } catch (ValidationException $e) {
            if (isset($e->errors()['error'])) {
                $balance = (int) app(CouponParticipationInvitationService::class)->walletFor(CouponParticipationRequest::TYPE_VENDOR, $vendor->id, $invitationModel->currency)->balance;

                return response()->json(['error' => 'insufficient_balance', 'balance' => $balance], 422);
            }

            return ApiResponse::error(collect($e->errors())->flatten()->first(), [], 422);
        }

        return ApiResponse::success($participationRequest, __('Participation request submitted.'), 201);
    }

    /**
     * This vendor's own participation requests, across all invitations.
     */
    public function myRequests(): JsonResponse
    {
        $vendorId = $this->actor()->vendor_id;

        $requests = CouponParticipationRequest::query()
            ->where('participant_type', CouponParticipationRequest::TYPE_VENDOR)
            ->where('participant_id', $vendorId)
            ->with('invitation')
            ->latest()
            ->paginate(20);

        return ApiResponse::success($requests);
    }
}
