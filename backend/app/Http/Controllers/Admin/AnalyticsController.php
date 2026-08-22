<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\AnalyticsService;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use HasExport;

    public function __construct(protected AnalyticsService $analytics)
    {
    }

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportOverview($request);
        }

        $countries = Country::where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'iso_code_2']);

        return view('admin.analytics.index', compact('countries'));
    }

    // ── Export ────────────────────────────────────────────────────────────────
    // NOTE: this page is a metrics dashboard, not a row-listing table — there is
    // no underlying "listing" datatable to reuse. We export the same overview
    // summary metrics the page's AJAX overview() endpoint returns, flattened
    // into label/value rows.

    private function exportOverview(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $overview = $this->analytics->overview($request);

        $rows = collect($overview)->map(fn($value, $key) => [
            'Metric' => $key,
            'Value' => is_scalar($value) ? $value : json_encode($value),
        ])->values();

        $headers = ['Metric', 'Value'];

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('analytics-overview', $headers, $rows),
            'csv' => $this->exportCsv('analytics-overview', $headers, $rows),
            'word' => $this->exportWord('analytics-overview', 'Analytics Overview', $rows),
            default => abort(400, __('admin.invalid_export_format')),
        };
    }

    // ── AJAX endpoints ────────────────────────────────────────────────────────

    public function overview(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->overview($request)]);
    }

    public function revenueChart(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->revenueChart($request)]);
    }

    public function ordersByStatus(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->ordersByStatus($request)]);
    }

    public function ordersByPaymentMethod(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->ordersByPaymentMethod($request)]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->topProducts($request)]);
    }

    public function topVendors(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->topVendors($request)]);
    }

    public function topCategories(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->topCategories($request)]);
    }

    public function customerStats(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->customerStats($request)]);
    }

    public function searchAnalytics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->searchAnalytics($request)]);
    }

    public function productAnalytics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->productAnalytics($request)]);
    }

    public function slaMetrics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->slaMetrics($request)]);
    }

    public function adPerformance(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->adPerformance($request)]);
    }

    public function flashSaleAnalytics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->flashSaleAnalytics($request)]);
    }

    public function returnAnalytics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->returnAnalytics($request)]);
    }

    public function supportMetrics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->supportMetrics($request)]);
    }
}
