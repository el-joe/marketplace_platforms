<?php

namespace App\Services\Admin;

use App\Models\CouponParticipationInvitation;
use App\Models\CouponParticipationRequest;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Marketer;
use App\Models\Vendor;
use App\Notifications\CouponParticipationInvitationNotification;
use App\Notifications\CouponParticipationRequestDecisionNotification;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponParticipationInvitationService
{
    /**
     * Notify every active vendor and marketer that a new coupon
     * participation invitation opened. Mirrors how MarketerCampaignService
     * notifies vendor admins on invitation events.
     */
    public function notifyNewInvitation(CouponParticipationInvitation $invitation): void
    {
        Vendor::query()->where('vendor_type', 'product_vendor')->with('vendorAdmins')->chunk(200, function ($vendors) use ($invitation) {
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
            if (! $vendor?->isProductVendor()) {
                return;
            }
            $vendor->vendorAdmins?->each(
                fn ($va) => $va->notify(new CouponParticipationRequestDecisionNotification($participationRequest))
            );

            return;
        }

        $marketer = Marketer::with('marketerAdmins')->find($participationRequest->participant_id);
        $marketer?->marketerAdmins?->each(
            fn ($ma) => $ma->notify(new CouponParticipationRequestDecisionNotification($participationRequest))
        );
    }

    public function walletFor(string $type, string $participantId, string $currency): \App\Models\Wallet
    {
        return app(WalletService::class)->getOrCreateWallet($type, $participantId, $currency);
    }

    /**
     * Single validation/creation path shared by the vendor API, marketer web
     * and vendor web: invitation open + not expired + not full, no duplicate,
     * offered fee >= min_fee_amount.
     *
     * @throws ValidationException
     */
    public function submitRequest(CouponParticipationInvitation $invitation, string $type, string $participantId, mixed $fee, string $paymentMethod = 'wallet', ?string $proofPath = null): CouponParticipationRequest
    {
        if ($invitation->status !== CouponParticipationInvitation::STATUS_OPEN || $invitation->isExpired()) {
            throw ValidationException::withMessages(['invitation' => 'الدعوة لم تعد مفتوحة.']);
        }
        if ($invitation->isFull()) {
            throw ValidationException::withMessages(['invitation' => 'اكتمل عدد المشاركين في هذه الدعوة.']);
        }
        if (! is_numeric($fee) || (int) $fee != $fee || (int) $fee < $invitation->min_fee_amount) {
            throw ValidationException::withMessages(['offered_fee_amount' => 'الرسوم المعروضة يجب أن تكون رقمًا صحيحًا لا يقل عن '.$invitation->min_fee_amount.'.']);
        }

        if (! in_array($paymentMethod, ['wallet', 'bank_transfer'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'طريقة الدفع غير صالحة.']);
        }
        if ($paymentMethod === 'wallet') {
            $balance = (int) $this->walletFor($type, $participantId, $invitation->currency)->balance;
            if ($balance < (int) $fee) {
                throw ValidationException::withMessages(['error' => 'insufficient_balance']);
            }
        } elseif ($proofPath === null) {
            throw ValidationException::withMessages(['bank_transfer_proof' => 'إثبات التحويل البنكي مطلوب.']);
        }

        $exists = CouponParticipationRequest::where('invitation_id', $invitation->id)
            ->where('participant_type', $type)->where('participant_id', $participantId)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['invitation' => 'لقد أرسلت طلب مشاركة في هذه الدعوة بالفعل.']);
        }

        return CouponParticipationRequest::create([
            'invitation_id' => $invitation->id,
            'participant_type' => $type,
            'participant_id' => $participantId,
            'offered_fee_amount' => (int) $fee,
            'payment_method' => $paymentMethod,
            'bank_transfer_proof_path' => $proofPath,
            'status' => CouponParticipationRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Approve a pending request and charge offered_fee_amount from the
     * participant's wallet (invitation currency). Transactional + idempotent:
     * only a 'pending' row (locked) can be approved; on success it becomes
     * 'paid'. Insufficient balance aborts the whole approval.
     *
     * @throws ValidationException
     */
    public function approve(CouponParticipationRequest $participationRequest, ?string $adminId = null): CouponParticipationRequest
    {
        $result = DB::transaction(function () use ($participationRequest, $adminId) {
            $req = CouponParticipationRequest::whereKey($participationRequest->id)->lockForUpdate()->firstOrFail();
            $invitation = CouponParticipationInvitation::whereKey($req->invitation_id)->lockForUpdate()->firstOrFail();

            if ($req->status !== CouponParticipationRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['request' => 'الطلب تمت معالجته بالفعل.']);
            }
            if ($invitation->status !== CouponParticipationInvitation::STATUS_OPEN) {
                throw ValidationException::withMessages(['request' => 'الدعوة لم تعد مفتوحة.']);
            }
            if ($invitation->isFull()) {
                throw ValidationException::withMessages(['request' => 'اكتمل عدد المشاركين المقبولين بالفعل.']);
            }

            if ($req->payment_method === 'bank_transfer') {
                // Awaiting admin confirmation of the transfer proof.
                $req->update(['status' => CouponParticipationRequest::STATUS_APPROVED, 'reviewed_by_admin_id' => $adminId]);

                return $req;
            }

            $wallets = app(WalletService::class);
            $wallet = $wallets->getOrCreateWallet($req->participant_type, $req->participant_id, $invitation->currency);
            try {
                $wallets->debit($wallet, $req->offered_fee_amount, 'coupon_participation_request', (string) $req->id,
                    'Coupon participation fee', $adminId);
            } catch (InsufficientBalanceException $e) {
                throw ValidationException::withMessages(['request' => 'رصيد محفظة المشارك غير كافٍ لسداد رسوم الاشتراك.']);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['request' => $e->getMessage()]);
            }

            $req->update(['status' => CouponParticipationRequest::STATUS_PAID, 'paid_at' => now(), 'reviewed_by_admin_id' => $adminId]);
            $this->onPaid($req, $invitation);

            return $req;
        });

        $this->notifyRequestDecision($result);

        return $result;
    }

    /**
     * Admin confirms a bank-transfer payment for an approved request.
     *
     * @throws ValidationException
     */
    public function confirmPayment(CouponParticipationRequest $participationRequest, ?string $adminId = null): CouponParticipationRequest
    {
        return DB::transaction(function () use ($participationRequest, $adminId) {
            $req = CouponParticipationRequest::whereKey($participationRequest->id)->lockForUpdate()->firstOrFail();
            $invitation = CouponParticipationInvitation::whereKey($req->invitation_id)->lockForUpdate()->firstOrFail();
            if ($req->status !== CouponParticipationRequest::STATUS_APPROVED) {
                throw ValidationException::withMessages(['request' => 'لا يمكن تحديد الطلب كمدفوع إلا بعد قبوله.']);
            }
            $req->update(['status' => CouponParticipationRequest::STATUS_PAID, 'paid_at' => now(), 'reviewed_by_admin_id' => $adminId]);
            $this->onPaid($req, $invitation);

            return $req;
        });
    }

    /** Link participant to the coupon and fulfil the invitation when full. */
    private function onPaid(CouponParticipationRequest $req, CouponParticipationInvitation $invitation): void
    {
        $coupon = $invitation->coupon;
        if ($coupon) {
            if ($req->participant_type === CouponParticipationRequest::TYPE_VENDOR) {
                $coupon->vendors()->syncWithoutDetaching([$req->participant_id]);
            } else {
                $coupon->marketers()->syncWithoutDetaching([$req->participant_id]);
            }
        }
        if ($invitation->isFull()) {
            $invitation->update(['status' => CouponParticipationInvitation::STATUS_FULFILLED]);
            $coupon?->update(['is_active' => true]);
        }
    }

    /**
     * Reject a request; refunds the fee to the wallet if it was already paid.
     * Idempotent (rejected rows are refused under lock).
     *
     * @throws ValidationException
     */
    public function reject(CouponParticipationRequest $participationRequest, ?string $adminId = null, bool $refund = true): CouponParticipationRequest
    {
        $result = DB::transaction(function () use ($participationRequest, $adminId, $refund) {
            $req = CouponParticipationRequest::whereKey($participationRequest->id)->lockForUpdate()->firstOrFail();

            if ($req->status === CouponParticipationRequest::STATUS_REJECTED) {
                throw ValidationException::withMessages(['request' => 'الطلب مرفوض بالفعل.']);
            }

            if ($refund && $req->status === CouponParticipationRequest::STATUS_PAID && $req->payment_method === 'wallet') {
                $invitation = CouponParticipationInvitation::findOrFail($req->invitation_id);
                $wallets = app(WalletService::class);
                $wallet = $wallets->getOrCreateWallet($req->participant_type, $req->participant_id, $invitation->currency);
                $wallets->credit($wallet, $req->offered_fee_amount, 'coupon_participation_refund', (string) $req->id,
                    'Coupon participation fee refund', $adminId);
            }

            $req->update(['status' => CouponParticipationRequest::STATUS_REJECTED, 'paid_at' => null, 'reviewed_by_admin_id' => $adminId]);

            return $req;
        });

        $this->notifyRequestDecision($result);

        return $result;
    }
}
