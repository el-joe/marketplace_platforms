{{-- Business Info Settings Partial --}}
<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.business_tab') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.business_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach ($settings as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>

<x-card class="mb-6">
    <div class="mb-3">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.business_placeholders') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.business_placeholders_desc') }}</p>
    </div>
    @include('admin.static-pages._placeholders')
</x-card>
