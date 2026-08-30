<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Services\WalletService;
use App\Traits\HasExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FinanceController extends Controller
{
    use HasExport;

    public function __construct(private WalletService $walletService) {}

    private function agencyId(): string
    {
        return Auth::guard('travel_agency')->user()->travel_agency_id;
    }

    private function dateRange(Request $request): array
    {
        $from = $request->query('date_from')
            ? Carbon::parse($request->query('date_from'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->query('date_to')
            ? Carbon::parse($request->query('date_to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function commissionRate(): float
    {
        return (float) config('travel.platform_commission_rate', 0.10);
    }

    // ── Revenue summary ─────────────────────────────────────────────────────────

    public function revenue(Request $request): View
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);
        $rate = $this->commissionRate();

        $summary = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->whereBetween('travel_bookings.created_at', [$from, $to])
            ->selectRaw('
                travel_packages.currency as currency,
                SUM(travel_bookings.total_price) as gross,
                COUNT(*) as bookings_count
            ')
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

        return view('travel-agency.finance.revenue', compact('from', 'to', 'summary'));
    }

    // ── Payout history ──────────────────────────────────────────────────────────

    public function payouts(Request $request): View
    {
        $agencyId = $this->agencyId();
        $rate = $this->commissionRate();

        // No dedicated payout-batch table exists for travel agencies yet, so
        // payout history is derived from completed bookings, grouped by month/currency.
        $payouts = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->selectRaw("
                DATE_FORMAT(travel_bookings.created_at, '%Y-%m') as period,
                travel_packages.currency as currency,
                COUNT(*) as bookings_count,
                SUM(travel_bookings.total_price) as gross
            ")
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

        return view('travel-agency.finance.payouts', compact('payouts'));
    }

    // ── Wallet ───────────────────────────────────────────────────────────────────

    public function wallet(): View
    {
        $agencyId = $this->agencyId();
        $currency = TravelPackage::where('travel_agency_id', $agencyId)->value('currency');

        if (! $currency) {
            return view('travel-agency.finance.wallet', [
                'wallet' => null,
                'transactions' => collect(),
                'withdrawalRequests' => collect(),
            ]);
        }

        $wallet = $this->walletService->getOrCreateWallet('travel_agency', $agencyId, $currency);
        $transactions = $wallet->transactions()->paginate(20);
        $withdrawalRequests = $wallet->withdrawalRequests()->latest()->take(10)->get();

        return view('travel-agency.finance.wallet', compact('wallet', 'transactions', 'withdrawalRequests'));
    }

    public function requestWithdrawal(Request $request): RedirectResponse
    {
        $agencyId = $this->agencyId();
        $currency = TravelPackage::where('travel_agency_id', $agencyId)->value('currency');

        if (! $currency) {
            return back()->with('error', 'No packages found to determine wallet currency.');
        }

        $wallet = $this->walletService->getOrCreateWallet('travel_agency', $agencyId, $currency);

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:1'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_iban' => ['required', 'string', 'max:50'],
        ]);

        $amount = (int) $data['amount'];

        $this->walletService->requestWithdrawal($wallet, $amount, [
            'bank_name' => $data['bank_name'],
            'bank_iban' => $data['bank_iban'],
        ]);

        return back()->with('success', __('travel.finance.withdrawal_submitted'));
    }

    // ── Sales report ─────────────────────────────────────────────────────────────

    private function maskName(?string $name): string
    {
        if (! $name) {
            return '';
        }

        return collect(explode(' ', $name))
            ->map(fn ($part, $i) => $i === 0 ? $part : mb_substr($part, 0, 1) . str_repeat('*', max(mb_strlen($part) - 1, 1)))
            ->implode(' ');
    }

    private function salesReportQuery(Request $request)
    {
        $agencyId = $this->agencyId();
        $rate = $this->commissionRate();

        $query = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->with(['package', 'customer']);

        if ($request->query('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->query('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        if ($packageId = $request->query('package_id')) {
            $query->where('travel_package_id', $packageId);
        }

        if ($countryId = $request->query('country_id')) {
            $query->whereHas('package', fn ($q) => $q->where('destination_travel_country_id', $countryId));
        }

        if ($status = $request->query('status')) {
            $query->where('status', TravelBookingStatus::from($status));
        }

        return [$query, $rate];
    }

    public function salesReport(Request $request): View
    {
        [$query, $rate] = $this->salesReportQuery($request);

        $bookings = $query->latest()->paginate(30)->withQueryString();

        $bookings->getCollection()->transform(function (TravelBooking $booking) use ($rate) {
            $commission = (int) round($booking->total_price * $rate);

            return (object) [
                'booking_ref'   => $booking->booking_number,
                'package_name'  => $booking->package->title_en ?? '',
                'customer_name' => $this->maskName($booking->customer->name ?? ''),
                'travelers'     => $booking->travelers_count,
                'price'         => (int) $booking->total_price,
                'commission'    => $commission,
                'net_revenue'   => (int) $booking->total_price - $commission,
                'currency'      => $booking->package->currency ?? '',
                'date'          => $booking->created_at,
                'status'        => $booking->status,
            ];
        });

        $agencyId = $this->agencyId();
        $packages = TravelPackage::where('travel_agency_id', $agencyId)->orderBy('title_en')->get(['id', 'title_en', 'title_ar']);

        return view('travel-agency.finance.sales-report', compact('bookings', 'packages'));
    }

    public function exportSalesReport(Request $request): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        [$query, $rate] = $this->salesReportQuery($request);

        $bookings = $query->latest()->get();

        $headers = [
            __('travel.finance.export.booking_ref'),
            __('travel.finance.export.package'),
            __('travel.finance.export.customer'),
            __('travel.finance.export.travelers'),
            __('travel.finance.export.price'),
            __('travel.finance.export.commission'),
            __('travel.finance.export.net_revenue'),
            __('travel.finance.export.currency'),
            __('travel.finance.export.date'),
            __('travel.finance.export.status'),
        ];

        $rows = $bookings->map(function (TravelBooking $booking) use ($rate) {
            $commission = (int) round($booking->total_price * $rate);

            return [
                $booking->booking_number,
                $booking->package->title_en ?? '',
                $this->maskName($booking->customer->name ?? ''),
                $booking->travelers_count,
                (int) $booking->total_price,
                $commission,
                (int) $booking->total_price - $commission,
                $booking->package->currency ?? '',
                $booking->created_at->toDateString(),
                $booking->status->value,
            ];
        });

        $filename = 'sales-report-' . now()->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word' => $this->exportWord($filename, __('travel.finance.export.sheet_title'), $rows),
            'csv' => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }
}
