{{--
    Shared International Shipping Rate form partial.
    Include with: @include('admin.international-shipping-rates._form', ['mode' => 'create'])
                  @include('admin.international-shipping-rates._form', ['mode' => 'edit', 'rate' => $rate])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
--}}
@php
    $isEdit = $mode === 'edit';

    $val = function (string $field, $default = '') use ($rate) {
        return old($field, $rate->{$field} ?? $default);
    };

    $bool = function (string $field, bool $default = false) use ($rate) {
        return (bool) old($field, $rate->{$field} ?? $default);
    };
@endphp

<div class="max-w-2xl bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
        {{ $isEdit ? __('admin.international_shipping.edit_rate') : __('admin.international_shipping.add_rate') }}
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.origin') }} <span class="text-danger-500">*</span></label>
            <select name="origin_country_id" required data-select2-init
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('admin.international_shipping.select_country') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" {{ $val('origin_country_id') === $country->id ? 'selected' : '' }}>{{ $country->name_en }}</option>
                @endforeach
            </select>
            @error('origin_country_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.destination') }} <span class="text-danger-500">*</span></label>
            <select name="destination_country_id" required data-select2-init
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('admin.international_shipping.select_country') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" {{ $val('destination_country_id') === $country->id ? 'selected' : '' }}>{{ $country->name_en }}</option>
                @endforeach
            </select>
            @error('destination_country_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.carrier') }}</label>
        <select name="carrier_id" data-select2-init
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">{{ __('admin.international_shipping.generic_fallback') }}</option>
            @foreach($carriers as $carrier)
                <option value="{{ $carrier->id }}" {{ $val('carrier_id') === $carrier->id ? 'selected' : '' }}>{{ $carrier->name }}</option>
            @endforeach
        </select>
        <p class="text-xs text-gray-400 mt-1">{{ __('admin.international_shipping.carrier_help') }}</p>
        @error('carrier_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.base_fee') }} <span class="text-danger-500">*</span></label>
            <input type="number" name="base_fee" value="{{ $val('base_fee', 0) }}" min="0" step="1" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            @error('base_fee')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.rate_per_kg') }} <span class="text-danger-500">*</span></label>
            <input type="number" name="rate_per_kg" value="{{ $val('rate_per_kg', 0) }}" min="0" step="1" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            @error('rate_per_kg')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.customs_fee') }}</label>
        <input type="number" name="customs_fee_flat" value="{{ $val('customs_fee_flat') }}" min="0" step="1"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
        <p class="text-xs text-gray-400 mt-1">{{ __('admin.international_shipping.customs_fee_help') }}</p>
        @error('customs_fee_flat')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.min_eta_days') }} <span class="text-danger-500">*</span></label>
            <input type="number" name="min_eta_days" value="{{ $val('min_eta_days', 0) }}" min="0" step="1" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            @error('min_eta_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.max_eta_days') }} <span class="text-danger-500">*</span></label>
            <input type="number" name="max_eta_days" value="{{ $val('max_eta_days', 0) }}" min="0" step="1" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            @error('max_eta_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <label class="flex items-center gap-2 cursor-pointer">
        <span class="relative inline-flex items-center" dir="ltr">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $bool('is_active', true) ? 'checked' : '' }}>
            <span class="relative w-10 h-5 bg-gray-200 peer-checked:bg-primary-600 rounded-full transition-colors duration-200 block"></span>
            <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
        </span>
        <span class="text-sm text-gray-700">{{ __('common.active') }}</span>
    </label>

    <div class="pt-2 flex items-center gap-3">
        <button type="submit" class="btn btn-primary btn-sm">{{ __('common.save') }}</button>
        <a href="{{ route('admin.international-shipping-rates.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('common.cancel') }}</a>
    </div>
</div>
