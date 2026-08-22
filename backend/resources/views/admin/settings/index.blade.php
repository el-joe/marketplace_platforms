@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/admin/settings.js'])
@endpush

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            saving: @json(__('admin.settings_section.saving')),
            settingsSaved: @json(__('admin.settings_section.settings_saved')),
            validationFailed: @json(__('admin.settings_section.validation_failed')),
            failedToSaveSettings: @json(__('admin.settings_section.failed_to_save_settings')),
            clearCacheTitle: @json(__('admin.settings_section.clear_cache_title')),
            clearCacheMessage: @json(__('admin.settings_section.clear_cache_message')),
            clearCacheConfirm: @json(__('admin.settings_section.clear_cache_confirm')),
            cacheCleared: @json(__('admin.settings_section.cache_cleared')),
            failedToClearCache: @json(__('admin.settings_section.failed_to_clear_cache')),
            gatewayReachable: @json(__('admin.settings_section.gateway_reachable')),
            gatewayTestFailed: @json(__('admin.settings_section.gateway_test_failed')),
            enterValidRate: @json(__('admin.settings_section.enter_valid_rate')),
            rateUpdated: @json(__('admin.settings_section.rate_updated')),
            failedToUpdateRate: @json(__('admin.settings_section.failed_to_update_rate')),
            ratesRefreshQueued: @json(__('admin.settings_section.rates_refresh_queued')),
            failedToQueueRefresh: @json(__('admin.settings_section.failed_to_queue_refresh')),
        });
    </script>
@endpush

@section('title', __('admin.settings_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.settings_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.settings_section.manage_desc') }}</p>
        </div>
        <button id="btn-clear-cache" type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
            <x-heroicon name="arrow-path" class="w-4 h-4" />
            {{ __('admin.settings_section.clear_cache') }}
        </button>
    </div>

    {{-- ─── URL-based Tab Navigation ─────────────────────────────────────────── --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-0 overflow-x-auto" aria-label="{{ __('admin.settings_section.categories_nav') }}">

            @php
                $tabIcons = [
                    'appearance' => 'adjustments-horizontal',
                    'customers' => 'users',
                    'general' => 'cog-6-tooth',
                    'integrations' => 'cube',
                    'notifications' => 'bell',
                    'orders' => 'shopping-cart',
                    'security' => 'shield-check',
                    'vendors' => 'building-storefront',
                    'promotion' => 'megaphone',
                ];
                $tabLabels = [
                    'appearance' => __('admin.settings_section.appearance'),
                    'customers' => __('admin.settings_section.customers_tab'),
                    'general' => __('admin.settings_section.general'),
                    'integrations' => __('admin.settings_section.integrations'),
                    'notifications' => __('admin.settings_section.notifications_tab'),
                    'orders' => __('admin.settings_section.orders_tab'),
                    'security' => __('admin.settings_section.security'),
                    'vendors' => __('admin.settings_section.vendors_tab'),
                    'promotion' => __('admin.settings_section.promotion_tab'),
                ];
            @endphp

            @foreach($categories as $cat)
                @php $icon = $tabIcons[$cat] ?? 'cog-6-tooth'; @endphp
                <a href="{{ route('admin.settings.index', ['tab' => $cat]) }}" class="flex items-center gap-1.5 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-colors
                                        {{ $activeCategory === $cat
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                    <x-heroicon :name="$icon" class="w-4 h-4" />
                    {{ $tabLabels[$cat] ?? ucfirst($cat) }}
                </a>
            @endforeach

        </nav>
    </div>

    {{-- ─── Active Category Form ──────────────────────────────────────────────── --}}
    <form id="settings-form" data-category="{{ $activeCategory }}" novalidate>
        @csrf

        @include('admin.settings.partials.' . $activeCategory)

        {{-- Sticky save bar --}}
        <div class="sticky bottom-0 z-10 mt-6 -mx-6 border-t border-gray-200 bg-white px-6 py-4 shadow-md">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ __('admin.settings_section.changes_apply_note') }}</p>
                <button type="submit" id="btn-save-settings"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-blue-700 disabled:opacity-60">
                    <x-heroicon name="check-circle" class="w-4 h-4" />
                    {{ __('admin.settings_section.save_settings', ['category' => $tabLabels[$activeCategory] ?? ucfirst($activeCategory)]) }}
                </button>
            </div>
        </div>

    </form>

@endsection