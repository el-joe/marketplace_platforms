<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use App\Services\Admin\CouponParticipationInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Vendor web panel for coupon participation invitations (client feature
 * request #3.2). Same rules as the vendor API and marketer panel, via
 * CouponParticipationInvitationService::submitRequest().
 */
class CouponParticipationController extends Controller
{
    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    public function index(): View
    {
        $vendorId = $this->vendorId();

        $invitations = CouponParticipationInvitation::query()
            ->open()
            ->where('registration_deadline', '>', now())
            ->withCount('approvedRequests')
            ->with(['requests' => fn ($q) => $q->where('participant_type', CouponParticipationRequest::TYPE_VENDOR)->where('participant_id', $vendorId)])
            ->latest()
            ->paginate(20);

        $myRequests = CouponParticipationRequest::query()
            ->where('participant_type', CouponParticipationRequest::TYPE_VENDOR)
            ->where('participant_id', $vendorId)
            ->with('invitation')
            ->latest()
            ->paginate(20, ['*'], 'requests_page');

        return view('partner.coupon-participation.index', compact('invitations', 'myRequests'));
    }

    public function store(Request $request, string $invitation): RedirectResponse
    {
        $model = CouponParticipationInvitation::findOrFail($invitation);

        app(CouponParticipationInvitationService::class)->submitRequest(
            $model,
            CouponParticipationRequest::TYPE_VENDOR,
            $this->vendorId(),
            $request->input('offered_fee_amount')
        );

        return back()->with('success', 'تم إرسال طلب المشاركة بنجاح.');
    }
}
