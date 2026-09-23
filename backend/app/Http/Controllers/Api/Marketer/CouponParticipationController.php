<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use App\Models\Marketer;
use App\Services\Admin\CouponParticipationInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponParticipationController extends Controller
{
    private function marketer(): Marketer
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    public function index(): JsonResponse
    {
        $m = $this->marketer();
        $svc = app(CouponParticipationInvitationService::class);

        $page = CouponParticipationInvitation::query()->open()
            ->where('registration_deadline', '>', now())
            ->withCount('approvedRequests')
            ->with(['coupon', 'requests' => fn ($q) => $q->where('participant_type', CouponParticipationRequest::TYPE_MARKETER)->where('participant_id', $m->id)])
            ->latest()->paginate(20);

        $page->getCollection()->transform(function ($i) use ($m, $svc) {
            $my = $i->requests->first();

            return [
                'id' => $i->id, 'title' => $i->title, 'description' => $i->description,
                'max_participants' => $i->max_participants, 'approved_count' => $i->approved_requests_count,
                'min_fee_amount' => (int) $i->min_fee_amount, 'currency' => $i->currency,
                'registration_deadline' => $i->registration_deadline,
                'coupon' => $i->coupon ? ['code' => $i->coupon->code, 'name' => $i->coupon->name,
                    'discount' => ['type' => $i->coupon->type?->value ?? $i->coupon->type, 'value' => $i->coupon->value]] : null,
                'my_request' => $my ? ['status' => $my->status, 'offered_fee' => (int) $my->offered_fee_amount] : null,
                'wallet_balance' => (int) $svc->walletFor('marketer', $m->id, $i->currency)->balance,
            ];
        });

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function store(Request $request, string $invitation): JsonResponse
    {
        $inv = CouponParticipationInvitation::findOrFail($invitation);
        $request->validate([
            'offered_fee_amount' => ['required', 'integer', 'min:' . (int) $inv->min_fee_amount],
            'payment_method' => ['required', 'in:wallet,bank_transfer'],
            'bank_transfer_proof' => ['nullable', 'file', 'max:5120'],
        ]);

        // Service re-validates state/duplicates/balance (throws ValidationException -> 422).
        $req = app(CouponParticipationInvitationService::class)->submitRequest(
            $inv, CouponParticipationRequest::TYPE_MARKETER, $this->marketer()->id,
            $request->input('offered_fee_amount'), $request->input('payment_method'),
            $request->hasFile('bank_transfer_proof') ? $request->file('bank_transfer_proof')->store('coupon-participation-proofs', 'local') : null
        );

        return response()->json([
            'success' => true, 'message' => 'تم إرسال طلبك بنجاح.',
            'data' => ['request_id' => $req->id, 'status' => $req->status],
        ], 201);
    }
}
