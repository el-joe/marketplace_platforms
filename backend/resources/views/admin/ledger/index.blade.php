@extends('layouts.admin')

@push('styles')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            failedToLoadGroup: @json(__('admin.ledger_section.failed_to_load_group')),
        });
    </script>
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/ledger.js'])
@endpush

@section('title', __('admin.ledger_section.title') . ' — ' . __('admin.ledger_section.read_only'))

@section('content')

    {{-- ─── Header ───────────────────────────────────────────────────────────────── --}}
    <div class="mb-5 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                {{ __('admin.ledger_section.title') }}
                <span
                    class="ms-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">{{ __('admin.ledger_section.read_only') }}</span>
                <span
                    class="ms-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">{{ __('admin.ledger_section.append_only') }}</span>
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.ledger_section.subtitle') }}</p>
        </div>
    </div>

    {{-- ─── Read-Only Warning Banner ───────────────────────────────────────────── --}}
    <div class="mb-5 flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        <svg class="w-5 h-5 flex-shrink-0 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                clip-rule="evenodd" />
        </svg>
        <span>{!! __('admin.ledger_section.readonly_warning') !!}</span>
    </div>

    {{-- ─── Stats Row ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <x-stat-card title="{{ __('admin.ledger_section.cards.total_debit') }}" :value="'$' . number_format($stats['total_debit'] / 100, 2)"
            iconBg="bg-green-100 text-green-600" />

        <x-stat-card title="{{ __('admin.ledger_section.cards.total_credit') }}" :value="'$' . number_format($stats['total_credit'] / 100, 2)"
            iconBg="bg-blue-100 text-blue-600" />

        <div
            class="rounded-xl border {{ $stats['balanced'] ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }} p-4">
            <p class="text-xs font-medium {{ $stats['balanced'] ? 'text-green-600' : 'text-red-600' }} uppercase mb-1">
                {{ __('admin.ledger_section.cards.balance_check') }}</p>
            @if($stats['balanced'])
                <p class="text-xl font-bold text-green-700">✓ {{ __('admin.ledger_section.cards.balanced') }}</p>
                <p class="text-xs text-green-600 mt-0.5">{{ __('admin.ledger_section.cards.balance_formula') }}</p>
            @else
                <p class="text-xl font-bold text-red-700">⚠ {{ __('admin.ledger_section.cards.imbalance') }}</p>
                <p class="text-xs text-red-600 mt-0.5" dir="ltr">{{ __('admin.ledger_section.cards.difference') }}: ${{ number_format($stats['difference'] / 100, 2) }}</p>
            @endif
        </div>
    </div>

    {{-- ─── Filter bar ──────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ledger_section.search_description') }}</label>
                <input type="text" id="ledger-search-input" class="form-input w-full text-sm" placeholder="{{ __('common.description') }}…">
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ledger_section.account_type') }}</label>
                <select id="ledger-filter-account-type" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.ledger_section.all_account_types') }}</option>
                    @foreach($accountTypes as $type)
                        <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-28">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.currency') }}</label>
                <input type="text" id="ledger-filter-currency" class="form-input w-full text-sm" placeholder="USD" dir="ltr">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.from') }}</label>
                <input type="date" id="ledger-filter-date-from" class="form-input w-full text-sm" dir="ltr">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.to') }}</label>
                <input type="date" id="ledger-filter-date-to" class="form-input w-full text-sm" dir="ltr">
            </div>
            <div class="w-56">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ledger_section.transaction_group_id') }}</label>
                <input type="text" id="ledger-filter-group-id" class="form-input w-full text-sm font-mono"
                    placeholder="UUID…" dir="ltr">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.ledger_section.reference_type') }}</label>
                <input type="text" id="ledger-filter-reference-type" class="form-input w-full text-sm"
                    placeholder="{{ __('admin.ledger_section.reference_type_placeholder') }}">
            </div>
            <div>
                <button type="button" id="ledger-clear-filters" class="btn btn-secondary btn-sm">{{ __('common.clear') }}</button>
            </div>
        </div>
    </x-card>

    {{-- ─── DataTable (NO actions column) ──────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="ledger-table" class="w-full" style="width:100%">
                <thead>
                    <tr>
                        <th>{{ __('admin.ledger_section.data_table.created_at') }}</th>
                        <th>{{ __('admin.ledger_section.data_table.group_id') }}</th>
                        <th>{{ __('admin.ledger_section.data_table.account_type') }}</th>
                        <th>{{ __('admin.ledger_section.data_table.holder') }}</th>
                        <th>{{ __('admin.ledger_section.data_table.debit') }}</th>
                        <th>{{ __('admin.ledger_section.data_table.credit') }}</th>
                        <th>{{ __('admin.ledger_section.data_table.currency') }}</th>
                        <th>{{ __('admin.ledger_section.data_table.reference') }}</th>
                        <th>{{ __('admin.ledger_section.data_table.description') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

    {{-- ─── Transaction Group Modal ─────────────────────────────────────────────── --}}
    <x-modal id="ledger-group-modal" title="{{ __('admin.ledger_section.transaction_group') }}" size="lg">
        <div id="ledger-group-content">
            <p class="text-sm text-gray-500">{{ __('common.loading') }}</p>
        </div>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" onclick="$('#ledger-group-modal').modal('close')">{{ __('common.close') }}</button>
        </x-slot>
    </x-modal>

@endsection
