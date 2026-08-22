@extends('layouts.admin')

@section('title', __('admin.activity_log_section.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            systemShort: @json(__('admin.activity_log_section.system_short')),
            unknown: @json(__('admin.activity_log_section.unknown')),
            view: @json(__('admin.activity_log_section.view')),
            failedToLoadDetail: @json(__('admin.activity_log_section.failed_to_load_detail')),
        });
    </script>
    @vite('resources/js/admin/activity-log.js')
@endpush

@section('content')

    {{-- ─── Header ─────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.activity_log_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.activity_log_section.description') }}</p>
        </div>
        <x-export-dropdown />
    </div>

    {{-- ─── Stats ─────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.activity_log_section.total_today') }}" :value="number_format($stats['total_today'])" />
        <x-stat-card title="{{ __('admin.activity_log_section.admin_actions') }}" :value="number_format($stats['admin_actions'])" />
        <x-stat-card title="{{ __('admin.activity_log_section.deletions') }}" :value="number_format($stats['deletions'])" />
        <x-stat-card title="{{ __('admin.activity_log_section.last_activity') }}" :value="$stats['last_activity']" />
    </div>

    {{-- ─── Filters ───────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="activity-filter-form" class="grid grid-cols-1 md:grid-cols-3 gap-3">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" name="search" class="form-input w-full text-sm" placeholder="{{ __('admin.activity_log_section.description') }}">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.activity_log_section.log_name') }}</label>
                <input type="text" name="log_name" class="form-input w-full text-sm" placeholder="{{ __('admin.activity_log_section.log_name_placeholder') }}">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.activity_log_section.event') }}</label>
                <select name="event" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.activity_log_section.all_event') }}</option>
                    <option value="created">{{ __('admin.activity_log_section.created') }}</option>
                    <option value="updated">{{ __('admin.activity_log_section.updated') }}</option>
                    <option value="deleted">{{ __('admin.activity_log_section.deleted') }}</option>
                    <option value="restored">{{ __('admin.activity_log_section.restored') }}</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.activity_log_section.causer_type') }}</label>
                <select name="causer_type" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.activity_log_section.all_causers') }}</option>
                    @foreach($causerTypes as $class => $info)
                        <option value="{{ $class }}">{{ $info['label'] }}s</option>
                    @endforeach
                    <option value="system">{{ __('admin.activity_log_section.system') }}</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.activity_log_section.subject_type') }}</label>
                <select name="subject_type" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.activity_log_section.all_subjects') }}</option>
                    @foreach($subjectTypes as $class => $label)
                        <option value="{{ $class }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.activity_log_section.causer') }}</label>
                @php
                    $causerSelectConfig = json_encode(
                        ['url' => route('admin.activity-log.causer-search'), 'param' => 'q', 'multiple' => false, 'minLength' => 2, 'delay' => 300],
                        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                    );
                @endphp
                <select name="causer_id" id="filter-causer-id"
                    class="block w-full rounded-lg border border-gray-300 text-sm" data-async-select
                    data-config='{!! $causerSelectConfig !!}' placeholder="{{ __('admin.activity_log_section.causer_search_placeholder') }}"></select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.activity_log_section.ip_address') }}</label>
                <input type="text" name="ip_address" class="form-input w-full text-sm" placeholder="{{ __('admin.activity_log_section.ip_placeholder') }}">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.activity_log_section.date_from') }}</label>
                <input type="date" name="date_from" class="form-input w-full text-sm">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.activity_log_section.date_to') }}</label>
                <input type="date" name="date_to" class="form-input w-full text-sm">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.activity_log_section.apply_filters') }}</button>
                <button type="button" id="clear-activity-filters" class="btn btn-ghost btn-sm text-gray-500">{{ __('admin.activity_log_section.clear_filters') }}</button>
            </div>
        </form>
    </x-card>

    {{-- ─── Table ─────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="activity-log-table" data-url="{{ route('admin.activity-log.datatable') }}" class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.activity_log_section.time') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.activity_log_section.causer') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.activity_log_section.event') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.activity_log_section.subject') }}</th>
                        <th class="pb-3 pr-4">{{ __('admin.activity_log_section.description') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap">{{ __('admin.activity_log_section.log') }}</th>
                        <th class="pb-3 pr-4 whitespace-nowrap hidden md:table-cell">{{ __('admin.activity_log_section.ip_address') }}</th>
                        <th class="pb-3"></th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    {{-- ─── Detail modal ──────────────────────────────────────────────────── --}}
    <x-modal id="activity-detail-modal" size="xl" title="{{ __('admin.activity_log_section.activity_detail') }}">
        <div class="space-y-5">

            {{-- Header: event + description --}}
            <div class="flex items-start gap-3">
                <span id="detail-event-badge"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">—</span>
                <p id="detail-description" class="text-sm text-gray-700 flex-1"></p>
            </div>

            {{-- Two-column meta --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="space-y-2.5">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.activity_log_section.who_and_when') }}</h4>
                    <dl class="text-sm space-y-1.5">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.causer') }}</dt>
                            <dd id="detail-causer-name" class="text-gray-900 font-medium text-end"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.type') }}</dt>
                            <dd id="detail-causer-type" class="text-gray-700 text-end"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.email') }}</dt>
                            <dd id="detail-causer-email" class="text-gray-700 text-end break-all"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.when') }}</dt>
                            <dd id="detail-created-at" class="text-gray-700 text-end"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.ip_label') }}</dt>
                            <dd id="detail-ip" class="text-gray-700 font-mono text-xs text-end"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.log_name') }}</dt>
                            <dd id="detail-log-name" class="text-gray-700 text-end"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.batch') }}</dt>
                            <dd id="detail-batch" class="text-gray-700 font-mono text-xs text-end break-all"></dd>
                        </div>
                    </dl>
                    <details class="mt-2">
                        <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">{{ __('admin.activity_log_section.user_agent') }}</summary>
                        <p id="detail-user-agent" class="text-xs text-gray-600 mt-1 break-all"></p>
                    </details>
                </div>

                <div class="space-y-2.5">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.activity_log_section.subject') }}</h4>
                    <dl class="text-sm space-y-1.5">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.type') }}</dt>
                            <dd id="detail-subject-type" class="text-gray-700 text-end"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.name') }}</dt>
                            <dd id="detail-subject-name" class="text-gray-900 font-medium text-end break-all"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">{{ __('admin.activity_log_section.id_label') }}</dt>
                            <dd id="detail-subject-id" class="text-gray-500 font-mono text-xs text-end break-all"></dd>
                        </div>
                    </dl>
                    <a id="detail-subject-link" href="#" target="_blank" class="hidden btn btn-secondary btn-xs">{{ __('admin.activity_log_section.open_subject') }}</a>
                </div>
            </div>

            {{-- Changes diff --}}
            <div id="detail-changes-section" class="hidden">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('admin.activity_log_section.changes') }}</h4>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                <th class="px-3 py-2">{{ __('admin.activity_log_section.field') }}</th>
                                <th class="px-3 py-2">{{ __('admin.activity_log_section.old') }}</th>
                                <th class="px-3 py-2">{{ __('admin.activity_log_section.new') }}</th>
                            </tr>
                        </thead>
                        <tbody id="detail-changes-tbody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            {{-- Raw JSON --}}
            <details>
                <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">{{ __('admin.activity_log_section.raw_json') }}</summary>
                <pre id="detail-raw-json" class="text-xs bg-gray-50 p-3 rounded overflow-x-auto mt-2"></pre>
            </details>
        </div>
    </x-modal>

@endsection
