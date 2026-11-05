<?php

namespace App\Services\PortalAnalytics;

use App\Enums\RegistrationStepEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the portal analytics: every number is an aggregate SQL query over the tracked
 * tables (no rows are loaded into PHP and nothing is simulated).
 *
 * Definitions
 *  - Page view: one counted portal page (refreshing the page you are on is not counted).
 *  - Visit: a visitor's first page view, or their first after 30+ idle minutes.
 *  - Unique visitor: distinct anonymous visitor id with a page view in the period.
 *  - Returning visitor: unique visitor whose first-ever visit was before the period start.
 *  - Registration metrics are a cohort: they count the registrations *started* in the period.
 *    A registration "attempt" is one where at least one step was submitted.
 */
class PortalAnalyticsService
{
    public const TOP_PAGES_LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    public function report(PortalAnalyticsFilters $filters): array
    {
        $current = $this->periodStats($filters->from, $filters->to, $filters);
        $previous = $filters->compare
            ? $this->periodStats($filters->previousFrom(), $filters->previousTo(), $filters)
            : null;

        $funnel = $this->funnel($filters);

        return [
            'filters' => $filters->toArray(),
            'period' => [
                'from' => $filters->from->toDateString(),
                'to' => $filters->to->toDateString(),
                'days' => $filters->days(),
                'previous_from' => $previous ? $filters->previousFrom()->toDateString() : null,
                'previous_to' => $previous ? $filters->previousTo()->toDateString() : null,
            ],
            'kpis' => $this->kpis($current, $previous),
            'funnel' => $funnel,
            'timeline' => $this->timeline($filters),
            'top_pages' => $this->topPages($filters, self::TOP_PAGES_LIMIT),
            'has_data' => $current['page_views'] > 0 || $current['registration_starts'] > 0,
        ];
    }

    // ── KPI aggregates ────────────────────────────────────────────────────

    /**
     * @return array<string, int|float>
     */
    private function periodStats(CarbonImmutable $from, CarbonImmutable $to, PortalAnalyticsFilters $filters): array
    {
        $traffic = $this->pageViews($from, $to, $filters->countryCode)
            ->selectRaw('COUNT(*) as page_views, COALESCE(SUM(is_new_visit), 0) as visits, COUNT(DISTINCT visitor_id) as unique_visitors')
            ->first();

        $returning = $this->pageViews($from, $to, $filters->countryCode)
            ->join('portal_visitors as v', 'v.id', '=', 'portal_page_views.visitor_id')
            ->where('v.first_seen_at', '<', $from)
            ->selectRaw('COUNT(DISTINCT portal_page_views.visitor_id) as returning_visitors')
            ->value('returning_visitors');

        $registrations = DB::table('registration_progress')
            ->where('flow', $filters->flow->value)
            ->whereBetween('started_at', [$from, $to])
            ->when($filters->countryCode, fn (Builder $q, string $code) => $q->where('country_code', $code))
            ->selectRaw('COUNT(*) as starts, COALESCE(SUM(first_submitted_at IS NOT NULL), 0) as attempts, COALESCE(SUM(completed_at IS NOT NULL), 0) as completed')
            ->first();

        $starts = (int) $registrations->starts;
        $completed = (int) $registrations->completed;
        $uniqueVisitors = (int) $traffic->unique_visitors;

        return [
            'visits' => (int) $traffic->visits,
            'page_views' => (int) $traffic->page_views,
            'unique_visitors' => $uniqueVisitors,
            'returning_visitors' => (int) $returning,
            'registration_starts' => $starts,
            'registration_attempts' => (int) $registrations->attempts,
            'registrations_completed' => $completed,
            'completion_rate' => $starts > 0 ? round($completed / $starts * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  array<string, int|float>  $current
     * @param  array<string, int|float>|null  $previous
     * @return array<string, array{value: int|float, previous: int|float|null, change: float|null, change_type: string}>
     */
    private function kpis(array $current, ?array $previous): array
    {
        $kpis = [];

        foreach ($current as $key => $value) {
            $isRate = $key === 'completion_rate';
            $before = $previous[$key] ?? null;

            $kpis[$key] = [
                'value' => $value,
                'previous' => $before,
                'change' => $this->change($value, $before, $isRate),
                'change_type' => $isRate ? 'points' : 'percent',
            ];
        }

        return $kpis;
    }

    /**
     * Percent change (or percentage-point difference for rates). Null when there is no baseline.
     */
    private function change(int|float $value, int|float|null $previous, bool $points): ?float
    {
        if ($previous === null) {
            return null;
        }

        if ($points) {
            return round($value - $previous, 1);
        }

        return $previous > 0 ? round(($value - $previous) / $previous * 100, 1) : null;
    }

    // ── Funnel ────────────────────────────────────────────────────────────

    /**
     * @return array{flow: string, flow_label: string, started: int, completed: int, completion_rate: float, steps: list<array<string, mixed>>}
     */
    private function funnel(PortalAnalyticsFilters $filters): array
    {
        $flow = $filters->flow;

        $counts = DB::table('registration_step_events as e')
            ->join('registration_progress as p', 'p.id', '=', 'e.registration_progress_id')
            ->where('p.flow', $flow->value)
            ->whereBetween('p.started_at', [$filters->from, $filters->to])
            ->when($filters->countryCode, fn (Builder $q, string $code) => $q->where('p.country_code', $code))
            ->selectRaw('e.step, e.event, COUNT(*) as registrations, COALESCE(SUM(e.occurrences), 0) as occurrences')
            ->groupBy('e.step', 'e.event')
            ->get()
            ->groupBy('step');

        $steps = [];
        $firstReached = null;

        foreach ($flow->steps() as $number => $key) {
            $byEvent = ($counts->get($number) ?? collect())->keyBy('event');

            $reached = (int) ($byEvent->get(RegistrationStepEvent::Viewed->value)->registrations ?? 0);
            $completed = (int) ($byEvent->get(RegistrationStepEvent::Completed->value)->registrations ?? 0);
            $failed = $byEvent->get(RegistrationStepEvent::ValidationFailed->value);
            $firstReached ??= $reached;
            $abandoned = max(0, $reached - $completed);

            $steps[] = [
                'step' => $number,
                'key' => $key,
                'label' => $flow->stepLabel($number),
                'reached' => $reached,
                'completed' => $completed,
                'abandoned' => $abandoned,
                'conversion_rate' => $reached > 0 ? round($completed / $reached * 100, 1) : 0.0,
                'dropoff_rate' => $reached > 0 ? round($abandoned / $reached * 100, 1) : 0.0,
                'reached_of_start' => $firstReached > 0 ? round($reached / $firstReached * 100, 1) : 0.0,
                'failed_registrations' => (int) ($failed->registrations ?? 0),
                'failed_attempts' => (int) ($failed->occurrences ?? 0),
            ];
        }

        $started = $steps[0]['reached'] ?? 0;
        $completedAll = $steps[array_key_last($steps)]['completed'] ?? 0;

        return [
            'flow' => $flow->value,
            'flow_label' => $flow->label(),
            'started' => $started,
            'completed' => $completedAll,
            'completion_rate' => $started > 0 ? round($completedAll / $started * 100, 1) : 0.0,
            'steps' => $steps,
        ];
    }

    // ── Timeline ──────────────────────────────────────────────────────────

    /**
     * @return array{granularity: string, labels: list<string>, page_views: list<int>, visits: list<int>, unique_visitors: list<int>, registrations_started: list<int>, registrations_completed: list<int>}
     */
    private function timeline(PortalAnalyticsFilters $filters): array
    {
        $buckets = $this->bucketKeys($filters);

        $traffic = $this->pageViews($filters->from, $filters->to, $filters->countryCode)
            ->selectRaw($this->bucketSql($filters->granularity, 'viewed_at').' as bucket, COUNT(*) as page_views, COALESCE(SUM(is_new_visit), 0) as visits, COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $registrations = DB::table('registration_progress')
            ->where('flow', $filters->flow->value)
            ->whereBetween('started_at', [$filters->from, $filters->to])
            ->when($filters->countryCode, fn (Builder $q, string $code) => $q->where('country_code', $code))
            ->selectRaw($this->bucketSql($filters->granularity, 'started_at').' as bucket, COUNT(*) as started, COALESCE(SUM(completed_at IS NOT NULL), 0) as completed')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $series = fn (Collection $rows, string $column): array => array_map(
            fn (string $bucket): int => (int) ($rows->get($bucket)->{$column} ?? 0),
            $buckets,
        );

        return [
            'granularity' => $filters->granularity,
            'labels' => $buckets,
            'page_views' => $series($traffic, 'page_views'),
            'visits' => $series($traffic, 'visits'),
            'unique_visitors' => $series($traffic, 'unique_visitors'),
            'registrations_started' => $series($registrations, 'started'),
            'registrations_completed' => $series($registrations, 'completed'),
        ];
    }

    private function bucketSql(string $granularity, string $column): string
    {
        return match ($granularity) {
            'week' => "DATE_FORMAT(DATE_SUB(DATE({$column}), INTERVAL WEEKDAY({$column}) DAY), '%Y-%m-%d')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m-01')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    /**
     * Every bucket start date (Y-m-d) in the period, so empty days are charted as zero.
     *
     * @return list<string>
     */
    private function bucketKeys(PortalAnalyticsFilters $filters): array
    {
        $cursor = match ($filters->granularity) {
            'week' => $filters->from->startOfWeek(CarbonInterface::MONDAY), // MySQL WEEKDAY() weeks start on Monday in every locale
            'month' => $filters->from->startOfMonth(),
            default => $filters->from->startOfDay(),
        };

        $keys = [];

        while ($cursor->lte($filters->to)) {
            $keys[] = $cursor->toDateString();
            $cursor = match ($filters->granularity) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $keys;
    }

    // ── Top pages ─────────────────────────────────────────────────────────

    /**
     * @return list<array{path: string, views: int, unique_visitors: int}>
     */
    public function topPages(PortalAnalyticsFilters $filters, int $limit): array
    {
        return $this->pageViews($filters->from, $filters->to, $filters->countryCode)
            ->selectRaw('path, COUNT(*) as views, COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupBy('path')
            ->orderByDesc('views')
            ->orderBy('path')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'path' => $row->path,
                'views' => (int) $row->views,
                'unique_visitors' => (int) $row->unique_visitors,
            ])
            ->all();
    }

    private function pageViews(CarbonImmutable $from, CarbonImmutable $to, ?string $countryCode): Builder
    {
        return DB::table('portal_page_views')
            ->whereBetween('portal_page_views.viewed_at', [$from, $to])
            ->when($countryCode, fn (Builder $q, string $code) => $q->where('portal_page_views.country_code', $code));
    }

    // ── Export ────────────────────────────────────────────────────────────

    public const EXPORT_SECTIONS = ['summary', 'funnel', 'timeline', 'top_pages'];

    /**
     * Headers and rows of one report section for the CSV / Excel / Word export.
     *
     * @param  array<string, mixed>  $report
     * @return array{headers: list<string>, rows: Collection<int, array<string, mixed>>}
     */
    public function exportSection(array $report, string $section): array
    {
        $t = fn (string $key): string => __('admin.portal_analytics.export.col_'.$key);

        return match ($section) {
            'funnel' => [
                'headers' => [$t('step'), $t('reached'), $t('completed'), $t('abandoned'), $t('conversion'), $t('dropoff')],
                'rows' => collect($report['funnel']['steps'])->map(fn (array $step): array => [
                    'step' => $step['step'].'. '.$step['label'],
                    'reached' => $step['reached'],
                    'completed' => $step['completed'],
                    'abandoned' => $step['abandoned'],
                    'conversion' => $step['conversion_rate'],
                    'dropoff' => $step['dropoff_rate'],
                ])->values(),
            ],
            'timeline' => [
                'headers' => [$t('date'), $t('page_views'), $t('visits'), $t('unique_visitors'), $t('registrations_started'), $t('registrations_completed')],
                'rows' => collect($report['timeline']['labels'])->map(fn (string $label, int $i): array => [
                    'date' => $label,
                    'page_views' => $report['timeline']['page_views'][$i],
                    'visits' => $report['timeline']['visits'][$i],
                    'unique_visitors' => $report['timeline']['unique_visitors'][$i],
                    'registrations_started' => $report['timeline']['registrations_started'][$i],
                    'registrations_completed' => $report['timeline']['registrations_completed'][$i],
                ])->values(),
            ],
            'top_pages' => [
                'headers' => [$t('page'), $t('page_views'), $t('unique_visitors')],
                'rows' => collect($report['top_pages'])->map(fn (array $page): array => [
                    'page' => $page['path'],
                    'page_views' => $page['views'],
                    'unique_visitors' => $page['unique_visitors'],
                ])->values(),
            ],
            default => [
                'headers' => [$t('metric'), $t('value'), $t('previous'), $t('change')],
                'rows' => collect($report['kpis'])->map(fn (array $kpi, string $key): array => [
                    'metric' => __('admin.portal_analytics.kpi.'.$key),
                    'value' => $kpi['value'],
                    'previous' => $kpi['previous'] ?? '',
                    'change' => $kpi['change'] ?? '',
                ])->values(),
            ],
        };
    }
}
