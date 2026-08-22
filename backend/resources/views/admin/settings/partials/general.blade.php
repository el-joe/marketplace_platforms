{{-- General Settings Partial --}}
@php
    $keys = [
        'site_name',
        'site_tagline',
        'support_email',
        'support_phone',
        'default_country_code',
        'default_locale',
        'platform_base_currency',
        'maintenance_mode',
        'maintenance_message',
        'logo_file_id',
        'favicon_url',
    ];
@endphp

<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.general') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.core_platform') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

        {{-- Default Country select (special case) --}}
        @php $countrySetting = $settings->firstWhere('key', 'default_country_code'); @endphp
        @if($countrySetting)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-2 py-5">
                <div class="sm:col-span-1">
                    <label for="setting-default_country_code" class="block text-sm font-medium text-gray-800">{{ __('admin.settings_section.default_country') }}</label>
                    <p class="mt-1 text-xs text-gray-500">{{ $countrySetting->description }}</p>
                    <p class="mt-1 text-xs font-mono text-gray-400">default_country_code</p>
                </div>
                <div class="sm:col-span-2">
                    <select id="setting-default_country_code" name="settings[default_country_code]"
                        class="form-input w-full sm:max-w-xs text-sm">
                        @foreach($countries as $country)
                            <option value="{{ $country->site_code }}" {{ $countrySetting->value === $country->site_code ? 'selected' : '' }}>
                                {{ $country->name_en }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        @foreach($settings->whereIn('key', ['default_locale', 'platform_base_currency', 'maintenance_mode', 'maintenance_message', 'logo_file_id', 'favicon_url']) as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>
