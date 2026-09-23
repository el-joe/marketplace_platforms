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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin CRUD for coupon participation invitations, plus the request
 * review queue (approve/reject) — client feature request #3.2.
 *
 * Payment: approving a request debits offered_fee_amount from the
 * participant's wallet (WalletService) and sets status=paid; rejecting a paid
 * request refunds it. "mark as paid" remains only for legacy 'approved' rows
 * (offline settlement).
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
                ['label' => __('admin.coupon_participation_section.cps_title')],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.coupon-participation-invitations.create', [
            'coupons' => \App\Models\Coupon::where('is_active', false)->where('value', '>', 0)->orderBy('code')->get(['id', 'code', 'value']),
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.coupons'), 'url' => route('admin.coupons.index')],
                ['label' => __('admin.coupon_participation_section.cps_new')],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'coupon_id' => ['required', 'uuid', 'exists:coupons,id', function ($attr, $value, $fail) {
                $coupon = \App\Models\Coupon::find($value);
                if (! $coupon) {
                    return $fail(__('admin.coupon_participation_section.coupon_not_found'));
                }
                if ($coupon->is_active) {
                    return $fail(__('admin.coupon_participation_section.coupon_must_be_inactive'));
                }
                if ($coupon->value <= 0) {
                    return $fail(__('admin.coupon_participation_section.coupon_value_zero'));
                }
            }],
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
            ->with('success', __('admin.coupon_participation_section.cps_created'));
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

        return back()->with('success', __('admin.coupon_participation_section.cps_cancelled'));
    }

    public function approveRequest(string $invitation, string $request): JsonResponse
    {
        $model = CouponParticipationInvitation::findOrFail($invitation);
        $participationRequest = CouponParticipationRequest::where('invitation_id', $model->id)->findOrFail($request);

        try {
            app(CouponParticipationInvitationService::class)->approve($participationRequest, Auth::guard('admin')->id());
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['success' => true]);
    }

    public function rejectRequest(string $invitation, string $request): JsonResponse
    {
        $model = CouponParticipationInvitation::findOrFail($invitation);
        $participationRequest = CouponParticipationRequest::where('invitation_id', $model->id)->findOrFail($request);

        try {
            app(CouponParticipationInvitationService::class)->reject($participationRequest, Auth::guard('admin')->id(), request()->boolean('refund_on_reject', true));
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

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

        try {
            app(CouponParticipationInvitationService::class)->confirmPayment($participationRequest, Auth::guard('admin')->id());
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['success' => true]);
    }
}
