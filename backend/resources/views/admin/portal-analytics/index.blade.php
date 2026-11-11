@extends('layouts.admin')

@push('styles')
    @vite('resources/js/admin/portal-analytics.js')
@endpush

@push('scripts')
    <script>
        window.PORTAL_ANALYTICS = {
            dataUrl: @json(route('admin.portal-analytics.data')),
            pageUrl: @json(route('admin.portal-analytics.index')),
            initial: @json($filters),
            locale: @json(app()->getLocale()),
            i18n: {
                kpi: @json(__('admin.portal_analytics.kpi')),
                kpiHint: @json(__('admin.portal_analytics.kpi_hint')),
                timeline: @json(__('admin.portal_analytics.timeline')),
                comparedTo: @json(__('admin.portal_analytics.compared_to')),
                vsPrev: @json(__('admin.portal_analytics.vs_prev')),
                vsPrevPoints: @json(__('admin.portal_analytics.vs_prev_points')),
                noBaseline: @json(__('admin.portal_analytics.no_baseline')),
                roll: @json(__('admin.portal_analytics.rolled_up_notice')),
                errorDetail: @json(__('admin.portal_analytics.states.error')),
            },
        };
    </script>
@endpush

@section('title', __('admin.portal_analytics.title'))

@section('content')
    <div class="mb-6 flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.portal_analytics.title') }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.portal_analytics.subtitle') }}</p>
            </div>

            {{-- Export --}}
            <div class="flex items-center gap-2">
                <label for="pa-export-section" class="text-xs text-gray-500">{{ __('admin.portal_analytics.export.section') }}</label>
                <select id="pa-export-section" class="form-input py-1.5 text-sm w-36">
                    @foreach (['summary', 'funnel', 'timeline', 'top_pages'] as $section)
                        <option value="{{ $section }}">{{ __('admin.portal_analytics.export.'.$section) }}</option>
                    @endforeach
                </select>
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="btn btn-secondary btn-sm">
                        {{ __('common.export') }}
                        <x-heroicon name="chevron-down" class="w-4 h-4 ms-1 inline" />
                    </button>
                    <div x-show="open" x-transition x-cloak
                        class="absolute end-0 mt-1 z-20 w-40 bg-white rounded-lg shadow-lg border border-gray-100 py-1">
                        <a data-pa-export="excel" href="#" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('common.export_excel') }}</a>
                        <a data-pa-export="csv" href="#" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('common.export_csv') }}</a>
                        <a data-pa-export="word" href="#" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('common.export_word') }}</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-end gap-3">
            <div class="inline-flex rounded-lg shadow-sm border border-gray-300 overflow-hidden" role="group" id="pa-quick-ranges">
                @foreach (['today' => 'today', '7' => '7d', '30' => '30d', '90' => '90d', '365' => '1y'] as $days => $key)
                    <button type="button" data-days="{{ $days }}"
                        class="pa-range-btn px-3 py-1.5 text-sm font-medium border-e border-gray-300 last:border-e-0 transition-colors bg-white text-gray-700 hover:bg-gray-50">
                        {{ __('admin.portal_analytics.range.'.$key) }}
                    </button>
                @endforeach
            </div>

            <div>
                <label for="pa-from" class="block text-xs text-gray-500 mb-1">{{ __('admin.portal_analytics.from') }}</label>
                <input type="date" id="pa-from" class="form-input py-1.5 text-sm w-40" value="{{ $filters['date_from'] }}">
            </div>
            <div>
                <label for="pa-to" class="block text-xs text-gray-500 mb-1">{{ __('admin.portal_analytics.to') }}</label>
                <input type="date" id="pa-to" class="form-input py-1.5 text-sm w-40" value="{{ $filters['date_to'] }}">
            </div>
            <div>
                <label for="pa-granularity" class="block text-xs text-gray-500 mb-1">{{ __('admin.portal_analytics.granularity_label') }}</label>
                <select id="pa-granularity" class="form-input py-1.5 text-sm w-32">
                    @foreach (['day', 'week', 'month'] as $g)
                        <option value="{{ $g }}" @selected($filters['granularity'] === $g)>{{ __('admin.portal_analytics.granularity.'.$g) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="pa-country" class="block text-xs text-gray-500 mb-1">{{ __('admin.portal_analytics.country') }}</label>
                <select id="pa-country" class="form-input py-1.5 text-sm w-44">
                    <option value="">{{ __('admin.portal_analytics.all_countries') }}</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->site_code }}" @selected($filters['country'] === $country->site_code)>{{ $country->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="pa-flow" class="block text-xs text-gray-500 mb-1">{{ __('admin.portal_analytics.flow') }}</label>
                <select id="pa-flow" class="form-input py-1.5 text-sm w-48">
                    @foreach ($flows as $value => $label)
                        <option value="{{ $value }}" @selected($filters['flow'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 pb-1.5">
                <input type="checkbox" id="pa-compare" class="rounded border-gray-300" @checked($filters['compare'])>
                {{ __('admin.portal_analytics.compare') }}
            </label>
            <button type="button" id="pa-apply" class="btn btn-primary btn-sm mb-0.5">{{ __('admin.portal_analytics.apply') }}</button>
        </div>

        <p id="pa-period-note" class="text-xs text-gray-500 -mt-2 hidden"></p>
    </div>

    {{-- Error state --}}
    <div id="pa-error" class="hidden mb-6 rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 flex items-center justify-between gap-3" role="alert">
        <span id="pa-error-text">{{ __('admin.portal_analytics.states.error') }}</span>
        <button type="button" id="pa-retry" class="btn btn-secondary btn-sm">{{ __('admin.portal_analytics.states.retry') }}</button>
    </div>

    {{-- Empty state --}}
    <div id="pa-empty" class="hidden mb-6 bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
        <x-heroicon name="chart-bar" class="w-10 h-10 text-gray-300 mx-auto" />
        <p class="mt-3 font-semibold text-gray-700">{{ __('admin.portal_analytics.states.empty_title') }}</p>
        <p class="mt-1 text-sm text-gray-500">{{ __('admin.portal_analytics.states.empty_text') }}</p>
    </div>

    <div id="pa-content" aria-busy="true">
        {{-- KPI cards (skeletons until loaded) --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="pa-kpis">
            @for ($i = 0; $i < 8; $i++)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="h-4 w-24 bg-gray-200 rounded animate-pulse"></div>
                    <div class="mt-3 h-8 w-20 bg-gray-200 rounded animate-pulse"></div>
                </div>
            @endfor
        </div>

        {{-- Funnel --}}
        <x-card class="mb-6">
            <x-slot:title>{{ __('admin.portal_analytics.funnel.title') }}</x-slot:title>
            <div class="p-6">
                <p class="text-sm text-gray-500 mb-4">{{ __('admin.portal_analytics.funnel.subtitle') }}</p>
                <div class="table-responsive">
                    <table class="min-w-full text-sm" id="pa-funnel">
                        <thead>
                            <tr class="text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                                <th class="py-2 pe-4 text-start font-semibold">{{ __('admin.portal_analytics.funnel.step') }}</th>
                                <th class="py-2 px-4 text-start font-semibold w-1/3 min-w-48"></th>
                                <th class="py-2 px-3 text-end font-semibold">{{ __('admin.portal_analytics.funnel.reached') }}</th>
                                <th class="py-2 px-3 text-end font-semibold">{{ __('admin.portal_analytics.funnel.completed') }}</th>
                                <th class="py-2 px-3 text-end font-semibold">{{ __('admin.portal_analytics.funnel.abandoned') }}</th>
                                <th class="py-2 px-3 text-end font-semibold">{{ __('admin.portal_analytics.funnel.conversion') }}</th>
                                <th class="py-2 px-3 text-end font-semibold">{{ __('admin.portal_analytics.funnel.dropoff') }}</th>
                                <th class="py-2 ps-3 text-end font-semibold" title="{{ __('admin.portal_analytics.funnel.errors_hint') }}">{{ __('admin.portal_analytics.funnel.errors') }}</th>
                            </tr>
                        </thead>
                        <tbody id="pa-funnel-body" class="divide-y divide-gray-100">
                            <tr><td colspan="8" class="py-6 text-center text-gray-400">{{ __('admin.portal_analytics.states.loading') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-4 text-xs text-gray-500">{{ __('admin.portal_analytics.funnel.note') }}</p>
            </div>
        </x-card>

        {{-- Timeline --}}
        <x-card class="mb-6">
            <x-slot:title>{{ __('admin.portal_analytics.timeline.title') }}</x-slot:title>
            <div class="p-6">
                <div class="relative h-72"><canvas id="pa-timeline"></canvas></div>
                <p class="mt-3 text-xs text-gray-500">{{ __('admin.portal_analytics.timeline.registration_note') }}</p>
            </div>
        </x-card>

        {{-- Top pages --}}
        <x-card class="mb-6">
            <x-slot:title>{{ __('admin.portal_analytics.top_pages.title') }}</x-slot:title>
            <div class="table-responsive p-6">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                            <th class="py-2 pe-4 text-start font-semibold">{{ __('admin.portal_analytics.top_pages.page') }}</th>
                            <th class="py-2 px-4 text-end font-semibold">{{ __('admin.portal_analytics.top_pages.views') }}</th>
                            <th class="py-2 ps-4 text-end font-semibold">{{ __('admin.portal_analytics.top_pages.visitors') }}</th>
                        </tr>
                    </thead>
                    <tbody id="pa-top-pages" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </x-card>

        <p class="text-xs text-gray-400 mb-8">{{ __('admin.portal_analytics.privacy_note') }}</p>
    </div>
@endsection
