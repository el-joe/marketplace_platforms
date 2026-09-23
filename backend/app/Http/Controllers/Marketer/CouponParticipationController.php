<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use App\Models\Marketer;
use App\Services\Admin\CouponParticipationInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Marketer-facing side of coupon participation invitations (client feature
 * request #3.2): browse open invitations and submit a request to
 * participate, optionally offering a higher fee than min_fee_amount.
 *
 * The fee is charged from the marketer's wallet when the admin approves.
 */
class CouponParticipationController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function index(): View
    {
        $marketer = $this->marketer();

        $invitations = CouponParticipationInvitation::query()
            ->open()
            ->where('registration_deadline', '>', now())
            ->withCount(['approvedRequests'])
            ->with(['requests' => fn ($q) => $q->where('participant_type', CouponParticipationRequest::TYPE_MARKETER)->where('participant_id', $marketer->id)])
            ->latest()
            ->paginate(20);

        return view('marketer.coupon-participation.index', compact('marketer', 'invitations'));
    }

    public function store(Request $request, CouponParticipationInvitation $invitation): RedirectResponse
    {
        $marketer = $this->marketer();

        app(CouponParticipationInvitationService::class)->submitRequest(
            $invitation,
            CouponParticipationRequest::TYPE_MARKETER,
            $marketer->id,
            $request->input('offered_fee_amount')
        );

        return back()->with('success', 'تم إرسال طلب المشاركة بنجاح.');
    }
}
