<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryAgentEarningStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SubOrderStatus;
use App\Jobs\CustomerDeliveredNotificationJob;
use App\Jobs\NotifyCustomerFailedDeliveryJob;
use App\Jobs\NotifyOperationsTeamJob;
use App\Enums\DeliveryAgentShiftStatus;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentEarning;
use App\Models\DeliveryAgentShift;
use App\Models\DeliveryAssignment;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\ShipmentTrackingEvent;
use App\Notifications\Carrier\DeliveryCompleted as DeliveryCompletedNotification;
use App\Notifications\Carrier\DeliveryFailed as DeliveryFailedNotification;
use App\Notifications\Customer\OrderOutForDelivery;
use App\Notifications\Vendor\OrderReturnInTransit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AssignmentService
{
    private const CUMULATIVE_FAIL_LIMIT = 3;

    public function __construct(
        private readonly OtpVerificationService  $otpService,
        private readonly ProofOfDeliveryService  $proofService,
        private readonly RtoService              $rtoService,
    ) {}

    // ── accept ────────────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException with key 'concurrent_assignment' if agent already has
     *         an active (accepted/picked_up) assignment — one-at-a-time dispatch model.
     */
    public function accept(DeliveryAssignment $assignment, DeliveryAgent $agent): void
    {
        if ($assignment->status !== DeliveryAssignment::STATUS_ASSIGNED) {
            throw new \DomainException(__('common.exceptions.delivery.not_assigned_state'));
        }

        $hasActive = DeliveryAssignment::where('agent_id', $agent->id)
            ->whereIn('status', [
                DeliveryAssignment::STATUS_ACCEPTED,
                DeliveryAssignment::STATUS_PICKED_UP,
            ])
            ->exists();

        if ($hasActive) {
            throw new \RuntimeException('concurrent_assignment');
        }

        $assignment->update([
            'status'      => DeliveryAssignment::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    // ── pickup ────────────────────────────────────────────────────────────────

    public function pickup(DeliveryAssignment $assignment, float $latitude, float $longitude): void
    {
        if ($assignment->status !== DeliveryAssignment::STATUS_ACCEPTED) {
            throw new \DomainException(__('common.exceptions.delivery.not_accepted_state'));
        }

        DB::transaction(function () use ($assignment, $latitude, $longitude) {
            $assignment->update([
                'status'           => DeliveryAssignment::STATUS_PICKED_UP,
                'picked_up_at'     => now(),
                'pickup_latitude'  => $latitude,
                'pickup_longitude' => $longitude,
            ]);

            if ($assignment->shipment) {
                $assignment->shipment->update([
                    'status'        => 'picked_up',
                    'picked_up_at'  => now(),
                ]);

                ShipmentTrackingEvent::create([
                    'shipment_id'  => $assignment->shipment_id,
                    'status'       => 'picked_up',
                    'description'  => 'Package picked up by delivery agent.',
                    'location'     => "{$latitude},{$longitude}",
                    'occurred_at'  => now(),
                ]);
            }

            $assignment->subOrder?->update(['status' => 'out_for_delivery']);
        });

        $assignment->loadMissing('subOrder.order.customer');
        $customer = $assignment->subOrder?->order?->customer;
        if ($customer) {
            $customer->notify(new OrderOutForDelivery($assignment->subOrder, $assignment->delivery_otp));
        }
    }

    // ── verifyOtp ─────────────────────────────────────────────────────────────

    /**
     * Verify the customer-provided OTP code.
     * Returns true on match, false on mismatch.
     *
     * @throws \RuntimeException('otp_locked') when lockout is reached
     */
    public function verifyOtp(DeliveryAssignment $assignment, string $code): bool
    {
        if ($assignment->status !== DeliveryAssignment::STATUS_PICKED_UP) {
            throw new \DomainException(__('common.exceptions.delivery.not_picked_up_state'));
        }

        if ($this->otpService->isLocked($assignment)) {
            throw new \RuntimeException('otp_locked');
        }

        $matched = $this->otpService->verify($assignment, $code);

        if ($matched) {
            $assignment->update(['otp_verified' => true]);
        }

        return $matched;
    }

    // ── deliver ───────────────────────────────────────────────────────────────

    // Tolerance in cents below which a COD shortfall is silently accepted (rounding, small change).
    private const COD_DISCREPANCY_TOLERANCE_CENTS = 100;

    public function deliver(
        DeliveryAssignment $assignment,
        DeliveryAgent      $agent,
        string             $otpCode,
        UploadedFile       $proofImage,
        float              $latitude,
        float              $longitude,
        ?int               $codAmountCollectedCents,
        ?string            $discrepancyNote = null,
    ): void {
        if ($assignment->status !== DeliveryAssignment::STATUS_PICKED_UP) {
            throw new \DomainException(__('common.exceptions.delivery.not_picked_up_state'));
        }

        // Re-validate OTP (do not rely on the earlier /verify-otp call alone).
        if ($this->otpService->isLocked($assignment)) {
            throw new \RuntimeException('otp_locked');
        }

        $otpMatched = $this->otpService->verify($assignment, $otpCode);
        if (! $otpMatched) {
            throw new \RuntimeException('otp_invalid');
        }

        $assignment->load('subOrder.order');
        $order = $assignment->subOrder->order;
        $isCod = $order->payment_method === 'cod';

        if ($isCod && ($codAmountCollectedCents === null || $codAmountCollectedCents <= 0)) {
            throw new \DomainException('cod_amount_required');
        }

        $proofFileId = $this->proofService->store($assignment, $proofImage);

        // Detect COD shortfall before entering the transaction.
        $codDiscrepancyCents = 0;
        $hasDiscrepancy      = false;
        if ($isCod && $codAmountCollectedCents !== null) {
            $expectedCents       = (int) $order->total;
            $codDiscrepancyCents = $expectedCents - $codAmountCollectedCents;
            $hasDiscrepancy      = $codDiscrepancyCents > self::COD_DISCREPANCY_TOLERANCE_CENTS;

            if ($hasDiscrepancy && empty($discrepancyNote)) {
                throw new \DomainException('discrepancy_note_required');
            }
        }

        DB::transaction(function () use (
            $assignment, $agent, $order, $isCod,
            $latitude, $longitude, $proofFileId,
            $codAmountCollectedCents, $discrepancyNote, $hasDiscrepancy
        ) {
            $assignment->update([
                'status'                      => DeliveryAssignment::STATUS_DELIVERED,
                'delivered_at'                => now(),
                'otp_verified'                => true,
                'proof_file_id'               => $proofFileId,
                'delivery_latitude'           => $latitude,
                'delivery_longitude'          => $longitude,
                'cod_amount_collected'  => $isCod ? $codAmountCollectedCents : null,
                'discrepancy_note'            => ($isCod && $hasDiscrepancy) ? $discrepancyNote : null,
            ]);

            if ($assignment->shipment) {
                $assignment->shipment->update([
                    'status'       => 'delivered',
                    'delivered_at' => now(),
                ]);

                ShipmentTrackingEvent::create([
                    'shipment_id' => $assignment->shipment_id,
                    'status'      => 'delivered',
                    'description' => 'Package delivered successfully.',
                    'location'    => "{$latitude},{$longitude}",
                    'occurred_at' => now(),
                ]);
            }

            $assignment->subOrder->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            // Agent earns in their own country's currency, not the customer's payment currency.
            // (An agent in Egypt always earns EGP even if the customer paid in AED.)
            $agent->loadMissing('country');
            $currency = $agent->country?->currency_code
                ?? $agent->zone?->country?->currency_code
                ?? $order->currency
                ?? 'AED';

            // Base delivery fee for the agent.
            DeliveryAgentEarning::create([
                'agent_id'                => $agent->id,
                'delivery_assignment_id'  => $assignment->id,
                'order_id'                => $order->id,
                'earning_type'            => 'base_fee',
                'amount'            => $agent->per_delivery_fee ?? 0,
                'currency'                => $currency,
                'status'                  => DeliveryAgentEarningStatus::Pending,
            ]);

            // COD handling fee (separate from the cash physically collected).
            if ($isCod) {
                $codFee = $agent->zone?->cod_fee ?? 0;
                if ($codFee > 0) {
                    DeliveryAgentEarning::create([
                        'agent_id'                => $agent->id,
                        'delivery_assignment_id'  => $assignment->id,
                        'order_id'                => $order->id,
                        'earning_type'            => 'cod_handling',
                        'amount'            => $codFee,
                        'currency'                => $currency,
                        'status'                  => DeliveryAgentEarningStatus::Pending,
                    ]);
                }
            }

            // Start return window on order items.
            $assignment->subOrder->items()
                ->whereNull('return_eligible_until')
                ->update(['return_eligible_until' => now()->addDays(14)->toDateString()]);

            $todayShift = DeliveryAgentShift::where('agent_id', $agent->id)
                ->where('status', DeliveryAgentShiftStatus::Active)
                ->whereDate('shift_date', today())
                ->first();

            if ($todayShift) {
                $todayShift->increment('total_deliveries');
                $todayShift->increment(
                    'total_earnings',
                    ($agent->per_delivery_fee ?? 0) + ($isCod ? ($agent->zone?->cod_fee ?? 0) : 0)
                );
            }
        });

        CustomerDeliveredNotificationJob::dispatch($assignment->sub_order_id);

        $this->notifyCarrierSupervisors($assignment, new DeliveryCompletedNotification($assignment));
    }

    // ── fail ──────────────────────────────────────────────────────────────────

    public function fail(
        DeliveryAssignment $assignment,
        string             $failureReason,
        ?string            $failureNotes,
        float              $latitude,
        float              $longitude,
        ?string            $customerRejectionReason = null,
    ): void {
        if (! in_array($assignment->status, [
            DeliveryAssignment::STATUS_ACCEPTED,
            DeliveryAssignment::STATUS_PICKED_UP,
        ], true)) {
            throw new \DomainException(__('common.exceptions.delivery.cannot_be_failed'));
        }

        if ($failureReason === 'customer_refused') {
            $assignment->load('subOrder.order');
            $isElectronicPayment = $assignment->subOrder->order->payment_method !== 'cod';

            if ($isElectronicPayment && ! $customerRejectionReason) {
                throw new \DomainException('customer_rejection_reason_required');
            }
        }

        $isCodRefused = false;

        DB::transaction(function () use (
            $assignment, $failureReason, $failureNotes, $latitude, $longitude, $customerRejectionReason, &$isCodRefused
        ) {
            $assignment->load('subOrder.order');
            $order        = $assignment->subOrder->order;
            $isCustomerRefused = $failureReason === 'customer_refused';
            $isElectronicPayment = $order->payment_method !== 'cod';

            $assignment->update([
                'status'             => DeliveryAssignment::STATUS_FAILED,
                'failed_at'          => now(),
                'failure_reason'     => $failureReason,
                'failure_notes'      => $failureNotes,
                'delivery_latitude'  => $latitude,
                'delivery_longitude' => $longitude,
                'customer_rejection_reason' => $isCustomerRefused ? $customerRejectionReason : $assignment->customer_rejection_reason,
                'rejected_by_customer_at' => $isCustomerRefused ? now() : $assignment->rejected_by_customer_at,
                'rejection_reason_mandatory' => $isCustomerRefused ? $isElectronicPayment : $assignment->rejection_reason_mandatory,
            ]);

            if ($assignment->shipment) {
                $assignment->shipment->update(['status' => 'failed']);

                ShipmentTrackingEvent::create([
                    'shipment_id' => $assignment->shipment_id,
                    'status'      => 'failed',
                    'description' => match ($failureReason) {
                        'address_not_found' => 'Delivery failed: address could not be located.',
                        'customer_not_home' => 'Delivery failed: recipient not available.',
                        'customer_refused'  => 'Delivery failed: refused by recipient.',
                        'phone_unreachable' => 'Delivery failed: could not reach recipient by phone.',
                        'access_denied'     => 'Delivery failed: access to location denied.',
                        default             => 'Delivery attempt failed.',
                    },
                    'location'    => "{$latitude},{$longitude}",
                    'occurred_at' => now(),
                ]);
            }

            $cumulativeFails = DeliveryAssignment::where('shipment_id', $assignment->shipment_id)
                ->where('status', DeliveryAssignment::STATUS_FAILED)
                ->count();

            $isCodRefused = $isCustomerRefused
                && $order->payment_method === 'cod';

            $triggerRto = $isCodRefused || $cumulativeFails >= self::CUMULATIVE_FAIL_LIMIT;

            if ($triggerRto) {
                $this->rtoService->createReturnAssignment($assignment);
            }

            // Customer refused a COD order = implicit cancellation; no cash changed hands.
            if ($isCodRefused) {
                $order->update([
                    'status'       => OrderStatus::Cancelled,
                    'cancelled_at' => now(),
                    // payment_status stays 'pending' — nothing was collected.
                ]);

                $assignment->subOrder->update(['status' => SubOrderStatus::Cancelled]);

                PaymentTransaction::where('order_id', $order->id)
                    ->where('gateway', 'cod')
                    ->where('status', PaymentTransactionStatus::Pending)
                    ->update(['status' => PaymentTransactionStatus::Cancelled]);
            }
        });

        if ($isCodRefused) {
            $this->notifyVendorsOfReturn($assignment);
        }

        NotifyCustomerFailedDeliveryJob::dispatch($assignment->sub_order_id);
        NotifyOperationsTeamJob::dispatch($assignment->id);

        $this->notifyCarrierSupervisors($assignment, new DeliveryFailedNotification($assignment));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function notifyCarrierSupervisors(DeliveryAssignment $assignment, object $notification): void
    {
        $company = $assignment->agent?->shippingCompany;

        if (! $company) {
            return;
        }

        $supervisors = $company->supervisors()->receivingNotifications()->get();

        if ($supervisors->isNotEmpty()) {
            Notification::send($supervisors, $notification);
        }
    }

    private function notifyVendorsOfReturn(DeliveryAssignment $assignment): void
    {
        $assignment->loadMissing('subOrder.items.vendor');

        $vendors = $assignment->subOrder->items
            ->map(fn($item) => $item->vendor)
            ->filter()
            ->unique('id');

        if ($vendors->isNotEmpty()) {
            Notification::send($vendors, new OrderReturnInTransit($assignment->subOrder));
        }
    }
}
