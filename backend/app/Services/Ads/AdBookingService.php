<?php

namespace App\Services\Ads;

use App\Enums\PaidAdAdvertiserType;
use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdCreativeStatus;
use App\Enums\PaidAdPaymentMethod;
use App\Enums\PaidAdPaymentStatus;
use App\Enums\PaidAdSlotPricingModel;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Admin;
use App\Models\Marketer;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCharge;
use App\Models\PaidAdSlot;
use App\Models\Vendor;
use App\Notifications\Ads\AdBookingApprovedNotification;
use App\Notifications\Ads\AdBookingCancelledNotification;
use App\Notifications\Ads\AdBookingCompletedNotification;
use App\Notifications\Ads\AdBookingExpiredNotification;
use App\Notifications\Ads\AdBookingLiveNotification;
use App\Notifications\Ads\AdBookingPausedNotification;
use App\Notifications\Ads\AdBookingRejectedNotification;
use App\Notifications\Ads\AdBookingSubmittedNotification;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdBookingService
{
    public function __construct(
        private readonly AdSlotAvailabilityService $availabilityService,
        private readonly AdSlotQuoteService $quoteService,
        private readonly AdBillingService $billingService,
        private readonly PaidAdResolver $resolver,
    ) {
    }

    public function createDraft(
        PaidAdSlot $slot,
        Vendor|Marketer $advertiser,
        Carbon $from,
        Carbon $to,
        ?int $budget,
        string $paymentMethod,
        ?Admin $onBehalf = null,
    ): PaidAdBooking {
        return DB::transaction(function () use ($slot, $advertiser, $from, $to, $budget, $paymentMethod, $onBehalf) {
            $advertiserType = $advertiser instanceof Vendor ? PaidAdAdvertiserType::Vendor : PaidAdAdvertiserType::Marketer;

            if (! $slot->allowsAdvertiser($advertiserType->value)) {
                throw new DomainException(__('ads.errors.advertiser_not_allowed'));
            }
            if (! $slot->is_available) {
                throw new DomainException(__('ads.errors.slot_unavailable', ['date' => $from->toDateString()]));
            }
            if ($advertiser->country_id !== $slot->country_id) {
                throw new DomainException(__('ads.errors.country_mismatch'));
            }

            $method = PaidAdPaymentMethod::from($paymentMethod);
            $this->assertPaymentMethodAllowed($advertiserType, $method, $onBehalf);

            $quote = $this->quoteService->quote($slot, $from, $to, $budget);

            $reference = $this->generateBookingReference();

            return PaidAdBooking::create([
                'booking_reference' => $reference,
                'paid_ad_slot_id' => $slot->id,
                'advertiser_type' => $advertiserType->value,
                'vendor_id' => $advertiserType === PaidAdAdvertiserType::Vendor ? $advertiser->id : null,
                'marketer_id' => $advertiserType === PaidAdAdvertiserType::Marketer ? $advertiser->id : null,
                'country_id' => $slot->country_id,
                'pricing_model' => $quote['pricing_model'],
                'pricing_units' => $quote['units'],
                'unit_rate' => $quote['unit_rate'],
                'agreed_rate' => $quote['unit_rate'],
                'quoted_amount' => $quote['subtotal'],
                'tax_amount' => $quote['tax_amount'],
                'budget_amount' => $quote['budget_amount'],
                'booked_from' => $quote['booked_from'],
                'booked_until' => $quote['booked_until'],
                'currency' => $quote['currency'],
                'status' => PaidAdBookingStatus::Draft->value,
                'payment_status' => PaidAdPaymentStatus::Unpaid->value,
                'payment_method' => $method->value,
                'created_by_admin_id' => $onBehalf?->id,
            ]);
        });
    }

    public function submit(PaidAdBooking $b): void
    {
        DB::transaction(function () use ($b) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, PaidAdBookingStatus::PendingReview);

            $creative = $b->creatives()->where('status', '!=', PaidAdCreativeStatus::Rejected->value)->first();
            if (! $creative) {
                throw new DomainException(__('ads.errors.creative_required'));
            }

            $slot = PaidAdSlot::whereKey($b->paid_ad_slot_id)->lockForUpdate()->first();
            $this->availabilityService->assertAvailable($slot, $b->booked_from, $b->booked_until, $b->id);

            $quote = $this->quoteService->quote($slot, $b->booked_from, $b->booked_until, $b->budget_amount);
            $b->update([
                'pricing_units' => $quote['units'],
                'unit_rate' => $quote['unit_rate'],
                'quoted_amount' => $quote['subtotal'],
                'tax_amount' => $quote['tax_amount'],
                'status' => PaidAdBookingStatus::PendingReview->value,
                'submitted_at' => now(),
            ]);

            if ($b->payment_method === PaidAdPaymentMethod::Wallet) {
                $this->assertSoftWalletBalance($b);
            }

            $this->resolver->bust($b->country_id);

            $autoApproved = ! $slot->requires_approval && $creative->status === PaidAdCreativeStatus::Approved;

            if ($autoApproved) {
                $this->approve($b, null);
            } else {
                DB::afterCommit(fn () => AdBookingRecipients::reviewers()->each(
                    fn ($admin) => $admin->notify(new AdBookingSubmittedNotification($b))
                ));
            }
        });
    }

    public function approve(PaidAdBooking $b, ?Admin $admin): void
    {
        DB::transaction(function () use ($b, $admin) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, PaidAdBookingStatus::Approved);

            $slot = PaidAdSlot::whereKey($b->paid_ad_slot_id)->lockForUpdate()->first();
            $this->availabilityService->assertAvailable($slot, $b->booked_from, $b->booked_until, $b->id);

            $b->update([
                'status' => PaidAdBookingStatus::Approved->value,
                'approved_by_admin_id' => $admin?->id,
                'approved_at' => now(),
                'payment_due_at' => now()->addHours(config('ads.payment_window_hours', 48)),
            ]);

            $this->resolver->bust($b->country_id);

            try {
                $this->billingService->collect($b);
                $this->activateOrSchedule($b->fresh());
            } catch (InsufficientBalanceException) {
                $this->notifyApproved($b, 'payment_due');
            }
        });
    }

    public function pay(PaidAdBooking $b): void
    {
        DB::transaction(function () use ($b) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();

            if ($b->status !== PaidAdBookingStatus::Approved || $b->payment_status !== PaidAdPaymentStatus::Unpaid) {
                throw new DomainException(__('ads.errors.transition_not_allowed', [
                    'from' => $b->status->value, 'to' => 'paid',
                ]));
            }

            $this->billingService->collect($b);
            $this->activateOrSchedule($b->fresh());
        });
    }

    public function activateOrSchedule(PaidAdBooking $b): void
    {
        DB::transaction(function () use ($b) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();

            $paid = in_array($b->payment_status, [PaidAdPaymentStatus::Paid, PaidAdPaymentStatus::Reserved], true);
            $creativeApproved = $b->currentCreative && $b->currentCreative->status === PaidAdCreativeStatus::Approved;

            if (! $paid || ! $creativeApproved) {
                return;
            }

            $today = Carbon::now($b->country->timezone ?? 'UTC')->startOfDay();
            $bookedFrom = Carbon::parse($b->booked_from)->startOfDay();

            $target = $today->gte($bookedFrom) ? PaidAdBookingStatus::Active : PaidAdBookingStatus::Scheduled;

            if (! $b->status->canTransitionTo($target)) {
                return;
            }

            $wasApproved = $b->status === PaidAdBookingStatus::Approved;

            $b->update([
                'status' => $target->value,
                'started_at' => $target === PaidAdBookingStatus::Active ? now() : $b->started_at,
            ]);

            $this->resolver->bust($b->country_id);

            if ($wasApproved) {
                $this->notifyApproved($b, $target === PaidAdBookingStatus::Active ? 'live' : 'scheduled');
            } elseif ($target === PaidAdBookingStatus::Active) {
                DB::afterCommit(fn () => AdBookingRecipients::advertiserAdmins($b)->each(
                    fn ($admin) => $admin->notify(new AdBookingLiveNotification($b))
                ));
            }
        });
    }

    public function reject(PaidAdBooking $b, Admin $admin, string $reason): void
    {
        DB::transaction(function () use ($b, $admin, $reason) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, PaidAdBookingStatus::Rejected);

            $charged = PaidAdCharge::where('paid_ad_booking_id', $b->id)->sum('amount');
            if ($charged > 0) {
                $this->billingService->refund($b, $charged, "Booking {$b->booking_reference} rejected", $admin);
            }

            $b->update([
                'status' => PaidAdBookingStatus::Rejected->value,
                'rejection_reason' => $reason,
                'rejected_by_admin_id' => $admin->id,
                'rejected_at' => now(),
            ]);

            $this->resolver->bust($b->country_id);

            DB::afterCommit(fn () => AdBookingRecipients::advertiserAdmins($b)->each(
                fn ($admin) => $admin->notify(new AdBookingRejectedNotification($b, $reason))
            ));
        });
    }

    public function pause(PaidAdBooking $b, Admin $admin, string $reason): void
    {
        DB::transaction(function () use ($b, $admin, $reason) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, PaidAdBookingStatus::Paused);

            $b->update([
                'status' => PaidAdBookingStatus::Paused->value,
                'paused_at' => now(),
                'notes' => trim(($b->notes ?? '')."\nPaused by admin {$admin->id}: {$reason}"),
            ]);

            $this->resolver->bust($b->country_id);

            DB::afterCommit(fn () => AdBookingRecipients::advertiserAdmins($b)->each(
                fn ($admin) => $admin->notify(new AdBookingPausedNotification($b, $reason))
            ));
        });
    }

    public function resume(PaidAdBooking $b, Admin $admin): void
    {
        DB::transaction(function () use ($b) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();

            $today = Carbon::now($b->country->timezone ?? 'UTC')->startOfDay();
            $bookedFrom = Carbon::parse($b->booked_from)->startOfDay();
            $target = $today->gte($bookedFrom) ? PaidAdBookingStatus::Active : PaidAdBookingStatus::Scheduled;

            $this->assertTransition($b, $target);

            $b->update(['status' => $target->value]);

            $this->resolver->bust($b->country_id);
        });
    }

    public function complete(PaidAdBooking $b, string $why): void
    {
        DB::transaction(function () use ($b, $why) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, PaidAdBookingStatus::Completed);

            $pricingModel = PaidAdSlotPricingModel::from($b->pricing_model);
            if (! $pricingModel->isFixed() && $b->payment_method === PaidAdPaymentMethod::Wallet) {
                $unspent = max(0, $b->budget_amount - $b->total_charged);
                if ($unspent > 0) {
                    $this->billingService->refund($b, $unspent, "Booking {$b->booking_reference} completed ({$why}): unspent budget");
                }
            }

            $b->update([
                'status' => PaidAdBookingStatus::Completed->value,
                'completed_at' => now(),
            ]);

            $this->resolver->bust($b->country_id);

            DB::afterCommit(fn () => AdBookingRecipients::advertiserAdmins($b)->each(
                fn ($admin) => $admin->notify(new AdBookingCompletedNotification($b))
            ));
        });
    }

    public function expire(PaidAdBooking $b, string $why): void
    {
        DB::transaction(function () use ($b, $why) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, PaidAdBookingStatus::Expired);

            $charged = PaidAdCharge::where('paid_ad_booking_id', $b->id)->sum('amount');
            if ($charged > 0) {
                $this->billingService->refund($b, $charged, "Booking {$b->booking_reference} expired ({$why})");
            }

            $b->update(['status' => PaidAdBookingStatus::Expired->value]);

            $this->resolver->bust($b->country_id);

            DB::afterCommit(fn () => AdBookingRecipients::advertiserAdmins($b)->each(
                fn ($admin) => $admin->notify(new AdBookingExpiredNotification($b))
            ));
        });
    }

    public function cancel(PaidAdBooking $b, string $by, string $reason, ?Admin $admin = null): void
    {
        DB::transaction(function () use ($b, $by, $reason, $admin) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();

            $advertiserCancellableStatuses = [
                PaidAdBookingStatus::Draft,
                PaidAdBookingStatus::PendingReview,
                PaidAdBookingStatus::Approved,
                PaidAdBookingStatus::Scheduled,
            ];

            if ($by !== 'admin') {
                if (! in_array($b->status, $advertiserCancellableStatuses, true)) {
                    throw new DomainException(__('ads.errors.cancel_not_allowed'));
                }
            } elseif ($b->status->isTerminal()) {
                throw new DomainException(__('ads.errors.cancel_not_allowed'));
            }

            $refundAmount = $this->computeCancelRefund($b);
            if ($refundAmount > 0) {
                $this->billingService->refund($b, $refundAmount, "Booking {$b->booking_reference} cancelled by {$by}: {$reason}", $admin);
            }

            $b->update([
                'status' => PaidAdBookingStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancelled_by' => $by,
                'cancellation_reason' => $reason,
            ]);

            $this->resolver->bust($b->country_id);

            DB::afterCommit(function () use ($b, $by, $refundAmount) {
                $recipients = AdBookingRecipients::advertiserAdmins($b);
                if ($by === 'advertiser') {
                    $recipients = $recipients->concat(AdBookingRecipients::reviewers())->unique('id');
                }
                $recipients->each(fn ($admin) => $admin->notify(new AdBookingCancelledNotification($b, $refundAmount)));
            });
        });
    }

    public function markOfflinePaid(PaidAdBooking $b, Admin $admin, ?string $note): void
    {
        DB::transaction(function () use ($b, $admin, $note) {
            $b = PaidAdBooking::whereKey($b->id)->lockForUpdate()->firstOrFail();

            PaidAdCharge::create([
                'paid_ad_booking_id' => $b->id,
                'advertiser_type' => $b->advertiser_type->value,
                'vendor_id' => $b->vendor_id,
                'marketer_id' => $b->marketer_id,
                'country_id' => $b->country_id,
                'currency' => $b->currency,
                'type' => 'fixed',
                'amount' => $b->quoted_amount + $b->tax_amount,
                'tax_amount' => $b->tax_amount,
                'settlement' => 'offline',
                'settled_at' => now(),
                'note' => $note,
                'created_by_admin_id' => $admin->id,
            ]);

            $b->update([
                'payment_status' => PaidAdPaymentStatus::Paid->value,
                'paid_at' => now(),
                'total_charged' => $b->quoted_amount,
            ]);

            $this->activateOrSchedule($b->fresh());
        });
    }

    private function computeCancelRefund(PaidAdBooking $b): int
    {
        $charged = PaidAdCharge::where('paid_ad_booking_id', $b->id)->sum('amount');
        if ($charged <= 0) {
            return 0;
        }

        if (! in_array($b->status, [PaidAdBookingStatus::Active, PaidAdBookingStatus::Paused], true)) {
            return $charged;
        }

        $pricingModel = PaidAdSlotPricingModel::from($b->pricing_model);

        if ($pricingModel->isFixed()) {
            $totalDays = Carbon::parse($b->booked_from)->diffInDays(Carbon::parse($b->booked_until)) + 1;
            $elapsedDays = Carbon::parse($b->booked_from)->diffInDays(Carbon::today()) + 1;
            $remainingDays = max(0, min($totalDays, $totalDays - $elapsedDays));

            return intdiv($b->quoted_amount * $remainingDays, $totalDays);
        }

        return max(0, $b->budget_amount - $b->total_charged);
    }

    private function assertTransition(PaidAdBooking $b, PaidAdBookingStatus $to): void
    {
        if (! $b->status->canTransitionTo($to)) {
            throw new DomainException(__('ads.errors.transition_not_allowed', [
                'from' => $b->status->value,
                'to' => $to->value,
            ]));
        }
    }

    private function assertPaymentMethodAllowed(PaidAdAdvertiserType $advertiserType, PaidAdPaymentMethod $method, ?Admin $onBehalf): void
    {
        if ($onBehalf) {
            return; // admin on-behalf may use offline (or any method already validated by controller)
        }

        $allowed = $advertiserType === PaidAdAdvertiserType::Marketer
            ? [PaidAdPaymentMethod::Wallet]
            : [PaidAdPaymentMethod::Wallet, PaidAdPaymentMethod::PayoutDeduction];

        if (! in_array($method, $allowed, true)) {
            throw new DomainException(__('ads.errors.payment_method_not_allowed'));
        }
    }

    private function assertSoftWalletBalance(PaidAdBooking $b): void
    {
        $ownerType = $b->advertiser_type->value === 'marketer' ? 'marketer' : 'vendor';
        $ownerId = $b->advertiser_type->value === 'marketer' ? $b->marketer_id : $b->vendor_id;

        $wallet = app(\App\Services\WalletService::class)->getOrCreateWallet($ownerType, $ownerId, $b->currency);

        $isFixed = PaidAdSlotPricingModel::from($b->pricing_model)->isFixed();
        $required = $isFixed ? ($b->quoted_amount + $b->tax_amount) : $b->budget_amount;

        if ($wallet->balance < $required) {
            $shortfall = $required - $wallet->balance;
            throw new DomainException(__('ads.errors.insufficient_balance', [
                'shortfall' => $shortfall,
                'currency' => $b->currency,
            ]));
        }
    }

    /** @param string $outcome one of: scheduled, live, payment_due */
    private function notifyApproved(PaidAdBooking $b, string $outcome): void
    {
        DB::afterCommit(fn () => AdBookingRecipients::advertiserAdmins($b)->each(
            fn ($admin) => $admin->notify(new AdBookingApprovedNotification($b, $outcome))
        ));
    }

    private function generateBookingReference(): string
    {
        do {
            $reference = 'ADB-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (PaidAdBooking::where('booking_reference', $reference)->exists());

        return $reference;
    }
}
