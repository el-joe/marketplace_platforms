{{--
    Edit form partial for VendorListing (admin panel).
    Requires: $listing, $warehouses, $shippingMethods, $statuses
--}}
@php
    $val = fn(string $f, $d = '') => old($f, (($listing->{$f} ?? null) instanceof \BackedEnum ? $listing->{$f}->value : ($listing->{$f} ?? $d)));
@endphp

<div class="max-w-2xl bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('admin.vendor_listings.edit_title') }}</h2>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_listings.status_col') }}</label>
        <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" {{ $val('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_listings.warehouse_col') }}</label>
        <select name="warehouse_id" data-select2-init
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">—</option>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" {{ $val('warehouse_id') === $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        @error('warehouse_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendor_listings.primary_shipping_method') }}</label>
        <select name="primary_shipping_method_id" data-select2-init
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">{{ __('admin.admin_listings.shipping_method_use_category_default') }}</option>
            @foreach($shippingMethods as $method)
                <option value="{{ $method->id }}" {{ $val('primary_shipping_method_id') === $method->id ? 'selected' : '' }}>
                    {{ $method->name }}
                </option>
            @endforeach
        </select>
        @error('primary_shipping_method_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="pt-2 flex items-center gap-3">
        <button type="submit" class="btn btn-primary btn-sm">{{ __('common.save') }}</button>
        <a href="{{ route('admin.vendor-listings.show', $listing) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('common.cancel') }}</a>
    </div>
</div>
