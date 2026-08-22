<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\Admin;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

class ReviewModerationService
{
    /**
     * Approve a review: publish it and recalculate the listing rating.
     */
    public function approve(Review $review, Admin $admin): void
    {
        DB::transaction(function () use ($review, $admin) {
            $review->update([
                'status' => ReviewStatus::Published,
                'moderated_by_admin_id' => $admin->id,
            ]);

            $this->recalculateListingRating($review);
        });
    }

    /**
     * Reject a review: record moderator and soft-delete.
     */
    public function reject(Review $review, Admin $admin): void
    {
        DB::transaction(function () use ($review, $admin) {
            $review->update([
                'status' => ReviewStatus::Rejected,
                'moderated_by_admin_id' => $admin->id,
            ]);

            $this->recalculateListingRating($review);

            $review->delete(); // soft delete
        });
    }

    /**
     * Recalculate rating_avg and rating_count for whichever listing this review is
     * attributed to. Ratings live on vendor_listings / admin_listings, not
     * on products, since the same product can be sold by many sellers with very
     * different review experiences.
     */
    public function recalculateListingRating(Review $review): void
    {
        if ($review->vendor_listing_id !== null) {
            $this->recalculate('vendor_listings', 'vendor_listing_id', $review->vendor_listing_id);
        }

        if ($review->admin_listing_id !== null) {
            $this->recalculate('admin_listings', 'admin_listing_id', $review->admin_listing_id);
        }
    }

    private function recalculate(string $table, string $column, string $listingId): void
    {
        $stats = Review::where($column, $listingId)
            ->where('status', ReviewStatus::Published)
            ->whereNull('deleted_at')
            ->selectRaw('AVG(rating) as rating_avg, COUNT(*) as rating_count')
            ->first();

        DB::table($table)
            ->where('id', $listingId)
            ->update([
                'rating_avg' => $stats->rating_count > 0 ? round($stats->rating_avg, 2) : null,
                'rating_count' => $stats->rating_count,
                'updated_at' => now(),
            ]);
    }
}
