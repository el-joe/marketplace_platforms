<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    private function agencyId(): string
    {
        return auth()->guard('travel_agencies')->user()->id;
    }

    private function dateRange(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function commissionRate(): float
    {
        return (float) config('travel.platform_commission_rate', 0.10);
    }

    /** GET /api/travel-agency/v1/finance/revenue */
    public function revenue(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);
        $rate = $this->commissionRate();

        $base = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->whereBetween('travel_bookings.created_at', [$from, $to]);

        $summary = (clone $base)
            ->selectRaw('travel_packages.currency as currency, SUM(travel_bookings.total_price) as gross, COUNT(*) as bookings_count')
            ->groupBy('travel_packages.currency')
            ->get()
            ->map(function ($row) use ($rate) {
                $commission = (int) round($row->gross * $rate);
                return [
                    'currency'       => $row->currency,
                    'gross'          => (int) $row->gross,
                    'commission'     => $commission,
                    'net_revenue'    => (int) $row->gross - $commission,
                    'bookings_count' => (int) $row->bookings_count,
                ];
            });

        $monthlyBreakdown = (clone $base)
            ->selectRaw("DATE_FORMAT(travel_bookings.created_at, '%Y-%m') as month, travel_packages.currency as currency, COUNT(*) as bookings_count, SUM(travel_bookings.total_price) as revenue")
            ->groupBy('month', 'currency')
            ->orderByDesc('month')
            ->get()
            ->map(function ($row) use ($rate) {
                $commission = (int) round($row->revenue * $rate);
                return [
                    'month'          => $row->month,
                    'currency'       => $row->currency,
                    'bookings_count' => (int) $row->bookings_count,
                    'revenue'        => (int) $row->revenue,
                    'commission'     => $commission,
                    'net'            => (int) $row->revenue - $commission,
                ];
            });

        return response()->json([
            'date_from'         => $from->toDateString(),
            'date_to'           => $to->toDateString(),
            'summary'           => $summary,
            'monthly_breakdown' => $monthlyBreakdown,
            'commission_rate'   => $rate,
        ]);
    }

    /** GET /api/travel-agency/v1/finance/payouts */
    public function payouts(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();
        $rate     = $this->commissionRate();

        $payouts = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->selectRaw("DATE_FORMAT(travel_bookings.created_at, '%Y-%m') as period, travel_packages.currency as currency, COUNT(*) as bookings_count, SUM(travel_bookings.total_price) as gross")
            ->groupBy('period', 'currency')
            ->orderByDesc('period')
            ->get()
            ->map(function ($row) use ($rate) {
                $commission = (int) round($row->gross * $rate);
                return [
                    'period'         => $row->period,
                    'currency'       => $row->currency,
                    'bookings_count' => (int) $row->bookings_count,
                    'gross'          => (int) $row->gross,
                    'commission'     => $commission,
                    'net_payout'     => (int) $row->gross - $commission,
                ];
            });

        return response()->json(['payouts' => $payouts]);
    }

    /** GET /api/travel-agency/v1/finance/wallet */
    public function wallet(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();
        $currency = TravelPackage::where('travel_agency_id', $agencyId)->value('currency');

        if (! $currency) {
            return response()->json([
                'wallet' => null,
                'message' => 'No packages found to determine wallet currency.',
            ]);
        }

        $wallet       = $this->walletService->getOrCreateWallet('travel_agency', $agencyId, $currency);
        $transactions = $wallet->transactions()->orderByDesc('created_at')->paginate(20);
        $withdrawals  = $wallet->withdrawalRequests()->latest()->take(10)->get();

        return response()->json([
            'wallet' => [
                'balance'         => $wallet->balance,
                'pending_balance' => $wallet->pending_balance,
                'currency'        => $wallet->currency,
                'is_frozen'       => $wallet->is_frozen,
            ],
            'transactions' => $transactions->map(fn ($t) => [
                'id'          => $t->id,
                'type'        => $t->type,
                'amount'      => $t->amount,
                'description' => $t->description,
                'source_type' => $t->source_type,
                'created_at'  => $t->created_at?->toIso8601String(),
            ]),
            'withdrawal_requests' => $withdrawals->map(fn ($w) => [
                'id'         => $w->id,
                'amount'     => $w->amount,
                'currency'   => $w->currency,
                'status'     => $w->status,
                'created_at' => $w->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
            ],
        ]);
    }

    /** GET /api/travel-agency/v1/finance/sales-report */
    public function salesReport(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);
        $rate = $this->commissionRate();

        $bookings = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->whereBetween('travel_bookings.created_at', [$from, $to])
            ->select([
                'travel_bookings.id',
                'travel_bookings.booking_number',
                'travel_bookings.status',
                'travel_bookings.total_price',
                'travel_bookings.travelers_count',
                'travel_bookings.created_at',
                'travel_packages.title_ar as package_title_ar',
                'travel_packages.title_en as package_title_en',
                'travel_packages.currency',
            ])
            ->orderByDesc('travel_bookings.created_at')
            ->paginate((int) ($request->query('per_page', 30)));

        return response()->json([
            'date_from'       => $from->toDateString(),
            'date_to'         => $to->toDateString(),
            'commission_rate' => $rate,
            'bookings'        => $bookings->map(function ($b) use ($rate) {
                $commission = (int) round($b->total_price * $rate);
                return [
                    'booking_number'  => $b->booking_number,
                    'package_title'   => $b->package_title_ar ?: $b->package_title_en,
                    'status'          => $b->status,
                    'travelers_count' => $b->travelers_count,
                    'total_price'     => (int) $b->total_price,
                    'commission'      => $commission,
                    'net'             => (int) $b->total_price - $commission,
                    'currency'        => $b->currency,
                    'date'            => $b->created_at?->toDateString(),
                ];
            }),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page'    => $bookings->lastPage(),
                'total'        => $bookings->total(),
            ],
        ]);
    }
}
