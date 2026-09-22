<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use App\Services\Admin\CouponParticipationInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin CRUD for coupon participation invitations, plus the request
 * review queue (approve/reject) — client feature request #3.2.
 *
 * NOTE — payment: "mark as paid" here is a manual admin action, not a real
 * payment-gateway/wallet integration (needs a further check against the
 * platform's billing system, per the plan — see class doc on
 * CouponParticipationRequest).
 */
class CouponParticipationInvitationController extends Controller
{
    public function index(): View
    {
        $invitations = CouponParticipationInvitation::query()
            ->withCount(['requests', 'approvedRequests'])
            ->latest()
            ->paginate(20);

        return view('admin.coupon-participation-invitations.index', [
            'invitations' => $invitations,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.coupons'), 'url' => route('admin.coupons.index')],
                ['label' => 'دعوات مشاركة القسائم'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.coupon-participation-invitations.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.coupons'), 'url' => route('admin.coupons.index')],
                ['label' => 'دعوة جديدة'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'coupon_id' => ['nullable', 'uuid', 'exists:coupons,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_participants' => ['required', 'integer', 'min:1'],
            'min_fee_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'registration_deadline' => ['required', 'date', 'after:now'],
        ]);

        $data['id'] = Str::uuid()->toString();
        $data['status'] = CouponParticipationInvitation::STATUS_OPEN;
        $data['created_by_admin_id'] = Auth::guard('admin')->id();

        $invitation = CouponParticipationInvitation::create($data);

        // Client feature #3.2: notify vendors/marketers that a new
        // participation invitation opened.
        app(CouponParticipationInvitationService::class)->notifyNewInvitation($invitation);

        return redirect()->route('admin.coupon-participation-invitations.show', $invitation->id)
            ->with('success', 'تم إنشاء الدعوة بنجاح.');
    }

    public function show(string $invitation): View
    {
        $model = CouponParticipationInvitation::with(['coupon', 'requests' => fn ($q) => $q->latest()])->findOrFail($invitation);

        $requests = $model->requests->map(function (CouponParticipationRequest $r) {
            $participant = $r->participant();

            return [
                'model' => $r,
                'participant_name' => $participant?->name ?? $participant?->store_name ?? '—',
            ];
        });

        return view('admin.coupon-participation-invitations.show', [
            'invitation' => $model,
            'requests' => $requests,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.coupons'), 'url' => route('admin.coupons.index')],
                ['label' => $model->title ?? $model->id],
            ],
        ]);
    }

    public function cancel(string $invitation): RedirectResponse
    {
        $model = CouponParticipationInvitation::findOrFail($invitation);
        $model->update(['status' => CouponParticipationInvitation::STATUS_CANCELLED]);

        return back()->with('success', 'تم إلغاء الدعوة.');
    }

    public function approveRequest(string $invitation, string $request): JsonResponse
    {
        $model = CouponParticipationInvitation::findOrFail($invitation);
        $participationRequest = CouponParticipationRequest::where('invitation_id', $model->id)->findOrFail($request);

        if ($model->isFull()) {
            return response()->json(['message' => 'اكتمل عدد المشاركين المقبولين بالفعل.'], 422);
        }

        $participationRequest->update(['status' => CouponParticipationRequest::STATUS_APPROVED]);

        app(CouponParticipationInvitationService::class)->notifyRequestDecision($participationRequest);

        return response()->json(['success' => true]);
    }

    public function rejectRequest(string $invitation, string $request): JsonResponse
    {
        $model = CouponParticipationInvitation::findOrFail($invitation);
        $participationRequest = CouponParticipationRequest::where('invitation_id', $model->id)->findOrFail($request);

        $participationRequest->update(['status' => CouponParticipationRequest::STATUS_REJECTED]);

        app(CouponParticipationInvitationService::class)->notifyRequestDecision($participationRequest);

        return response()->json(['success' => true]);
    }

    /**
     * Manual "mark as paid" stub — NOT a real payment integration. See
     * class doc block and CouponParticipationRequest for the TODO.
     */
    public function markRequestPaid(string $invitation, string $request): JsonResponse
    {
        $model = CouponParticipationInvitation::findOrFail($invitation);
        $participationRequest = CouponParticipationRequest::where('invitation_id', $model->id)->findOrFail($request);

        if ($participationRequest->status !== CouponParticipationRequest::STATUS_APPROVED) {
            return response()->json(['message' => 'لا يمكن تحديد الطلب كمدفوع إلا بعد قبوله.'], 422);
        }

        $participationRequest->update([
            'status' => CouponParticipationRequest::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
