{{-- Integrations Settings Partial --}}

{{-- Security notice --}}
<div class="mb-5 flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
    <x-heroicon name="shield-check" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
    <p>{!! str_replace(':encrypted', '<strong>' . __('admin.settings_section.encrypted_at_rest') . '</strong>', __('admin.settings_section.security_notice')) !!}</p>
</div>

{{-- Payment Gateway --}}
<x-card class="mb-6">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.payment_gateway') }}</h2>
            <p class="text-sm text-gray-500">{{ __('admin.settings_section.payment_gateway_desc') }}</p>
        </div>
        <button
            type="button"
            id="btn-test-gateway"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
        >
            <x-heroicon name="bolt" class="w-3.5 h-3.5" />
            {{ __('admin.settings_section.test_connection') }}
        </button>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>

{{-- SMS Gateway --}}
<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.sms_gateway') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.sms_gateway_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings->whereIn('key', [
            'sms_gateway_provider',
            'sms_gateway_api_key',
        ]) as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>

{{-- Other APIs --}}
<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.other_apis') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.other_apis_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings->whereIn('key', [
            'firebase_server_key',
            'google_maps_api_key',
            'meilisearch_host',
            'meilisearch_key',
            'exchange_rate_api_key',
        ]) as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>

{{-- Currency Exchange Rates --}}
<x-card>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.exchange_rates') }}</h2>
            <p class="text-sm text-gray-500">{{ __('admin.settings_section.exchange_rates_desc') }}</p>
        </div>
        <button
            type="button"
            id="btn-refresh-rates"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
        >
            <x-heroicon name="arrow-path" class="w-3.5 h-3.5" />
            {{ __('admin.settings_section.refresh_from_api') }}
        </button>
    </div>

    <div id="rates-table-wrapper" class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200">
                <tr class="text-start text-xs font-medium text-gray-500">
                    <th class="pb-2 pr-4">{{ __('admin.currency') }}</th>
                    <th class="pb-2 pr-4">{{ __('admin.settings_section.code_col') }}</th>
                    <th class="pb-2 pr-4">{{ __('admin.settings_section.rate_to_base_col') }}</th>
                    <th class="pb-2 pr-4">{{ __('admin.settings_section.source') }}</th>
                    <th class="pb-2">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody id="rates-table-body" class="divide-y divide-gray-100">
                @foreach($currencies as $currency)
                <tr data-currency-code="{{ $currency->code }}">
                    <td class="py-2 pr-4 font-medium text-gray-900">{{ $currency->name }}</td>
                    <td class="py-2 pr-4 font-mono text-gray-600">{{ $currency->code }}</td>
                    <td class="py-2 pr-4">
                        <input
                            type="number"
                            class="form-input w-32 text-sm rate-input"
                            value="{{ $currency->exchange_rate_to_base }}"
                            step="0.000001"
                            min="0.000001"
                            data-currency-code="{{ $currency->code }}"
                        >
                    </td>
                    <td class="py-2 pr-4 text-xs text-gray-500">
                        @if($currency->is_manually_overridden)
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs bg-amber-100 text-amber-700">{{ __('admin.settings_section.manual') }}</span>
                        @else
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs bg-green-100 text-green-700">{{ __('admin.settings_section.api') }}</span>
                        @endif
                        @if($currency->rate_updated_at)
                            <br><span class="text-gray-400">{{ $currency->rate_updated_at->diffForHumans() }}</span>
                        @endif
                    </td>
                    <td class="py-2">
                        <button
                            type="button"
                            class="btn-save-rate text-xs font-medium text-blue-600 hover:text-blue-800"
                            data-currency-code="{{ $currency->code }}"
                        >
                            {{ __('admin.settings_section.save_rate') }}
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
