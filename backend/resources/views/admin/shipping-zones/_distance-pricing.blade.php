{{-- Distance-based delivery for the selected zone (client item #19). Driven by resources/js/admin/shipping-distance-pricing.js --}}
<div x-show="activeTab === 'distance'" x-cloak class="p-4 space-y-4" id="distance-pricing-panel">
    <p class="text-sm text-gray-600">{{ __('distance_delivery.admin.intro') }}</p>

    {{-- Stores / warehouses of the zone's country without coordinates (filled by shipping-distance-pricing.js) --}}
    <div id="dp-coverage" class="hidden rounded-lg border px-3 py-2 text-sm space-y-1"></div>

    <div class="flex flex-wrap items-end gap-3">
        <div class="space-y-1">
            <label for="dp-method" class="block text-sm font-medium text-gray-700">{{ __('distance_delivery.admin.method') }}</label>
            <select id="dp-method" class="form-input text-sm w-56">
                <option value="">{{ __('distance_delivery.admin.select_method') }}</option>
                @foreach($methods as $m)
                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="text-xs text-gray-500">
            {{ __('distance_delivery.admin.configured_methods') }}:
            <span id="dp-configured" class="font-medium text-gray-700">—</span>
        </div>
    </div>

    <form id="dp-form" class="hidden space-y-4" novalidate>
        <p id="dp-not-configured" class="hidden text-sm text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
            {{ __('distance_delivery.admin.not_configured') }}
        </p>

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="dp-is-active" class="form-checkbox" checked>
            <span class="text-sm text-gray-700">{{ __('distance_delivery.admin.enabled') }}</span>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1">
                <label for="dp-free-radius" class="block text-sm font-medium text-gray-700">{{ __('distance_delivery.admin.free_radius_km') }}</label>
                <input type="text" inputmode="decimal" id="dp-free-radius" class="form-input w-full" dir="ltr">
                <p class="text-xs text-gray-500">{{ __('distance_delivery.admin.free_radius_hint') }}</p>
            </div>
            <div class="space-y-1">
                <label for="dp-max-radius" class="block text-sm font-medium text-gray-700">{{ __('distance_delivery.admin.max_service_radius_km') }}</label>
                <input type="text" inputmode="decimal" id="dp-max-radius" class="form-input w-full" dir="ltr">
                <p class="text-xs text-gray-500">{{ __('distance_delivery.admin.max_service_radius_hint') }}</p>
            </div>
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-800">{{ __('distance_delivery.admin.tiers') }}</p>
                <button type="button" id="dp-add-tier" class="btn btn-ghost btn-xs">{{ __('distance_delivery.admin.add_tier') }}</button>
            </div>
            <p class="text-xs text-gray-500">{{ __('distance_delivery.admin.tiers_hint') }}</p>
            <div class="table-responsive">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-start">#</th>
                            <th class="px-3 py-2 text-start">{{ __('distance_delivery.admin.from_km') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('distance_delivery.admin.to_km') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('distance_delivery.admin.price') }} (<span class="dp-currency"></span>)</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="dp-tiers" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
            <ul id="dp-errors" class="hidden text-sm text-red-600 list-disc ps-5 space-y-0.5"></ul>
            <ul id="dp-warnings" class="hidden text-sm text-yellow-700 list-disc ps-5 space-y-0.5"></ul>
        </div>

        <div class="space-y-1">
            <label for="dp-categories" class="block text-sm font-medium text-gray-700">{{ __('distance_delivery.admin.categories') }}</label>
            <select id="dp-categories" multiple size="6" class="form-input w-full text-sm"></select>
            <p class="text-xs text-gray-500">{{ __('distance_delivery.admin.categories_hint') }}</p>
        </div>

        <div class="border rounded-lg p-3 space-y-2">
            <p class="text-sm font-semibold text-gray-800">{{ __('distance_delivery.admin.preview') }}</p>
            <div id="dp-preview-no-key" class="hidden text-xs text-gray-500">{{ __('distance_delivery.admin.preview_no_key') }}</div>
            <div id="dp-preview-no-locations" class="hidden text-xs text-gray-500">{{ __('distance_delivery.admin.preview_no_coordinates') }}</div>
            <div id="dp-preview-controls" class="hidden space-y-2">
                <p class="text-xs text-gray-500">{{ __('distance_delivery.admin.preview_hint') }}</p>
                <select id="dp-preview-location" class="form-input text-sm w-64" aria-label="{{ __('distance_delivery.admin.preview_warehouse') }}"></select>
                <div id="dp-map" class="w-full rounded-lg overflow-hidden" style="height: 320px;"></div>
            </div>
        </div>

        <div class="flex justify-between gap-2 pt-2 border-t">
            <button type="button" id="dp-delete" class="btn btn-ghost text-red-600 hidden">{{ __('distance_delivery.admin.delete') }}</button>
            <button type="submit" id="dp-save" class="btn btn-primary ms-auto">{{ __('distance_delivery.admin.save') }}</button>
        </div>
    </form>
</div>
