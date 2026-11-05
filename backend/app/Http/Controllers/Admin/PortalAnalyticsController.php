<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegistrationFlow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PortalAnalyticsRequest;
use App\Models\Country;
use App\Services\PortalAnalytics\PortalAnalyticsService;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal visitor analytics and registration funnel (permission portal_analytics.view).
 */
class PortalAnalyticsController extends Controller
{
    use HasExport;

    private const EXPORT_TOP_PAGES_LIMIT = 100;

    public function __construct(private readonly PortalAnalyticsService $analytics) {}

    public function index(PortalAnalyticsRequest $request): View|StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->export($request);
        }

        return view('admin.portal-analytics.index', [
            'filters' => $request->filters()->toArray(),
            'countries' => Country::query()
                ->whereNotNull('site_code')
                ->where('is_active', true)
                ->orderBy('name_en')
                ->get(['site_code', 'name_en']),
            'flows' => collect(RegistrationFlow::cases())->mapWithKeys(fn (RegistrationFlow $flow) => [$flow->value => $flow->label()]),
        ]);
    }

    public function data(PortalAnalyticsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->analytics->report($request->filters())]);
    }

    private function export(PortalAnalyticsRequest $request): StreamedResponse
    {
        $filters = $request->filters();
        $report = $this->analytics->report($filters);
        $report['top_pages'] = $this->analytics->topPages($filters, self::EXPORT_TOP_PAGES_LIMIT);

        $section = (string) $request->input('section', 'summary');
        $export = $this->analytics->exportSection($report, $section);
        $rows = $export['rows']->map(fn (array $row): array => array_combine($export['headers'], array_values($row)));

        $filename = sprintf('portal-analytics-%s-%s-%s', $section, $filters->from->toDateString(), $filters->to->toDateString());
        $title = __('admin.portal_analytics.title');

        return match ($request->input('export')) {
            'excel' => $this->exportExcel($filename, $export['headers'], $rows),
            'word' => $this->exportWord($filename, $title, $rows),
            default => $this->exportCsv($filename, $export['headers'], $rows),
        };
    }
}
