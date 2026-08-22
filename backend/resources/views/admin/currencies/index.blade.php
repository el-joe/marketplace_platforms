@extends('layouts.admin')

@push('head')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            queueSyncConfirm: @json(__('admin.currencies_section.queue_sync_confirm')),
            queuing: @json(__('admin.currencies_section.queuing')),
            dispatchFailed: @json(__('admin.currencies_section.dispatch_failed')),
            syncExchangeRates: @json(__('admin.currencies_section.sync_rates')),
        });
    </script>
    @vite('resources/js/admin/currencies.js')
@endpush

@section('title', __('admin.currencies_section.title_full'))

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div></div>
            <button type="button" id="btn-dispatch-rate-update" data-url="{{ route('admin.currencies.dispatch-update') }}"
                class="btn btn-secondary">
                <x-heroicon name="arrow-path" class="w-4 h-4" />
                {{ __('admin.currencies_section.sync_rates') }}
            </button>
        </div>

        <x-card>
            <x-slot name="header">
                <h2 class="text-base font-semibold text-gray-800">{{ __('admin.currencies_section.all_currencies') }}</h2>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-gray-100 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <th class="px-4 py-3">{{ __('admin.currencies_section.currency_code') }}</th>
                            <th class="px-4 py-3">{{ __('admin.currencies_section.currency_name') }}</th>
                            <th class="px-4 py-3">{{ __('admin.currencies_section.symbol') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('admin.currencies_section.rate_to_base_col') }}</th>
                            <th class="px-4 py-3">{{ __('admin.currencies_section.base_col') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('admin.currencies_section.decimals_col') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('admin.currencies_section.override_col') }}</th>
                            <th class="px-4 py-3">{{ __('admin.currencies_section.last_updated') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('common.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($currencies as $currency)
                            <tr class="hover:bg-gray-50 transition-colors {{ !$currency->is_active ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3">
                                    <span class="font-mono font-bold text-gray-900">{{ $currency->code }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $currency->name }}</td>
                                <td class="px-4 py-3 text-gray-500 font-mono">{{ $currency->symbol }}</td>
                                <td class="px-4 py-3 text-end font-mono text-gray-800">
                                    {{ number_format($currency->exchange_rate_to_base, 6) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-mono text-xs text-gray-500">{{ $currency->base_currency_code }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-500">{{ $currency->decimal_places }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($currency->is_manually_overridden)
                                        <x-badge color="warning" size="xs">{{ __('admin.currencies_section.manual') }}</x-badge>
                                    @else
                                        <span class="text-gray-300 text-xs">{{ __('admin.currencies_section.auto') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs">
                                    {{ $currency->rate_updated_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($currency->is_active)
                                        <x-badge color="success" size="xs">{{ __('common.active') }}</x-badge>
                                    @else
                                        <x-badge color="gray" size="xs">{{ __('admin.geography.inactive') }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <a href="{{ route('admin.currencies.edit', $currency->code) }}"
                                        class="btn btn-ghost btn-sm">{{ __('common.edit') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-gray-400">{{ __('admin.currencies_section.no_currencies') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection