<?php

namespace App\Services;

use App\Enums\CarrierClaimStatus;
use App\Jobs\CreditVendorCompensationJob;
use App\Models\Admin;
use App\Models\CarrierClaim;
use App\Models\CarrierPerformanceRating;
use App\Models\ShippingCompany;
use Illuminate\Support\Str;

class CarrierClaimService
{
    // ── Claims ─────────────────────────────────────────────────────────────

    public function submitClaim(array $data, mixed $submitter): CarrierClaim
    {
        return CarrierClaim::create([
            'claim_number'         => $this->generateClaimNumber(),
            'shipment_id'          => $data['shipment_id'],
            'shipping_company_id'  => $data['shipping_company_id'] ?? null,
            'delivery_agent_id'    => $data['delivery_agent_id'] ?? null,
            'claim_type'           => $data['claim_type'],
            'description'          => $data['description'],
            'claimed_amount' => $data['claimed_amount'],
            'evidence_files'       => $data['evidence_files'] ?? null,
            'status'               => CarrierClaimStatus::Submitted,
        ]);
    }

    public function markUnderReview(CarrierClaim $claim): CarrierClaim
    {
        $claim->update(['status' => CarrierClaimStatus::UnderReview]);
        return $claim;
    }

    public function resolve(
        CarrierClaim $claim,
        string $decision,
        ?int $compensatedAmount,
        Admin $admin,
        ?string $notes = null
    ): CarrierClaim {
        if ($decision === 'approved') {
            $claim->update([
                'status'                   => CarrierClaimStatus::Approved,
                'compensated_amount' => $compensatedAmount,
                'resolution_notes'         => $notes,
                'resolved_by_admin_id'     => $admin->id,
                'resolved_at'              => now(),
            ]);

            if ($compensatedAmount && $compensatedAmount > 0) {
                CreditVendorCompensationJob::dispatch($claim);
            }
        } else {
            $claim->update([
                'status'               => CarrierClaimStatus::Rejected,
                'resolution_notes'     => $notes,
                'resolved_by_admin_id' => $admin->id,
                'resolved_at'          => now(),
            ]);
        }

        return $claim->fresh();
    }

    // ── Scorecard ──────────────────────────────────────────────────────────

    public function getCarrierScorecard(ShippingCompany $company, string $period = 'month'): array
    {
        $since = match($period) {
            'week'    => now()->subWeek(),
            'quarter' => now()->subQuarter(),
            'year'    => now()->subYear(),
            default   => now()->subMonth(),
        };

        $ratings = CarrierPerformanceRating::where('shipping_company_id', $company->id)
            ->where('created_at', '>=', $since)
            ->get();

        $claims = CarrierClaim::where('shipping_company_id', $company->id)
            ->where('created_at', '>=', $since)
            ->get();

        $totalRatings   = $ratings->count();
        $onTimeCount    = $ratings->whereNotNull('on_time')->where('on_time', true)->count();
        $onTimeEligible = $ratings->whereNotNull('on_time')->count();

        $totalClaims    = $claims->count();
        $approvedClaims = $claims->whereIn('status', [CarrierClaimStatus::Approved, CarrierClaimStatus::Compensated])->count();

        return [
            'period'               => $period,
            'avg_rating'           => $totalRatings ? round($ratings->avg('rating'), 2) : null,
            'total_ratings'        => $totalRatings,
            'on_time_pct'          => $onTimeEligible ? round($onTimeCount / $onTimeEligible * 100, 1) : null,
            'total_claims'         => $totalClaims,
            'claims_approved_pct'  => $totalClaims ? round($approvedClaims / $totalClaims * 100, 1) : null,
            'total_compensated'    => $claims->whereIn('status', [CarrierClaimStatus::Approved, CarrierClaimStatus::Compensated])
                                             ->sum('compensated_amount'),
        ];
    }

    // ── Trend data for Chart.js (last N months) ────────────────────────────

    public function getRatingTrend(ShippingCompany $company, int $months = 6): array
    {
        $rows = CarrierPerformanceRating::selectRaw(
                'DATE_FORMAT(created_at, "%Y-%m") as month,
                 AVG(rating) as avg_rating,
                 COUNT(*) as count'
            )
            ->where('shipping_company_id', $company->id)
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")')
            ->orderBy('month')
            ->get();

        return $rows->map(fn($r) => [
            'month'      => $r->month,
            'avg_rating' => round($r->avg_rating, 2),
            'count'      => $r->count,
        ])->values()->toArray();
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function generateClaimNumber(): string
    {
        do {
            $number = 'CLM-' . strtoupper(Str::random(3)) . '-' . now()->format('ymd') . '-' . random_int(1000, 9999);
        } while (CarrierClaim::where('claim_number', $number)->exists());

        return $number;
    }
}
