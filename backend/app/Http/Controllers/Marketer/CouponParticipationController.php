<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use App\Models\Marketer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Marketer-facing side of coupon participation invitations (client feature
 * request #3.2): browse open invitations and submit a request to
 * participate, optionally offering a higher fee than min_fee_amount.
 *
 * Payment is NOT integrated yet — requests are created with status='pending'
 * (see CouponParticipationRequest doc block for details).
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

        abort_unless($invitation->status === CouponParticipationInvitation::STATUS_OPEN && ! $invitation->isExpired(), 422, 'الدعوة لم تعد مفتوحة.');
        abort_if($invitation->isFull(), 422, 'اكتمل عدد المشاركين في هذه الدعوة.');

        $alreadyRequested = CouponParticipationRequest::query()
            ->where('invitation_id', $invitation->id)
            ->where('participant_type', CouponParticipationRequest::TYPE_MARKETER)
            ->where('participant_id', $marketer->id)
            ->exists();

        abort_if($alreadyRequested, 422, 'لقد أرسلت طلب مشاركة في هذه الدعوة بالفعل.');

        $validated = $request->validate([
            'offered_fee_amount' => ['required', 'integer', 'min:'.$invitation->min_fee_amount],
        ]);

        CouponParticipationRequest::create([
            'invitation_id' => $invitation->id,
            'participant_type' => CouponParticipationRequest::TYPE_MARKETER,
            'participant_id' => $marketer->id,
            'offered_fee_amount' => $validated['offered_fee_amount'],
            'status' => CouponParticipationRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'تم إرسال طلب المشاركة بنجاح.');
    }
}
