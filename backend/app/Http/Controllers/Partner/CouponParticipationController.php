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
        abort_if(! Auth::guard('vendor')->user()->vendor->isProductVendor(), 403);

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

        $svc = app(CouponParticipationInvitationService::class);
        $balances = $invitations->getCollection()->mapWithKeys(fn ($i) => [$i->id => (int) $svc->walletFor('vendor', $vendorId, $i->currency)->balance]);

        return view('partner.coupon-participation.index', compact('invitations', 'myRequests', 'balances'));
    }

    public function store(Request $request, string $invitation): RedirectResponse
    {
        abort_if(! Auth::guard('vendor')->user()->vendor->isProductVendor(), 403);
        $model = CouponParticipationInvitation::findOrFail($invitation);

        app(CouponParticipationInvitationService::class)->submitRequest(
            $model,
            CouponParticipationRequest::TYPE_VENDOR,
            $this->vendorId(),
            $request->input('offered_fee_amount'),
            $request->input('payment_method', 'wallet'),
            $request->hasFile('bank_transfer_proof') ? $request->file('bank_transfer_proof')->store('coupon-participation-proofs', 'local') : null
        );

        return back()->with('success', 'تم إرسال طلب المشاركة بنجاح.');
    }
}
