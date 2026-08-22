{{-- Security Settings Partial --}}
<x-card>
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.security_access') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.security_access_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings as $setting)
                @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>
