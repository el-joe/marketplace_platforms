{{-- Notifications Settings Partial --}}
<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.admin_notifications') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.admin_notifications_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>

<x-card>
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.customer_vendor_notifications') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.customer_vendor_notifications_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings->whereIn('key', [
            'vendor_welcome_email_enabled',
            'customer_order_email_enabled',
            'sms_notifications_enabled',
            'push_notifications_enabled',
        ]) as $setting)
            @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>
