<?php

namespace App\Services;

use App\Enums\FlashSaleStatus;
use App\Enums\VendorGlobalStatus;
use App\Enums\VendorListingStatus;
use App\Jobs\FlashSaleInviteBulkJob;
use App\Jobs\SubmissionApprovedNotificationJob;
use App\Jobs\SubmissionRejectedNotificationJob;
use App\Models\Admin;
use App\Models\FlashSale;
use App\Models\FlashSaleSubmission;
use App\Models\FlashSaleSubmissionHistory;
use App\Models\FlashSaleVendorInvitition;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

use App\Services\FakeDiscountDetectionService;

class FlashSaleService
{
    // ─────────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────────

    public function create(array $data, Admin $admin): FlashSale
    {
        $this->validateTimeline($data);

        return FlashSale::create([
            ...$data,
            'status' => FlashSaleStatus::Draft->value,
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
            'approved_slots_count' => 0,
            'eligible_categories' => $data['eligible_categories'] ?? null,
            'eligible_seller_tiers' => $data['eligible_seller_tiers'] ?? null,
        ]);
    }

    public function update(FlashSale $sale, array $data, Admin $admin): FlashSale
    {
        if (in_array($sale->status?->value, [FlashSaleStatus::Live->value, FlashSaleStatus::Ended->value, FlashSaleStatus::Cancelled->value], true)) {
            throw new \LogicException(__('admin.flash_sales.cannot_update_status', ['status' => $sale->status?->value]));
        }

        $sale->update([
            ...$data,
            'updated_by_admin_id' => $admin->id,
        ]);

        return $sale->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status transitions
    // ─────────────────────────────────────────────────────────────────────────

    public function transition(FlashSale $sale, string $newStatus, Admin $admin, string $reason = ''): FlashSale
    {
        if (!$sale->canTransitionTo($newStatus)) {
            throw new \LogicException(__('admin.flash_sales.cannot_transition', ['from' => $sale->status?->value, 'to' => $newStatus]));
        }

        $updates = [
            'status' => $newStatus,
            'updated_by_admin_id' => $admin->id,
        ];

        if ($newStatus === 'cancelled') {
            $updates['cancelled_at'] = now();
            $updates['cancellation_reason'] = $reason;
        }

        $sale->update($updates);
        return $sale->fresh();
    }

    public function openSubmissions(FlashSale $sale, Admin $admin): FlashSale
    {
        $sale = $this->transition($sale, 'submission_open', $admin);
        $this->inviteEligibleVendors($sale);
        return $sale;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Vendor invitations
    // ─────────────────────────────────────────────────────────────────────────

    public function inviteEligibleVendors(FlashSale $sale): int
    {
        $query = $this->buildEligibleVendorQuery($sale);

        $alreadyInvited = FlashSaleVendorInvitition::where('flash_sale_id', $sale->id)->pluck('vendor_id');
        if ($alreadyInvited->isNotEmpty()) {
            $query->whereNotIn('vendors.id', $alreadyInvited);
        }

        $newIds = [];
        $query->chunkById(200, function ($vendors) use ($sale, &$newIds) {
            foreach ($vendors as $vendor) {
                FlashSaleVendorInvitition::create([
                    'flash_sale_id' => $sale->id,
                    'vendor_id' => $vendor->id,
                    'invitation_type' => 'auto',
                    'status' => 'pending',
                    'invited_at' => now(),
                    'slots_allocated' => $sale->max_products_per_seller ?? null,
                ]);
                $newIds[] = $vendor->id;
            }
        });

        if (!empty($newIds)) {
            FlashSaleInviteBulkJob::dispatch($sale->id, $newIds);
        }

        return count($newIds);
    }

    public function countEligibleVendors(FlashSale $sale): int
    {
        $alreadyInvited = FlashSaleVendorInvitition::where('flash_sale_id', $sale->id)->pluck('vendor_id');
        $query = $this->buildEligibleVendorQuery($sale);
        if ($alreadyInvited->isNotEmpty()) {
            $query->whereNotIn('vendors.id', $alreadyInvited);
        }
        return $query->count();
    }

    private function buildEligibleVendorQuery(FlashSale $sale): \Illuminate\Database\Eloquent\Builder
    {
        $query = Vendor::where('global_status', VendorGlobalStatus::Active->value);

        if ($sale->country_id) {
            $query->where('country_id', $sale->country_id);
        }

        if ($sale->min_seller_rating !== null) {
            $query->where('store_rating_avg', '>=', $sale->min_seller_rating);
        }

        if (!empty($sale->eligible_seller_tiers)) {
            $thresholds = config('flash_sales.tier_thresholds', []);
            $query->where(function ($q) use ($sale, $thresholds) {
                foreach ($sale->eligible_seller_tiers as $tier) {
                    $t = $thresholds[$tier] ?? null;
                    if (!$t) continue;
                    $q->orWhere(function ($inner) use ($t) {
                        $inner->where('total_sales', '>=', $t['min_total_sales'])
                              ->where('store_rating_avg', '>=', $t['min_rating']);
                        if ($t['min_sla_compliance'] > 0) {
                            $inner->where('sla_compliance_pct', '>=', $t['min_sla_compliance']);
                        }
                        if ($t['max_strikes'] !== PHP_INT_MAX) {
                            $inner->where('strikes_count', '<=', $t['max_strikes']);
                        }
                    });
                }
            });
        }

        if (!empty($sale->eligible_categories)) {
            $categoryIds = $sale->eligible_categories;
            $query->whereHas('listings', function ($q) use ($categoryIds) {
                $q->where('status', VendorListingStatus::Active->value)
                  ->whereHas('productVariant.product', function ($q2) use ($categoryIds) {
                      $q2->whereIn('category_id', $categoryIds);
                  });
            });
        }

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Submission review
    // ─────────────────────────────────────────────────────────────────────────

    public function reviewSubmission(
        FlashSaleSubmission $submission,
        string $decision,
        array $data,
        Admin $admin
    ): FlashSaleSubmission {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException(__('admin.flash_sales.decision_invalid'));
        }

        $flashSale = $submission->flashSale;

        if ($flashSale->status?->value === FlashSaleStatus::Live->value) {
            throw new \LogicException(__('admin.flash_sales.cannot_review_while_live'));
        }

        $fromStatus = $submission->status?->value;

        DB::transaction(function () use ($submission, $decision, $data, $admin, $flashSale, $fromStatus) {
            if ($decision === 'approved') {
                if ((float) $submission->calculated_discount_pct < (float) $flashSale->min_discount_pct) {
                    throw new \LogicException(__('admin.flash_sales.discount_too_low_min', ['pct' => $flashSale->min_discount_pct]));
                }
                if ($flashSale->max_total_slots && $flashSale->approved_slots_count >= $flashSale->max_total_slots) {
                    throw new \LogicException(__('admin.flash_sales.slot_limit_reached_sale'));
                }

                $submission->update([
                    'status' => 'approved',
                    'reviewed_at' => now(),
                    'reviewed_by_admin_id' => $admin->id,
                    'approved_at' => now(),
                    'admin_notes' => $data['admin_notes'] ?? null,
                ]);

                $flashSale->increment('approved_slots_count');
                SubmissionApprovedNotificationJob::dispatch($submission->id);
            } else {
                if (empty($data['rejection_code'])) {
                    throw new \InvalidArgumentException(__('admin.flash_sales.rejection_code_required'));
                }

                $submission->update([
                    'status' => 'rejected',
                    'reviewed_at' => now(),
                    'reviewed_by_admin_id' => $admin->id,
                    'rejection_reason' => $data['rejection_reason'] ?? null,
                    'rejection_code' => $data['rejection_code'],
                    'admin_notes' => $data['admin_notes'] ?? null,
                ]);

                SubmissionRejectedNotificationJob::dispatch($submission->id);
            }

            FlashSaleSubmissionHistory::create([
                'flash_sale_submission_id' => $submission->id,
                'from_status' => $fromStatus,
                'to_status' => $decision === 'approved' ? 'approved' : 'rejected',
                'changed_by_user_id' => $admin->id,
                'changed_by_role' => 'admin',
                'reason' => $data['rejection_reason'] ?? ($data['admin_notes'] ?? null),
            ]);
        });

        return $submission->fresh();
    }

    public function bulkReview(array $submissionIds, string $decision, array $data, Admin $admin): array
    {
        $result = ['approved' => 0, 'rejected' => 0, 'failed' => 0];

        foreach ($submissionIds as $id) {
            $submission = FlashSaleSubmission::find($id);
            if (!$submission) {
                $result['failed']++;
                continue;
            }
            try {
                $this->reviewSubmission($submission, $decision, $data, $admin);
                $result[$decision === 'approved' ? 'approved' : 'rejected']++;
            } catch (\Throwable) {
                $result['failed']++;
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function validateTimeline(array $data): void
    {
        $fields = [
            'submission_opens_at' => __('admin.flash_sales.timeline_fields.submission_opens_at'),
            'submission_closes_at' => __('admin.flash_sales.timeline_fields.submission_closes_at'),
            'review_deadline_at' => __('admin.flash_sales.timeline_fields.review_deadline_at'),
            'sale_starts_at' => __('admin.flash_sales.timeline_fields.sale_starts_at'),
            'sale_ends_at' => __('admin.flash_sales.timeline_fields.sale_ends_at'),
        ];

        $timestamps = array_map(
            fn($k) => isset($data[$k]) ? strtotime($data[$k]) : null,
            array_flip($fields)
        );

        $keys = array_keys($fields);
        $values = array_values($timestamps);

        for ($i = 0; $i < count($keys) - 1; $i++) {
            if ($values[$i] !== null && $values[$i + 1] !== null && $values[$i] >= $values[$i + 1]) {
                throw new \InvalidArgumentException(
                    __('admin.flash_sales.timeline_order_invalid', [
                        'first' => array_values($fields)[$i],
                        'second' => array_values($fields)[$i + 1],
                    ])
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Discount analysis (delegates to FakeDiscountDetectionService)
    // ─────────────────────────────────────────────────────────────────────────

    public function analyzeDiscount(FlashSaleSubmission $submission): array
    {
        return app(FakeDiscountDetectionService::class)->analyze($submission);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Manual vendor invite
    // ─────────────────────────────────────────────────────────────────────────

    public function inviteVendorManually(FlashSale $sale, string $vendorId): FlashSaleVendorInvitition
    {
        $existing = FlashSaleVendorInvitition::where('flash_sale_id', $sale->id)
            ->where('vendor_id', $vendorId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return FlashSaleVendorInvitition::create([
            'flash_sale_id' => $sale->id,
            'vendor_id' => $vendorId,
            'invitation_type' => 'manual',
            'status' => 'pending',
            'invited_at' => now(),
            'slots_allocated' => $sale->max_products_per_seller ?? null,
        ]);
    }
}
