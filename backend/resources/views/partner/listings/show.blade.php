@extends('layouts.partner')

@php
    $product = $listing->productVariant->product;
    $variant = $listing->productVariant;
    $category = $product->category;
    $primaryImg = $product->images->where('is_primary', true)->first()
        ?? $product->images->first();

    $statusMap = [
        \App\Enums\VendorListingStatus::Active->value => ['bg-green-100 text-green-700', __('partner.listings.show.statuses.active')],
        \App\Enums\VendorListingStatus::Paused->value => ['bg-gray-100 text-gray-600', __('partner.listings.show.statuses.paused')],
        \App\Enums\VendorListingStatus::PendingReview->value => ['bg-yellow-100 text-yellow-700', __('partner.listings.show.statuses.pending_review')],
        \App\Enums\VendorListingStatus::Draft->value => ['bg-gray-100 text-gray-500', __('partner.listings.show.statuses.draft')],
        \App\Enums\VendorListingStatus::Rejected->value => ['bg-red-100 text-red-700', __('partner.listings.show.statuses.rejected')],
        \App\Enums\VendorListingStatus::OutOfStock->value => ['bg-red-50 text-red-500', __('partner.listings.show.statuses.out_of_stock')],
        \App\Enums\VendorListingStatus::Archived->value => ['bg-gray-100 text-gray-400', __('partner.listings.show.statuses.archived')],
    ];
    [$statusClass, $statusLabel] = $statusMap[$listing->status->value] ?? ['bg-gray-100 text-gray-600', $listing->status->value];

    $fulfillmentLabels = [
        'fbm' => __('partner.listings.show.fulfillment_labels.fbm'),
        'fbn' => __('partner.listings.show.fulfillment_labels.fbn'),
        'cross_dock' => __('partner.listings.show.fulfillment_labels.cross_dock'),
    ];
    $conditionLabels = [
        'new' => __('partner.listings.show.condition_labels.new'),
        'like_new' => __('partner.listings.show.condition_labels.like_new'),
        'good' => __('partner.listings.show.condition_labels.good'),
        'acceptable' => __('partner.listings.show.condition_labels.acceptable'),
        'refurbished' => __('partner.listings.show.condition_labels.refurbished'),
    ];
    $movementTypeLabels = [
        \App\Enums\InventoryMovementType::Inbound->value => ['text-green-600', __('partner.listings.show.movement_types.inbound')],
        \App\Enums\InventoryMovementType::Outbound->value => ['text-red-600', __('partner.listings.show.movement_types.outbound')],
        \App\Enums\InventoryMovementType::Adjustment->value => ['text-blue-600', __('partner.listings.show.movement_types.adjustment')],
        \App\Enums\InventoryMovementType::Reservation->value => ['text-purple-600', __('partner.listings.show.movement_types.reservation')],
        \App\Enums\InventoryMovementType::Release->value => ['text-teal-600', __('partner.listings.show.movement_types.reservation_release')],
        \App\Enums\InventoryMovementType::Damage->value => ['text-orange-600', __('partner.listings.show.movement_types.damage')],
        \App\Enums\InventoryMovementType::ReturnMovement->value => ['text-yellow-600', __('partner.listings.show.movement_types.return')],
        \App\Enums\InventoryMovementType::Transfer->value => ['text-indigo-600', __('partner.listings.show.movement_types.transfer')],
    ];
@endphp

@section('title', $product->name_ar ?: $product->name_en)
@section('page-title', __('partner.listings.show.page_title'))

@push('scripts')
    @vite('resources/js/partner/listings.js')
    <script>
        window.LISTING_DETAIL = {
            listingId: '{{ $listing->id }}',
            currentStatus: '{{ $listing->status?->value }}',
            currentPrice: '{{ $listing->price }}',
            updatePriceUrl: '{{ route('partner.listings.update-price', $listing->id) }}',
            updateShippingUrl: '{{ route('partner.listings.update-shipping', $listing->id) }}',
            currentShippingMethodId: '{{ $listing->primary_shipping_method_id }}',
            toggleStatusUrl: '{{ route('partner.listings.toggle-status', $listing->id) }}',
            adjustStockUrl: '{{ route('partner.listings.adjust-stock', $listing->id) }}',
            toggleCoversDeliveryUrl: '{{ route('partner.listings.toggle-covers-delivery', $listing->id) }}',
            updateDimensionsUrl: '{{ route('partner.listings.update-dimensions', $listing->id) }}',
            shippingPreviewUrl: '{{ route('partner.listings.shipping-preview', $listing->id) }}',
            clearCacheUrl: '{{ route('partner.listings.clear-cache', $listing->id) }}',
            csrf: '{{ csrf_token() }}',
        };
    </script>
@endpush

@section('content')

    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('partner.listings.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            {{ __('partner.listings.show.back_to_listings') }}
        </a>
    </div>

    <div x-data="{ tab: '{{ request('tab', 'details') }}' }">

    {{-- Tab nav --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-x-6 overflow-x-auto">
            @foreach ([
                'details' => __('partner.listings.show.tab_details'),
                'marketer_campaign' => __('partner.listings.show.tab_marketer_campaign'),
            ] as $tabKey => $tabLabel)
                <button type="button"
                    @click="tab = '{{ $tabKey }}'"
                    :class="tab === '{{ $tabKey }}' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>
    </div>

    <div x-show="tab === 'details'" x-cloak>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- ── LEFT COLUMN ── --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Product Info Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-start gap-5">
                    <div
                        class="w-20 h-20 rounded-xl border border-gray-100 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
                        @if($primaryImg)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($primaryImg->disk)->url($primaryImg->path) }}"
                                alt="{{ $product->name_en }}" class="w-full h-full object-cover">
                        @else
                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10" />
                            </svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-bold text-gray-900 text-lg leading-snug">
                                    {{ $product->name_ar ?: $product->name_en }}
                                </h2>
                                @if($product->name_ar && $product->name_en)
                                    <p class="text-sm text-gray-500">{{ $product->name_en }}</p>
                                @endif
                                @if($category)
                                    <p class="text-xs text-gray-400 mt-1">{{ $category->name_ar ?? $category->name_en }}</p>
                                @endif
                            </div>
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusClass }} shrink-0">
                                {{ $statusLabel }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-4 text-sm text-gray-600">
                            <div>
                                <span class="text-xs text-gray-400 block">{{ __('partner.listings.show.variant') }}</span>
                                <span class="font-medium">{{ $variant->variant_name ?: __('partner.inventory.default_variant') }}</span>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 block">SKU</span>
                                <span class="font-mono text-xs">{{ $variant->sku }}</span>
                            </div>
                            @if($listing->vendor_sku)
                                <div>
                                    <span class="text-xs text-gray-400 block">{{ __('partner.listings.show.your_sku') }}</span>
                                    <span class="font-mono text-xs">{{ $listing->vendor_sku }}</span>
                                </div>
                            @endif
                        </div>

                        @php $customerUrl = "/products/{$variant->id}/{$listing->id}"; @endphp
                        <div class="mt-3 bg-gray-50 border border-gray-200 rounded-lg p-3">
                            <span class="text-xs text-gray-400 block mb-1">{{ __('partner.listings.customer_url') ?? 'Customer URL' }}</span>
                            <p class="text-xs text-gray-400 mb-1">{{ __('partner.listings.customer_url_hint') ?? 'This is the URL customers will see for your listing.' }}</p>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ $customerUrl }}"
                                    class="flex-1 border border-gray-200 bg-white rounded-lg px-3 py-1.5 text-xs font-mono text-gray-600 focus:outline-none">
                                <button type="button" class="js-copy px-3 py-1.5 text-xs font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100"
                                        data-value="{{ $customerUrl }}">
                                    {{ __('partner.listings.copy_url') ?? 'Copy URL' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Listing Details --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ __('partner.listings.show.details_title') }}</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-5 text-sm">
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.price') }}</span>
                        <span id="display-price" class="font-bold text-gray-900 text-lg">
                            {{ number_format($listing->price, 2) }}
                        </span>
                        <span class="text-xs text-gray-500 mr-1">{{ $listing->currency }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.condition') }}</span>
                        <span class="font-medium">{{ $conditionLabels[$listing->condition] ?? $listing->condition }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.fulfillment_model') }}</span>
                        <span
                            class="font-medium">{{ $fulfillmentLabels[$listing->fulfillment_model] ?? $listing->fulfillment_model }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.total_sold') }}</span>
                        <span class="font-medium">{{ number_format($listing->total_sold) }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.avg_rating') }}</span>
                        <span
                            class="font-medium">{{ $listing->rating_avg ? number_format($listing->rating_avg, 1) . ' ★' : '—' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.alert_threshold') }}</span>
                        <span class="font-medium">{{ $listing->low_stock_threshold }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.shipping_method') }}</span>
                        @if($listing->primaryShippingMethod)
                            <span id="display-shipping-method"
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                style="background-color: {{ $listing->primaryShippingMethod->badge_color_hex ?? '#eef2ff' }}22; color: {{ $listing->primaryShippingMethod->badge_color_hex ?? '#4f46e5' }};">
                                {{ $listing->primaryShippingMethod->badge_label_en ?? $listing->primaryShippingMethod->name }}
                            </span>
                        @else
                            <span id="display-shipping-method" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                {{ __('partner.listings.show.category_default') ?? 'Category default' }}{{ $defaultShippingMethod ? ' — ' . $defaultShippingMethod->name : '' }}
                            </span>
                        @endif
                    </div>
                </div>

                @if($listing->global_system_type === \App\Enums\GlobalSystemType::MerchantFbp)
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" id="vendor-covers-delivery-toggle" @checked($listing->vendor_covers_delivery)
                                class="mt-1 rounded border-gray-300 text-yellow-500 focus:ring-yellow-400/40">
                            <span class="text-sm text-gray-700">
                                {{ __('partner.listings.show.cover_delivery_label') }}
                            </span>
                        </label>
                    </div>
                @endif

                @if($listing->vendor_notes)
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <span class="text-xs text-gray-400 block mb-1">{{ __('partner.listings.show.your_notes') }}</span>
                        <p class="text-sm text-gray-700">{{ $listing->vendor_notes }}</p>
                    </div>
                @endif

                @if($listing->rejection_reason)
                    <div class="mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                        <p class="text-xs font-semibold text-red-700 mb-1">{{ __('partner.listings.show.rejection_reason') }}</p>
                        <p class="text-sm text-red-800">{{ $listing->rejection_reason }}</p>
                    </div>
                @endif
            </div>

            {{-- Shipping & Dimensions --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800">{{ __('partner.listings.show.shipping_and_dimensions') }}</h3>
                    <button id="btn-update-dimensions"
                        class="text-xs text-blue-600 hover:underline">{{ __('partner.listings.show.edit') }}</button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-5 text-sm">
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.weight_label') }}</span>
                        <span id="display-declared-weight" class="font-medium">{{ $listing->declared_weight_grams ?? '—' }} g</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.dimensions_cm_label') }}</span>
                        <span id="display-declared-dimensions" class="font-medium">
                            {{ $listing->declared_length_cm ?? '—' }} × {{ $listing->declared_width_cm ?? '—' }} × {{ $listing->declared_height_cm ?? '—' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.weight_class_label') }}</span>
                        <span id="display-weight-class" class="font-medium">{{ ['light' => __('partner.listings.show.weight_classes.light'), 'medium' => __('partner.listings.show.weight_classes.medium'), 'heavy' => __('partner.listings.show.weight_classes.heavy')][$listing->weight_class] ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('partner.listings.show.handling_class_label') }}</span>
                        <span id="display-handling-class" class="font-medium">
                            {{ ['standard' => __('partner.listings.show.handling_classes.standard'), 'refrigerated' => __('partner.listings.show.handling_classes.refrigerated'), 'fragile' => __('partner.listings.show.handling_classes.fragile'), 'special_tech' => __('partner.listings.show.handling_classes.special_tech')][$listing->handling_class] ?? '—' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Inventory per Warehouse --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10" />
                    </svg>
                    {{ __('partner.listings.show.inventory_by_warehouse') }}
                </h3>

                @if($listing->warehouseInventories->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-6">{{ __('partner.listings.show.no_inventory') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 border-b border-gray-100">
                                    <th class="text-right py-2 font-medium">{{ __('partner.listings.show.warehouse') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('partner.listings.show.available_for_sale') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('partner.listings.show.on_hand') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('partner.listings.show.reserved') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('partner.listings.show.inbound') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('partner.listings.show.damaged') }}</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($listing->warehouseInventories as $inv)
                                    @php
                                        $available = $inv->quantity_on_hand - $inv->quantity_reserved;
                                        $stockCls = $available <= 0 ? 'text-red-600 font-bold'
                                            : ($available <= $listing->low_stock_threshold ? 'text-orange-500 font-semibold' : 'text-gray-900');
                                    @endphp
                                    <tr>
                                        <td class="py-3">
                                            <p class="font-medium text-gray-800">{{ $inv->warehouse->name }}</p>
                                            @if($inv->bin_location)
                                                <p class="text-xs text-gray-400 font-mono">{{ $inv->bin_location }}</p>
                                            @endif
                                        </td>
                                        <td class="py-3 text-center">
                                            <span class="{{ $stockCls }} text-base font-bold"
                                                id="avail-{{ $inv->id }}">{{ $available }}</span>
                                        </td>
                                        <td class="py-3 text-center text-gray-700" id="onhand-{{ $inv->id }}">
                                            {{ $inv->quantity_on_hand }}</td>
                                        <td class="py-3 text-center text-gray-500">{{ $inv->quantity_reserved }}</td>
                                        <td class="py-3 text-center text-blue-500">{{ $inv->quantity_inbound ?? 0 }}</td>
                                        <td class="py-3 text-center text-red-400">{{ $inv->quantity_damaged ?? 0 }}</td>
                                        <td class="py-3 text-right">
                                            <button type="button"
                                                class="btn-adjust-stock text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded-lg transition-colors"
                                                data-inv-id="{{ $inv->id }}" data-warehouse="{{ $inv->warehouse->name }}"
                                                data-on-hand="{{ $inv->quantity_on_hand }}">
                                                {{ __('partner.listings.show.adjust_stock') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Stock History --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800">{{ __('partner.listings.show.stock_history') }}</h3>
                    <a href="{{ route('partner.inventory.movements', $listing->id) }}"
                        class="text-xs text-blue-600 hover:underline">{{ __('partner.listings.show.view_all') }}</a>
                </div>

                @if($movements->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-4">{{ __('partner.listings.show.no_movements') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-50">
                                    <th class="text-right py-2 font-medium">{{ __('partner.listings.show.movement_type') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('partner.listings.show.quantity') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('partner.listings.show.after') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('partner.listings.show.reason') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('partner.listings.show.date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($movements as $mov)
                                    @php
                                        [$movCls, $movLabel] = $movementTypeLabels[$mov->movement_type->value]
                                            ?? ['text-gray-600', $mov->movement_type->value];
                                        $deltaSign = $mov->quantity_delta > 0 ? '+' : '';
                                    @endphp
                                    <tr>
                                        <td class="py-2">
                                            <span class="{{ $movCls }} font-medium">{{ $movLabel }}</span>
                                        </td>
                                        <td
                                            class="py-2 text-center {{ $mov->quantity_delta > 0 ? 'text-green-600' : 'text-red-600' }} font-semibold">
                                            {{ $deltaSign }}{{ $mov->quantity_delta }}
                                        </td>
                                        <td class="py-2 text-center text-gray-600">{{ $mov->quantity_after }}</td>
                                        <td class="py-2 text-gray-600">{{ $mov->reason ?? '—' }}</td>
                                        <td class="py-2 text-gray-400">{{ $mov->created_at?->format('M d, H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>

        {{-- ── RIGHT SIDEBAR ── --}}
        <div class="lg:col-span-4 space-y-4">

            {{-- Quick Stats --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h4 class="font-semibold text-gray-800 text-sm mb-3">{{ __('partner.listings.show.total_inventory') }}</h4>
                @php
                    $totalOnHand = $listing->warehouseInventories->sum('quantity_on_hand');
                    $totalReserved = $listing->warehouseInventories->sum('quantity_reserved');
                    $totalAvail = $totalOnHand - $totalReserved;
                    $totalInbound = $listing->warehouseInventories->sum('quantity_inbound');
                @endphp
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">{{ __('partner.listings.show.available_for_sale') }}</span>
                        <span @class(['font-bold', 'text-red-600' => $totalAvail <= 0, 'text-orange-500' => $totalAvail > 0 && $totalAvail <= $listing->low_stock_threshold, 'text-gray-900' => $totalAvail > $listing->low_stock_threshold])>
                            {{ $totalAvail }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>{{ __('partner.listings.show.on_hand') }}</span>
                        <span>{{ $totalOnHand }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>{{ __('partner.listings.show.reserved') }}</span>
                        <span>{{ $totalReserved }}</span>
                    </div>
                    @if($totalInbound > 0)
                        <div class="flex justify-between text-sm text-blue-600">
                            <span>{{ __('partner.listings.show.incoming_soon') }}</span>
                            <span>{{ $totalInbound }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Actions --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h4 class="font-semibold text-gray-800 text-sm mb-3">{{ __('partner.listings.show.actions') }}</h4>
                <div class="space-y-2">

                    {{-- Edit Listing --}}
                    <a href="{{ route('partner.listings.edit', $listing->id) }}"
                        class="block w-full text-center border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold py-2.5 rounded-xl transition-colors">
                        تعديل القائمة
                    </a>

                    {{-- Update Price --}}
                    <button id="btn-update-price"
                        class="w-full bg-yellow-400 hover:bg-yellow-300 text-gray-900 text-sm font-semibold py-2.5 rounded-xl transition-colors">
                        {{ __('partner.listings.show.update_price') }}
                    </button>

                    {{-- Update Shipping Method --}}
                    <button id="btn-update-shipping"
                        class="w-full border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold py-2.5 rounded-xl transition-colors">
                        {{ $listing->primary_shipping_method_id ? __('partner.listings.show.update_shipping') : __('partner.listings.show.set_shipping') }}
                    </button>

                    {{-- Toggle Status --}}
                    @if(in_array($listing->status, [\App\Enums\VendorListingStatus::Active, \App\Enums\VendorListingStatus::Paused]))
                        <button id="btn-toggle-status"
                            class="w-full {{ $listing->status === \App\Enums\VendorListingStatus::Active ? 'border border-gray-200 hover:bg-gray-50 text-gray-700' : 'bg-green-600 hover:bg-green-700 text-white' }} text-sm font-semibold py-2.5 rounded-xl transition-colors">
                            {{ $listing->status === \App\Enums\VendorListingStatus::Active ? __('partner.listings.show.deactivate') : __('partner.listings.show.activate_listing') }}
                        </button>
                    @endif

                    {{-- Inventory movements link --}}
                    <a href="{{ route('partner.inventory.movements', $listing->id) }}"
                        class="w-full block text-center border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold py-2.5 rounded-xl transition-colors">
                        {{ __('partner.listings.show.movements_history') }}
                    </a>

                    {{-- Clear Cache --}}
                    <button type="button"
                        onclick="
                            fetch(window.LISTING_DETAIL.clearCacheUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': window.LISTING_DETAIL.csrf,
                                    'Accept': 'application/json'
                                }
                            })
                            .then(r => r.json())
                            .then(d => {
                                const msg = document.createElement('div');
                                msg.className = 'fixed top-4 right-4 z-50 bg-green-600 text-white px-4 py-2 rounded-lg text-sm shadow-lg';
                                msg.textContent = '✅ ' + d.message;
                                document.body.appendChild(msg);
                                setTimeout(() => msg.remove(), 4000);
                            })
                            .catch(() => alert('Failed to clear cache.'));
                        "
                        class="w-full inline-flex items-center justify-center gap-1.5 border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold py-2.5 rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh Live Data
                    </button>

                </div>
            </div>

        </div>

    </div>
    </div>

    {{-- Marketer Campaign tab --}}
    <div x-show="tab === 'marketer_campaign'" x-cloak>
        @if($marketerCampaign)
            @php
                $mcStatusMap = [
                    'pending_admin' => ['bg-yellow-100 text-yellow-700', __('partner.listings.show.marketer_campaign.statuses.pending_admin')],
                    'active' => ['bg-green-100 text-green-700', __('partner.listings.show.marketer_campaign.statuses.active')],
                    'rejected' => ['bg-red-100 text-red-700', __('partner.listings.show.marketer_campaign.statuses.rejected')],
                    'cancelled' => ['bg-gray-100 text-gray-500', __('partner.listings.show.marketer_campaign.statuses.cancelled')],
                    'completed' => ['bg-blue-100 text-blue-700', __('partner.listings.show.marketer_campaign.statuses.completed')],
                ];
                [$mcStatusClass, $mcStatusLabel] = $mcStatusMap[$marketerCampaign->status] ?? ['bg-gray-100 text-gray-600', $marketerCampaign->status];
            @endphp

            <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800">{{ $marketerCampaign->title ?: __('partner.listings.show.marketer_campaign.title') }}</h3>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $mcStatusClass }}">{{ $mcStatusLabel }}</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm mb-4">
                    <div>
                        <p class="text-gray-400 text-xs mb-1">{{ __('partner.listings.show.marketer_campaign.invited_marketers') }}</p>
                        <p class="font-semibold text-gray-900">{{ $marketerCampaign->invitations->count() }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-xs mb-1">{{ __('partner.listings.show.marketer_campaign.total_conversions') }}</p>
                        <p class="font-semibold text-gray-900">{{ $marketerCampaign->conversions->count() }}</p>
                    </div>
                </div>

                <a href="{{ route('partner.marketer-campaigns.show', $marketerCampaign) }}"
                    class="text-sm text-blue-600 hover:underline">
                    {{ __('partner.listings.show.marketer_campaign.view_details') }}
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ __('partner.listings.show.marketer_campaign.invited_marketers') }}</h3>

                @if($marketerCampaign->invitations->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-6">{{ __('partner.listings.show.marketer_campaign.no_invitations') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 border-b border-gray-100">
                                    <th class="text-right py-2 font-medium">{{ __('partner.listings.show.marketer_campaign.marketer') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('partner.listings.show.marketer_campaign.status') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('partner.listings.show.marketer_campaign.referral_link') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($marketerCampaign->invitations as $invitation)
                                    <tr>
                                        <td class="py-2">{{ $invitation->marketer?->display_name ?: $invitation->marketer?->name ?? '—' }}</td>
                                        <td class="py-2">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                {{ $invitation->status }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-gray-400">{{ $invitation->referral_link ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center text-sm text-gray-400">
                {{ __('partner.listings.show.marketer_campaign.no_campaign') }}
            </div>
        @endif
    </div>

    </div>

    {{-- Adjust Stock Modal --}}
    <div id="adjust-stock-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900 text-sm">{{ __('partner.listings.show.adjust_modal_title') }}</h3>
                    <p id="adjust-warehouse-name" class="text-xs text-gray-400"></p>
                </div>
                <button id="adjust-modal-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="adjust-form" class="p-5 space-y-4">
                <input type="hidden" id="adjust-inv-id" name="warehouse_inventory_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.listings.show.current_stock') }}</label>
                    <p id="adjust-current-qty" class="text-2xl font-bold text-gray-900"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.inventory.adjustment_label') }} <span
                            class="text-xs text-gray-400">{{ __('partner.listings.show.adjustment_hint') }}</span></label>
                    <input type="number" name="adjustment" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                        placeholder="{{ __('partner.inventory.adjustment_placeholder') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.inventory.reason_label') }} <span
                            class="text-red-500">*</span></label>
                    <select name="reason" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        <option value="">{{ __('partner.inventory.select_reason') }}</option>
                        <option value="received_stock">{{ __('partner.inventory.reasons.received_stock') }}</option>
                        <option value="damaged_goods">{{ __('partner.inventory.reasons.damaged_goods') }}</option>
                        <option value="inventory_count">{{ __('partner.inventory.reasons.inventory_count') }}</option>
                        <option value="returned_to_vendor">{{ __('partner.inventory.reasons.returned_to_vendor') }}</option>
                        <option value="transfer">{{ __('partner.inventory.reasons.transfer') }}</option>
                        <option value="other">{{ __('partner.inventory.reasons.other') }}</option>
                    </select>
                </div>
                <div id="adjust-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-gray-900 hover:bg-gray-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                        {{ __('partner.listings.show.confirm_adjustment') }}
                    </button>
                    <button type="button" id="adjust-cancel-btn"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
                        {{ __('partner.listings.show.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Update Price Modal --}}
    <div id="update-price-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 text-sm">{{ __('partner.listings.show.update_price_title') }}</h3>
                <button id="price-modal-close-detail" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="price-update-form" class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.show.new_price') }}</label>
                    <input type="number" id="new-price-input" name="price" step="1" min="1"
                        value="{{ $listing->price }}"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                </div>
                <div id="price-update-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('partner.listings.show.save') }}</button>
                    <button type="button" id="price-close-btn"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('partner.listings.show.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Update Shipping Method Modal --}}
    <div id="update-shipping-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 text-sm">{{ __('partner.listings.show.update_shipping_title') }}</h3>
                <button id="shipping-modal-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="shipping-update-form" class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.show.shipping_method') }}</label>
                    <select id="shipping-method-select" name="primary_shipping_method_id"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        @forelse($availableShippingMethods as $method)
                            <option value="{{ $method->id }}" @selected($listing->primary_shipping_method_id === $method->id)>{{ $method->name }}</option>
                        @empty
                            <option value="">{{ __('partner.listings.show.no_shipping_methods') }}</option>
                        @endforelse
                    </select>
                </div>
                <div id="shipping-update-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-2">
                    <button type="submit" @disabled($availableShippingMethods->isEmpty())
                        class="flex-1 bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold py-2.5 rounded-xl text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">{{ __('partner.listings.show.save') }}</button>
                    <button type="button" id="shipping-close-btn"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('partner.listings.show.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Update Shipping & Dimensions Modal --}}
    <div id="update-dimensions-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 text-sm">{{ __('partner.listings.show.dimensions_modal.title') }}</h3>
                <button id="dimensions-modal-close" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="dimensions-update-form" class="p-5 space-y-4"
                x-data="{
                    l: {{ (float) ($listing->declared_length_cm ?? 0) }},
                    w: {{ (float) ($listing->declared_width_cm ?? 0) }},
                    h: {{ (float) ($listing->declared_height_cm ?? 0) }},
                    actual: {{ (int) ($listing->declared_weight_grams ?? 0) }},
                    get volumetric() { return (this.l && this.w && this.h) ? Math.ceil((this.l * this.w * this.h) / 5) : 0; },
                    get billable() { return Math.max(this.actual, this.volumetric); },
                    get weightClass() {
                        if (this.billable <= 1000) return '{{ __('partner.listings.show.weight_classes.light') }}';
                        if (this.billable <= 5000) return '{{ __('partner.listings.show.weight_classes.medium') }}';
                        return '{{ __('partner.listings.show.weight_classes.heavy') }}';
                    }
                }">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.show.dimensions_modal.product_weight_grams') }}</label>
                    <input type="number" name="declared_weight_grams" min="1" step="1" required x-model.number="actual"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.show.dimensions_modal.packaged_dimensions_cm') }}</label>
                    <div class="grid grid-cols-3 gap-3">
                        <input type="number" name="declared_length_cm" min="0.1" step="0.1" x-model.number="l" placeholder="L"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        <input type="number" name="declared_width_cm" min="0.1" step="0.1" x-model.number="w" placeholder="W"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        <input type="number" name="declared_height_cm" min="0.1" step="0.1" x-model.number="h" placeholder="H"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">{{ __('partner.listings.show.dimensions_modal.volumetric_formula_hint') }}</p>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('partner.listings.show.dimensions_modal.volumetric') }}</span><span x-text="volumetric + ' g'"></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('partner.listings.show.dimensions_modal.actual') }}</span><span x-text="actual + ' g'"></span></div>
                    <div class="flex justify-between font-bold"><span class="text-gray-500 font-normal">{{ __('partner.listings.show.dimensions_modal.billable') }}</span><span x-text="billable + ' g'"></span></div>
                    <div class="flex justify-between pt-1.5 border-t border-gray-100"><span class="text-gray-500">{{ __('partner.listings.show.dimensions_modal.class') }}</span><span class="font-semibold text-yellow-600" x-text="weightClass"></span></div>
                </div>

                <a href="{{ route('partner.tools.weight-calculator') }}" target="_blank"
                   class="text-sm text-blue-600 hover:underline">
                    {{ __('partner.listings.show.dimensions_modal.open_weight_calculator') }}
                </a>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('partner.listings.show.dimensions_modal.handling_class') }}</label>
                    <select name="handling_class"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        <option value="standard" @selected($listing->handling_class === 'standard')>{{ __('partner.listings.show.dimensions_modal.handling_options.standard') }}</option>
                        <option value="refrigerated" @selected($listing->handling_class === 'refrigerated')>{{ __('partner.listings.show.dimensions_modal.handling_options.refrigerated') }}</option>
                        <option value="fragile" @selected($listing->handling_class === 'fragile')>{{ __('partner.listings.show.dimensions_modal.handling_options.fragile') }}</option>
                        <option value="special_tech" @selected($listing->handling_class === 'special_tech')>{{ __('partner.listings.show.dimensions_modal.handling_options.special_tech') }}</option>
                    </select>
                </div>

                <div id="dimensions-update-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('partner.listings.show.save') }}</button>
                    <button type="button" id="dimensions-close-btn"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">{{ __('partner.listings.show.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection