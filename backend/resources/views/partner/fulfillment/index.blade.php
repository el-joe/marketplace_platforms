@extends('layouts.partner')

@section('title', __('partner.fulfillment.title'))

@push('styles')
    @vite(['resources/css/app.css', 'resources/js/partner/app.js'])
@endpush

@section('content')

<div class="max-w-6xl mx-auto py-6 px-4" dir="rtl" x-data="fulfillmentApp()">

    {{-- ─── Page Header ─────────────────────────────────────────────────────── --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.fulfillment.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.fulfillment.subtitle') }}</p>
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-indigo-500 uppercase tracking-wide mb-1">{{ __('partner.fulfillment.express_fbn') }}</p>
            <p class="text-2xl font-extrabold text-indigo-700">{{ $stats['fbn_count'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('partner.fulfillment.product_unit') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-blue-500 uppercase tracking-wide mb-1">{{ __('partner.fulfillment.merchant_fbp') }}</p>
            <p class="text-2xl font-extrabold text-blue-700">{{ $stats['fbp_count'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('partner.fulfillment.product_unit') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-green-500 uppercase tracking-wide mb-1">{{ __('partner.fulfillment.marketplace') }}</p>
            <p class="text-2xl font-extrabold text-green-700">{{ $stats['marketplace_count'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('partner.fulfillment.product_unit') }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-yellow-100 bg-yellow-50 p-4 text-center">
            <p class="text-xs text-yellow-600 uppercase tracking-wide mb-1">{{ __('partner.fulfillment.pending_requests') }}</p>
            <p class="text-2xl font-extrabold text-yellow-700">{{ $stats['pending_requests'] }}</p>
            <p class="text-xs text-yellow-500 mt-0.5">{{ __('partner.fulfillment.fbn_requests') }}</p>
        </div>
    </div>

    {{-- ─── Tab Nav ──────────────────────────────────────────────────────────── --}}
    <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6 w-fit">
        <button @click="tab='fbn'"
                :class="tab==='fbn' ? 'bg-white shadow text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 rounded-lg text-sm transition-all">
            {{ __('partner.fulfillment.tab_fbn') }}
        </button>
        <button @click="tab='fbp'"
                :class="tab==='fbp' ? 'bg-white shadow text-blue-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 rounded-lg text-sm transition-all">
            {{ __('partner.fulfillment.tab_fbp') }}
        </button>
        <button @click="tab='marketplace'"
                :class="tab==='marketplace' ? 'bg-white shadow text-green-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 rounded-lg text-sm transition-all">
            {{ __('partner.fulfillment.tab_marketplace') }}
        </button>
        <button @click="tab='fees'; loadFees()"
                :class="tab==='fees' ? 'bg-white shadow text-orange-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 rounded-lg text-sm transition-all">
            {{ __('partner.fulfillment.tab_fees') }}
        </button>
    </div>

    {{-- ─────────────────────────────────────────────────────────────────────── --}}
    {{-- TAB: FBN                                                                --}}
    {{-- ─────────────────────────────────────────────────────────────────────── --}}
    <div x-show="tab==='fbn'" x-cloak class="space-y-5">

        {{-- FBN explainer --}}
        <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 flex gap-3">
            <span class="text-2xl">🚀</span>
            <div>
                <p class="font-semibold text-indigo-800">{{ __('partner.fulfillment.fbn_explainer_title') }}</p>
                <p class="text-sm text-indigo-600 mt-0.5">
                    {{ __('partner.fulfillment.fbn_explainer_desc') }}
                </p>
            </div>
        </div>

        {{-- Submit inbound request button --}}
        <div class="flex justify-between items-center">
            <h2 class="text-base font-bold text-gray-700">{{ __('partner.fulfillment.inbound_requests_title') }}</h2>
            @if($fbnListings->isNotEmpty())
                <button type="button" @click="showFbnForm=true" class="btn btn-primary btn-sm">
                    {{ __('partner.fulfillment.new_inbound_request') }}
                </button>
            @endif
        </div>

        {{-- FBN listings overview --}}
        @if($fbnListings->isEmpty())
            <div class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center text-gray-400">
                <p class="text-2xl mb-2">📦</p>
                <p class="font-medium">{{ __('partner.fulfillment.no_fbn_listings') }}</p>
                <p class="text-sm mt-1">{{ __('partner.fulfillment.no_fbn_listings_desc') }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($fbnListings as $listing)
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <p class="font-semibold text-gray-800 text-sm">{{ $listing->productVariant?->product?->name_ar ?? $listing->productVariant?->product?->name_en ?? __('common.product') }}</p>
                            <p class="text-xs text-gray-400">SKU: {{ $listing->vendor_sku ?? '—' }}</p>
                        </div>
                        <span class="badge badge-indigo text-xs">FBN</span>
                    </div>
                    @foreach($listing->warehouseInventories as $inv)
                        @if($inv->warehouse?->type === \App\Enums\WarehouseType::PlatformFbn)
                        <div class="grid grid-cols-3 gap-1 text-center text-xs mt-2 bg-gray-50 rounded-lg p-2">
                            <div><p class="text-gray-400">{{ __('partner.fulfillment.in_warehouse') }}</p><p class="font-bold text-gray-800">{{ $inv->quantity_on_hand }}</p></div>
                            <div><p class="text-gray-400">{{ __('partner.fulfillment.in_transit') }}</p><p class="font-bold text-blue-600">{{ $inv->quantity_inbound }}</p></div>
                            <div><p class="text-gray-400">{{ __('partner.fulfillment.available') }}</p><p class="font-bold text-green-600">{{ $inv->quantity_available }}</p></div>
                        </div>
                        @endif
                    @endforeach
                </div>
                @endforeach
            </div>

            {{-- Inbound requests table --}}
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">{{ __('partner.fulfillment.requests_table_title') }}</div>
                <div id="fbn-requests-container" class="p-4 text-center text-gray-400 text-sm">
                    {{ __('partner.fulfillment.loading') }}
                </div>
            </div>
        @endif

        {{-- FBN Submit form (Alpine toggle) --}}
        <div x-show="showFbnForm" x-cloak class="bg-white rounded-2xl border border-indigo-100 p-5">
            <h3 class="font-bold text-gray-800 mb-4">{{ __('partner.fulfillment.new_inbound_form_title') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label-sm">{{ __('partner.fulfillment.listing_label') }} <span class="text-red-500">*</span></label>
                    <select id="fbn-listing" class="form-select w-full text-sm">
                        <option value="">{{ __('partner.fulfillment.select_listing') }}</option>
                        @foreach($fbnListings as $listing)
                            <option value="{{ $listing->id }}">
                                {{ $listing->productVariant?->product?->name_ar ?? $listing->productVariant?->product?->name_en }}
                                ({{ $listing->vendor_sku ?? substr($listing->id, 0, 8) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label-sm">{{ __('partner.fulfillment.warehouse_label') }} <span class="text-red-500">*</span></label>
                    <select id="fbn-warehouse" class="form-select w-full text-sm">
                        <option value="">{{ __('partner.fulfillment.select_warehouse') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label-sm">{{ __('partner.fulfillment.quantity_to_send') }} <span class="text-red-500">*</span></label>
                    <input type="number" id="fbn-qty" class="form-input w-full text-sm" min="1" placeholder="{{ __('partner.fulfillment.quantity_placeholder') }}">
                </div>
                <div>
                    <label class="label-sm">{{ __('partner.fulfillment.notes_optional') }}</label>
                    <input type="text" id="fbn-notes" class="form-input w-full text-sm" placeholder="{{ __('partner.fulfillment.notes_placeholder') }}">
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-4">
                <button type="button" @click="showFbnForm=false" class="btn btn-ghost btn-sm">{{ __('partner.fulfillment.cancel') }}</button>
                <button type="button" @click="submitFbnRequest()" class="btn btn-primary btn-sm">{{ __('partner.fulfillment.submit_request') }}</button>
            </div>
        </div>

    </div>{{-- end FBN tab --}}

    {{-- ─────────────────────────────────────────────────────────────────────── --}}
    {{-- TAB: FBP                                                                --}}
    {{-- ─────────────────────────────────────────────────────────────────────── --}}
    <div x-show="tab==='fbp'" x-cloak class="space-y-5">

        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 flex gap-3">
            <span class="text-2xl">🏪</span>
            <div>
                <p class="font-semibold text-blue-800">{{ __('partner.fulfillment.fbp_explainer_title') }}</p>
                <p class="text-sm text-blue-600 mt-0.5">
                    {{ __('partner.fulfillment.fbp_explainer_desc') }}
                </p>
            </div>
        </div>

        <div class="flex justify-between items-center">
            <h2 class="text-base font-bold text-gray-700">{{ __('partner.fulfillment.fbp_inventory_title') }}</h2>
            <button type="button" @click="loadFbpInventory()" class="btn btn-ghost btn-xs">{{ __('partner.fulfillment.refresh') }}</button>
        </div>

        @if($fbpListings->isEmpty())
            <div class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center text-gray-400">
                <p class="text-2xl mb-2">🏪</p>
                <p class="font-medium">{{ __('partner.fulfillment.no_fbp_listings') }}</p>
            </div>
        @else
            <div id="fbp-inventory-container">
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.inventory_table.product') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.inventory_table.warehouse') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.inventory_table.on_hand') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.inventory_table.available') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.inventory_table.reserved') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.inventory_table.location') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.inventory_table.reorder_point') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fbpListings as $listing)
                            @foreach($listing->warehouseInventories as $inv)
                            <tr class="border-t border-gray-50 hover:bg-gray-50/50
                                {{ $inv->reorder_point && $inv->quantity_available <= $inv->reorder_point ? 'bg-red-50/30' : '' }}">
                                <td class="px-4 py-3 font-medium text-gray-800 text-right text-xs">
                                    {{ $listing->productVariant?->product?->name_ar ?? $listing->productVariant?->product?->name_en }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 text-right">{{ $inv->warehouse?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-center font-bold text-gray-900">{{ $inv->quantity_on_hand }}</td>
                                <td class="px-4 py-3 text-center font-bold text-green-600">{{ $inv->quantity_available }}</td>
                                <td class="px-4 py-3 text-center text-orange-500">{{ $inv->quantity_reserved }}</td>
                                <td class="px-4 py-3 text-xs text-gray-400 text-right">{{ $inv->bin_location ?? '—' }}</td>
                                <td class="px-4 py-3 text-center text-xs">
                                    @if($inv->reorder_point)
                                        <span class="{{ $inv->quantity_available <= $inv->reorder_point ? 'text-red-500 font-bold' : 'text-gray-400' }}">
                                            {{ $inv->reorder_point }}
                                            @if($inv->quantity_available <= $inv->reorder_point)
                                                ⚠️
                                            @endif
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>{{-- end FBP tab --}}

    {{-- ─────────────────────────────────────────────────────────────────────── --}}
    {{-- TAB: Marketplace                                                         --}}
    {{-- ─────────────────────────────────────────────────────────────────────── --}}
    <div x-show="tab==='marketplace'" x-cloak class="space-y-5">

        <div class="bg-green-50 border border-green-100 rounded-2xl p-4 flex gap-3">
            <span class="text-2xl">🌐</span>
            <div>
                <p class="font-semibold text-green-800">{{ __('partner.fulfillment.marketplace_explainer_title') }}</p>
                <p class="text-sm text-green-600 mt-0.5">
                    {{ __('partner.fulfillment.marketplace_explainer_desc') }}
                </p>
            </div>
        </div>

        @if($marketplaceListings->isEmpty())
            <div class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center text-gray-400">
                <p class="text-2xl mb-2">🌐</p>
                <p class="font-medium">{{ __('partner.fulfillment.no_marketplace_listings') }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($marketplaceListings as $listing)
                @php $rule = $listing->marketplaceShippingRule ?? null; @endphp
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <p class="font-semibold text-gray-800 text-sm mb-1">
                        {{ $listing->productVariant?->product?->name_ar ?? $listing->productVariant?->product?->name_en ?? __('common.product') }}
                    </p>
                    @if($rule)
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            @if($rule->requires_special_vehicle)
                                <span class="badge badge-warning text-xs">{{ __('partner.fulfillment.special_vehicle') }}</span>
                            @endif
                            @if($rule->requires_refrigeration)
                                <span class="badge badge-primary text-xs">{{ __('partner.fulfillment.refrigeration') }}</span>
                            @endif
                            @if($rule->max_weight_kg)
                                <span class="badge badge-secondary text-xs">{{ $rule->max_weight_kg }} kg</span>
                            @endif
                        </div>
                        <dl class="grid grid-cols-2 gap-1 text-xs">
                            <dt class="text-gray-400">{{ __('partner.fulfillment.commission_type') }}</dt>
                            <dd class="font-medium text-gray-700">
                                {{ __('partner.fulfillment.commission_types.' . $rule->commission_type->value) }}
                            </dd>
                            <dt class="text-gray-400">{{ __('partner.fulfillment.commission_value') }}</dt>
                            <dd class="font-semibold text-gray-900">{{ $rule->commissionLabel() }}</dd>
                            @if($rule->extra_delivery_fee > 0)
                                <dt class="text-gray-400">{{ __('partner.fulfillment.extra_delivery_fee') }}</dt>
                                <dd class="font-medium text-orange-600">{{ $rule->extraFeeFormatted() }}</dd>
                            @endif
                        </dl>
                        @if($rule->special_handling_notes)
                            <p class="text-xs text-gray-500 mt-2 bg-gray-50 rounded p-2">{{ $rule->special_handling_notes }}</p>
                        @endif
                    @else
                        <p class="text-xs text-gray-400 italic">{{ __('partner.fulfillment.no_custom_shipping_rules') }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        @endif

    </div>{{-- end Marketplace tab --}}

    {{-- ─────────────────────────────────────────────────────────────────────── --}}
    {{-- TAB: Storage Fees                                                       --}}
    {{-- ─────────────────────────────────────────────────────────────────────── --}}
    <div x-show="tab==='fees'" x-cloak>
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold text-gray-700 text-sm">{{ __('partner.fulfillment.storage_fees_title') }}</div>
            <div id="fees-container">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.storage_fees_table.month') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.storage_fees_table.units') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.storage_fees_table.amount') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('partner.fulfillment.storage_fees_table.status') }}</th>
                        </tr>
                    </thead>
                    <tbody id="fees-tbody">
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">{{ __('partner.fulfillment.storage_fees_hint') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>{{-- end Fees tab --}}

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
window.PARTNER_TRANSLATIONS = window.PARTNER_TRANSLATIONS || {};
Object.assign(window.PARTNER_TRANSLATIONS, {
    fulfillment: {
        noRequestsYet: @json(__('partner.fulfillment.no_requests_yet')),
        requestsTable: {
            requestNumber: @json(__('partner.fulfillment.requests_table.request_number')),
            product: @json(__('partner.fulfillment.requests_table.product')),
            warehouse: @json(__('partner.fulfillment.requests_table.warehouse')),
            quantity: @json(__('partner.fulfillment.requests_table.quantity')),
            status: @json(__('partner.fulfillment.requests_table.status')),
            tracking: @json(__('partner.fulfillment.requests_table.tracking')),
            expectedArrival: @json(__('partner.fulfillment.requests_table.expected_arrival')),
        },
        cancelRequest: @json(__('partner.fulfillment.cancel_request')),
        fillAllFields: @json(__('partner.fulfillment.fill_all_fields')),
        genericError: @json(__('partner.fulfillment.generic_error')),
        noStorageFees: @json(__('partner.fulfillment.no_storage_fees')),
        inventoryUpdated: @json(__('partner.fulfillment.inventory_updated')),
        cancelRequestConfirm: @json(__('partner.fulfillment.cancel_request_confirm')),
        trackingPlaceholder: @json(__('partner.fulfillment.tracking_placeholder')),
        saveTracking: @json(__('partner.fulfillment.save_tracking')),
    },
});

function fulfillmentApp() {
    return {
        tab: 'fbn',
        showFbnForm: false,
        feesLoaded: false,

        init() {
            this.loadFbnRequests();
        },

        async loadFbnRequests() {
            const res = await fetch('{{ route('partner.fulfillment.fbn.requests') }}', {
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });
            const data = await res.json();
            const container = document.getElementById('fbn-requests-container');
            if (!container) return;

            if (!data.data?.length) {
                container.innerHTML = `<p class="py-6 text-gray-400">${window.PARTNER_TRANSLATIONS.fulfillment.noRequestsYet}</p>`;
                return;
            }

            const t = window.PARTNER_TRANSLATIONS.fulfillment.requestsTable;
            let html = `<table class="w-full text-xs">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-right">${t.requestNumber}</th>
                        <th class="px-3 py-2 text-right">${t.product}</th>
                        <th class="px-3 py-2 text-right">${t.warehouse}</th>
                        <th class="px-3 py-2 text-center">${t.quantity}</th>
                        <th class="px-3 py-2 text-right">${t.status}</th>
                        <th class="px-3 py-2 text-right">${t.tracking}</th>
                        <th class="px-3 py-2 text-right">${t.expectedArrival}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead><tbody>`;

            data.data.forEach(r => {
                const cancelBtn = r.can_cancel
                    ? `<button class="btn btn-xs btn-danger" onclick="cancelFbnRequest('${r.id}')">${window.PARTNER_TRANSLATIONS.fulfillment.cancelRequest}</button>`
                    : '';
                html += `<tr class="border-t border-gray-50 hover:bg-gray-50/50">
                    <td class="px-3 py-2 font-mono text-right text-gray-700">${r.request_number}</td>
                    <td class="px-3 py-2 text-right text-gray-800">${r.product}</td>
                    <td class="px-3 py-2 text-right text-gray-500">${r.warehouse}</td>
                    <td class="px-3 py-2 text-center">${r.qty_requested} / <span class="text-green-600 font-bold">${r.qty_received}</span></td>
                    <td class="px-3 py-2 text-right"><span class="badge badge-${r.status_color} text-xs">${r.status_label}</span></td>
                    <td class="px-3 py-2 text-right text-gray-400">${renderTrackingCell(r)}</td>
                    <td class="px-3 py-2 text-right text-gray-500">${r.expected_arrival ?? '—'}</td>
                    <td class="px-3 py-2">${cancelBtn}</td>
                </tr>`;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        },

        async submitFbnRequest() {
            const listing_id   = document.getElementById('fbn-listing').value;
            const warehouse_id = document.getElementById('fbn-warehouse').value;
            const qty          = document.getElementById('fbn-qty').value;
            const notes        = document.getElementById('fbn-notes').value;

            if (!listing_id || !warehouse_id || !qty) {
                window.Toast.error(window.PARTNER_TRANSLATIONS.fulfillment.fillAllFields);
                return;
            }

            const res = await fetch('{{ route('partner.fulfillment.fbn.submit') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                body: JSON.stringify({ vendor_listing_id: listing_id, warehouse_id, quantity_requested: parseInt(qty), vendor_notes: notes }),
            });
            const data = await res.json();
            if (data.success) {
                window.Toast.success(data.message);
                this.showFbnForm = false;
                this.loadFbnRequests();
            } else {
                window.Toast.error(data.message ?? window.PARTNER_TRANSLATIONS.fulfillment.genericError);
            }
        },

        async loadFees() {
            if (this.feesLoaded) return;
            const res = await fetch('{{ route('partner.fulfillment.storage-fees') }}', {
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });
            const data = await res.json();
            const tbody = document.getElementById('fees-tbody');
            if (!data.data?.length) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">${window.PARTNER_TRANSLATIONS.fulfillment.noStorageFees}</td></tr>`;
            } else {
                tbody.innerHTML = data.data.map(f => `<tr class="border-t border-gray-50">
                    <td class="px-4 py-3 text-right font-medium text-gray-700">${f.month}</td>
                    <td class="px-4 py-3 text-center text-gray-800">${f.units.toLocaleString()}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">${f.total}</td>
                    <td class="px-4 py-3 text-right"><span class="badge badge-${f.status_color} text-xs">${f.status}</span></td>
                </tr>`).join('');
            }
            this.feesLoaded = true;
        },

        async loadFbpInventory() {
            const res = await fetch('{{ route('partner.fulfillment.fbp.inventory') }}', {
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });
            const data = await res.json();
            window.Toast.info(window.PARTNER_TRANSLATIONS.fulfillment.inventoryUpdated);
        },
    };
}

function renderTrackingCell(r) {
    if (r.tracking_number) {
        return r.tracking_number;
    }
    if (r.status === 'approved') {
        return `<div class="flex items-center justify-end gap-1">
            <input type="text" id="tracking-input-${r.id}" class="form-input text-xs py-1 w-28" placeholder="${window.PARTNER_TRANSLATIONS.fulfillment.trackingPlaceholder}">
            <button class="btn btn-xs btn-primary" onclick="saveTrackingNumber('${r.id}')">${window.PARTNER_TRANSLATIONS.fulfillment.saveTracking}</button>
        </div>`;
    }
    return '—';
}

async function saveTrackingNumber(id) {
    const input = document.getElementById(`tracking-input-${id}`);
    const trackingNumber = input?.value.trim();
    if (!trackingNumber) {
        window.Toast.error(window.PARTNER_TRANSLATIONS.fulfillment.fillAllFields);
        return;
    }

    const res = await fetch(`{{ url('partner/fulfillment/fbn') }}/${id}/tracking`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
        body: JSON.stringify({ tracking_number: trackingNumber }),
    });
    const data = await res.json();
    if (data.success) {
        window.Toast.success(data.message);
        location.reload();
    } else {
        window.Toast.error(data.message ?? window.PARTNER_TRANSLATIONS.fulfillment.genericError);
    }
}

async function cancelFbnRequest(id) {
    if (!confirm(window.PARTNER_TRANSLATIONS.fulfillment.cancelRequestConfirm)) return;
    const res = await fetch(`{{ url('partner/fulfillment/fbn') }}/${id}/cancel`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
        body: '{}',
    });
    const data = await res.json();
    if (data.success) { window.Toast.success(data.message); location.reload(); }
    else { window.Toast.error(data.message ?? window.PARTNER_TRANSLATIONS.fulfillment.genericError); }
}
</script>
@endpush
