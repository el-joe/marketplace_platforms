{{-- Orders Settings Partial --}}
<x-card class="mb-6">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.settings_section.orders_cart') }}</h2>
        <p class="text-sm text-gray-500">{{ __('admin.settings_section.orders_cart_desc') }}</p>
    </div>
    <div class="divide-y divide-gray-100">

        @foreach($settings as $setting)
                @include('admin.settings.partials._field', ['setting' => $setting])
        @endforeach

    </div>
</x-card>
