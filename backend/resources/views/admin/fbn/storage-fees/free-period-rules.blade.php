@extends('layouts.admin')

@section('title', __('admin.fbn_section.free_period_rules_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.fbn_section.free_period_rules_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.fbn_section.free_period_rules_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.fbn.storage-fees.index') }}" class="btn-secondary btn-sm">{{ __('admin.fbn_section.storage_fees_title') }}</a>
            <button type="button" id="btn-add-rule" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('admin.fbn_section.add_rule') }}
            </button>
        </div>
    </div>

    <x-card padding="none">
        <table id="free-period-rules-table" class="table-base w-full">
            <thead>
                <tr>
                    <th>{{ __('admin.fbn_section.min_weight_grams') }}</th>
                    <th>{{ __('admin.fbn_section.max_weight_grams') }}</th>
                    <th>{{ __('admin.fbn_section.free_days') }}</th>
                    <th class="text-center w-24">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody id="free-period-rules-body">
                @foreach($rules as $rule)
                    <tr data-id="{{ $rule->id }}">
                        <td>{{ number_format($rule->min_weight_grams) }}</td>
                        <td>{{ $rule->max_weight_grams !== null ? number_format($rule->max_weight_grams) : '—' }}</td>
                        <td>{{ $rule->free_days }}</td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button type="button" class="btn-edit-rule p-1 rounded text-gray-400 hover:text-primary-600"
                                        data-id="{{ $rule->id }}"
                                        data-min="{{ $rule->min_weight_grams }}"
                                        data-max="{{ $rule->max_weight_grams }}"
                                        data-days="{{ $rule->free_days }}" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button type="button" class="btn-delete-rule p-1 rounded text-gray-400 hover:text-danger-600" data-id="{{ $rule->id }}" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9.5 3h5a1 1 0 011 1v3h-7V4a1 1 0 011-1z"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>

    <x-modal id="free-period-rule-modal" title="{{ __('admin.fbn_section.manage_free_period_rules') }}" size="md">
        <form id="free-period-rule-form" novalidate x-data="{ openEnded: false }">
            @csrf
            <input type="hidden" id="rule-id">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-form-input name="min_weight_grams" id="rule-min-weight" type="number" step="1" min="0"
                                  label="{{ __('admin.fbn_section.min_weight_grams') }}" required />
                </div>
                <div>
                    <x-form-input name="max_weight_grams" id="rule-max-weight" type="number" step="1" min="0"
                                  label="{{ __('admin.fbn_section.max_weight_grams') }}" x-bind:disabled="openEnded" />
                    <label class="mt-1.5 flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" id="rule-open-ended" x-model="openEnded"
                               class="rounded border-gray-300 text-primary-600 focus:ring-primary-200">
                        {{ __('admin.fbn_section.open_ended') }}
                    </label>
                </div>
                <div>
                    <x-form-input name="free_days" id="rule-free-days" type="number" step="1" min="0"
                                  label="{{ __('admin.fbn_section.free_days') }}" required />
                </div>
            </div>

            <x-slot:footer>
                <button type="button" data-modal-close class="btn-secondary">{{ __('common.cancel') }}</button>
                <button type="submit" form="free-period-rule-form" class="btn-primary">{{ __('common.save') }}</button>
            </x-slot:footer>
        </form>
    </x-modal>

@endsection

@push('scripts')
    @vite(['resources/js/admin/storage-fee-free-period-rules.js'])
    <script type="module">
        window.FREE_PERIOD_RULES_ROUTES = {
            store: @json(route('admin.fbn.storage-fees.free-period-rules.store')),
            update: @json(route('admin.fbn.storage-fees.free-period-rules.update', ['freePeriodRule' => '__ID__'])),
            destroy: @json(route('admin.fbn.storage-fees.free-period-rules.destroy', ['freePeriodRule' => '__ID__'])),
        };
        window.FREE_PERIOD_RULES_I18N = {
            deleteConfirm: @json(__('admin.fbn_section.delete_rule_confirm')),
            ruleSaved: @json(__('admin.fbn_section.rule_saved')),
            ruleDeleted: @json(__('admin.fbn_section.rule_deleted')),
        };
    </script>
@endpush
