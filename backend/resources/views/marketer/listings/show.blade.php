@extends('layouts.marketer')

@php
    $product = $listing->productVariant->product;
    $variant = $listing->productVariant;
    $category = $product->category;
    $primaryImg = $product->images->where('is_primary', true)->first()
        ?? $product->images->first();

    $statusMap = [
        \App\Enums\MarketerListingStatus::Active->value => ['bg-green-100 text-green-700', 'نشط'],
        \App\Enums\MarketerListingStatus::Paused->value => ['bg-gray-100 text-gray-600', 'موقوف مؤقتاً'],
        \App\Enums\MarketerListingStatus::PendingReview->value => ['bg-yellow-100 text-yellow-700', 'قيد المراجعة'],
        \App\Enums\MarketerListingStatus::Draft->value => ['bg-gray-100 text-gray-500', 'مسودة'],
        \App\Enums\MarketerListingStatus::Rejected->value => ['bg-red-100 text-red-700', 'مرفوض'],
        \App\Enums\MarketerListingStatus::OutOfStock->value => ['bg-red-50 text-red-500', 'نفد المخزون'],
        \App\Enums\MarketerListingStatus::Archived->value => ['bg-gray-100 text-gray-400', 'مؤرشف'],
    ];
    $statusVal = $listing->status instanceof \App\Enums\MarketerListingStatus ? $listing->status->value : $listing->status;
    [$statusClass, $statusLabel] = $statusMap[$statusVal] ?? ['bg-gray-100 text-gray-600', $statusVal];

    $conditionLabels = [
        'new' => 'جديد',
        'like_new' => 'كالجديد',
        'good' => 'جيد',
        'acceptable' => 'مقبول',
        'refurbished' => 'مُجدَّد',
    ];
@endphp

@section('title', $product->name_ar ?: $product->name_en)
@section('page-title', 'تفاصيل القائمة')

@section('content')

    {{-- Back link --}}
    <div class="mb-4">
        <a href="{{ route('marketer.listings.index') }}"
            class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            {{ __('marketer.static_text.marketer_listings_show.back_to_listings') }}
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- ── LEFT COLUMN ── --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Product Info Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-start gap-5">
                    <div class="w-20 h-20 rounded-xl border border-gray-100 bg-gray-50 overflow-hidden shrink-0 flex items-center justify-center">
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
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusClass }} shrink-0">
                                {{ $statusLabel }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-4 text-sm text-gray-600">
                            <div>
                                <span class="text-xs text-gray-400 block">{{ __('marketer.static_text.marketer_listings_show.variant') }}</span>
                                <span class="font-medium">{{ $variant->variant_name ?: 'النسخة الافتراضية' }}</span>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 block">SKU</span>
                                <span class="font-mono text-xs">{{ $variant->sku }}</span>
                            </div>
                            @if($listing->vendor_sku)
                                <div>
                                    <span class="text-xs text-gray-400 block">{{ __('marketer.static_text.marketer_listings_show.your_sku') }}</span>
                                    <span class="font-mono text-xs">{{ $listing->vendor_sku }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Listing Details --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ __('marketer.static_text.marketer_listings_show.listing_details') }}</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-5 text-sm">
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.price') }}</span>
                        <span class="font-bold text-gray-900 text-lg">{{ number_format($listing->price, 2) }}</span>
                        <span class="text-xs text-gray-500 mr-1">{{ $listing->currency }}</span>
                    </div>
                    @if($listing->compare_at_price)
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.compare_at_price') }}</span>
                            <span class="font-medium text-gray-500 line-through">{{ number_format($listing->compare_at_price, 2) }}</span>
                        </div>
                    @endif
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.status') }}</span>
                        <span class="font-medium">{{ $conditionLabels[$listing->condition] ?? $listing->condition }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.fulfillment_model') }}</span>
                        <span class="font-medium">{{ __('marketer.static_text.marketer_listings_show.fbn_stored_in_our_warehouses') }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.warehouse') }}</span>
                        <span class="font-medium">{{ $listing->warehouse?->name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.stock_alert_threshold') }}</span>
                        <span class="font-medium">{{ $listing->low_stock_threshold ?? 5 }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.country') }}</span>
                        <span class="font-medium">{{ $listing->country?->name_ar ?: $listing->country?->name_en }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.created_at') }}</span>
                        <span class="font-medium">{{ $listing->created_at?->format('Y-m-d') }}</span>
                    </div>
                </div>

                @if($listing->condition_notes)
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <span class="text-xs text-gray-400 block mb-1">{{ __('marketer.static_text.marketer_listings_show.condition_notes') }}</span>
                        <p class="text-sm text-gray-700">{{ $listing->condition_notes }}</p>
                    </div>
                @endif

                @if($listing->rejection_reason)
                    <div class="mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                        <p class="text-xs font-semibold text-red-700 mb-1">{{ __('marketer.static_text.marketer_listings_show.rejection_reason') }}</p>
                        <p class="text-sm text-red-800 mb-3">{{ $listing->rejection_reason }}</p>
                        <form method="POST" action="{{ route('marketer.listings.resubmit', $listing) }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl transition-colors">
                                {{ __('marketer.static_text.marketer_listings_show.resubmit_for_review') }}
                            </button>
                        </form>
                    </div>
                @endif

                @if(in_array($statusVal, [\App\Enums\MarketerListingStatus::Draft->value, \App\Enums\MarketerListingStatus::PendingReview->value]))
                    <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                        <p class="text-sm text-yellow-800">
                            <strong>{{ __('marketer.static_text.marketer_listings_show.note') }}</strong> قائمتك {{ $statusVal === \App\Enums\MarketerListingStatus::Draft->value ? 'مسودة' : 'قيد المراجعة' }}. ستظهر للعملاء بعد موافقة الإدارة.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Inventory --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0v10l-8 4m-8-4V7m8 4v10" />
                        </svg>
                        {{ __('marketer.static_text.marketer_listings_show.warehouse_stock') }}
                    </h3>
                    @if($warehouseInventory)
                        <button type="button"
                            class="btn-adjust-stock text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded-lg transition-colors"
                            data-inv-id="{{ $warehouseInventory->id }}"
                            data-warehouse="{{ $listing->warehouse?->name }}"
                            data-on-hand="{{ $warehouseInventory->quantity_on_hand }}">
                            {{ __('marketer.static_text.marketer_listings_show.adjust_stock') }}
                        </button>
                    @endif
                </div>

                @if(! $warehouseInventory)
                    <p class="text-sm text-gray-400 text-center py-6">{{ __('marketer.static_text.marketer_listings_show.no_stock_recorded_yet') }}</p>
                @else
                    @php
                        $available = $warehouseInventory->quantity_on_hand - $warehouseInventory->quantity_reserved;
                        $threshold = $listing->low_stock_threshold ?? 5;
                        $stockCls = $available <= 0
                            ? 'text-red-600 font-bold'
                            : ($available <= $threshold ? 'text-orange-500 font-semibold' : 'text-gray-900');
                    @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-5 text-sm">
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.available_to_sell') }}</span>
                            <span class="{{ $stockCls }} text-2xl font-bold" id="avail-{{ $warehouseInventory->id }}">{{ $available }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.in_warehouse') }}</span>
                            <span class="font-medium text-gray-800" id="onhand-{{ $warehouseInventory->id }}">{{ $warehouseInventory->quantity_on_hand }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.reserved') }}</span>
                            <span class="font-medium text-gray-500">{{ $warehouseInventory->quantity_reserved }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block mb-0.5">{{ __('marketer.static_text.marketer_listings_show.inbound') }}</span>
                            <span class="font-medium text-blue-500">{{ $warehouseInventory->quantity_inbound ?? 0 }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Stock History --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ __('marketer.static_text.marketer_listings_show.stock_history') }}</h3>

                @if($movements->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-4">{{ __('marketer.static_text.marketer_listings_show.no_stock_movements_yet') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-50">
                                    <th class="text-right py-2 font-medium">{{ __('marketer.static_text.marketer_listings_show.type') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('marketer.static_text.marketer_listings_show.quantity') }}</th>
                                    <th class="py-2 text-center font-medium">{{ __('marketer.static_text.marketer_listings_show.after') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('marketer.static_text.marketer_listings_show.reason') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('marketer.static_text.marketer_listings_show.date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($movements as $mov)
                                    @php
                                        $movType = $mov->movement_type instanceof \BackedEnum ? $mov->movement_type->value : $mov->movement_type;
                                        $movCls = match($movType) {
                                            'inbound' => 'text-green-600',
                                            'outbound' => 'text-red-600',
                                            'adjustment' => 'text-blue-600',
                                            default => 'text-gray-600',
                                        };
                                        $deltaSign = $mov->quantity_delta > 0 ? '+' : '';
                                    @endphp
                                    <tr>
                                        <td class="py-2">
                                            <span class="{{ $movCls }} font-medium">{{ $movType }}</span>
                                        </td>
                                        <td class="py-2 text-center {{ $mov->quantity_delta > 0 ? 'text-green-600' : 'text-red-600' }} font-semibold">
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
            @if($warehouseInventory)
                @php
                    $totalAvail = $warehouseInventory->quantity_on_hand - $warehouseInventory->quantity_reserved;
                @endphp
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h4 class="font-semibold text-gray-800 text-sm mb-3">{{ __('marketer.static_text.marketer_listings_show.stock_summary') }}</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">{{ __('marketer.static_text.marketer_listings_show.available_to_sell') }}</span>
                            <span @class(['font-bold', 'text-red-600' => $totalAvail <= 0, 'text-orange-500' => $totalAvail > 0 && $totalAvail <= ($listing->low_stock_threshold ?? 5), 'text-gray-900' => $totalAvail > ($listing->low_stock_threshold ?? 5)])>
                                {{ $totalAvail }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>{{ __('marketer.static_text.marketer_listings_show.in_warehouse') }}</span>
                            <span>{{ $warehouseInventory->quantity_on_hand }}</span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>{{ __('marketer.static_text.marketer_listings_show.reserved') }}</span>
                            <span>{{ $warehouseInventory->quantity_reserved }}</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Actions --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h4 class="font-semibold text-gray-800 text-sm mb-3">{{ __('marketer.static_text.marketer_listings_show.actions') }}</h4>
                <div class="space-y-2">

                    <a href="{{ route('marketer.listings.edit', $listing) }}"
                        class="block w-full text-center border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold py-2.5 rounded-xl transition-colors">
                        {{ __('marketer.static_text.marketer_listings_show.edit_listing') }}
                    </a>

                    @if($warehouseInventory)
                        <button type="button"
                            class="btn-adjust-stock w-full border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold py-2.5 rounded-xl transition-colors"
                            data-inv-id="{{ $warehouseInventory->id }}"
                            data-warehouse="{{ $listing->warehouse?->name }}"
                            data-on-hand="{{ $warehouseInventory->quantity_on_hand }}">
                            {{ __('marketer.static_text.marketer_listings_show.adjust_stock') }}
                        </button>
                    @endif

                    @if(in_array($statusVal, [\App\Enums\MarketerListingStatus::Active->value, \App\Enums\MarketerListingStatus::Paused->value]))
                        <form method="POST" action="{{ route('marketer.listings.toggle-status', $listing) }}">
                            @csrf
                            <button type="submit"
                                class="w-full {{ $statusVal === \App\Enums\MarketerListingStatus::Active->value ? 'border border-gray-200 hover:bg-gray-50 text-gray-700' : 'bg-green-600 hover:bg-green-700 text-white' }} text-sm font-semibold py-2.5 rounded-xl transition-colors">
                                {{ $statusVal === \App\Enums\MarketerListingStatus::Active->value ? 'إيقاف مؤقت' : 'تفعيل القائمة' }}
                            </button>
                        </form>
                    @endif

                    @if($statusVal === \App\Enums\MarketerListingStatus::Rejected->value)
                        <form method="POST" action="{{ route('marketer.listings.resubmit', $listing) }}">
                            @csrf
                            <button type="submit"
                                class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-semibold py-2.5 rounded-xl transition-colors">
                                {{ __('marketer.static_text.marketer_listings_show.resubmit_for_review') }}
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('marketer.listings.destroy', $listing) }}"
                          onsubmit="return confirm('هل أنت متأكد من حذف هذه القائمة؟');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="w-full border border-red-200 hover:bg-red-50 text-red-600 text-sm font-semibold py-2.5 rounded-xl transition-colors">
                            {{ __('marketer.static_text.marketer_listings_show.delete_listing') }}
                        </button>
                    </form>

                </div>
            </div>

        </div>

    </div>

    {{-- Adjust Stock Modal --}}
    <div id="adjust-stock-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900 text-sm">{{ __('marketer.static_text.marketer_listings_show.adjust_stock') }}</h3>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('marketer.static_text.marketer_listings_show.current_stock') }}</label>
                    <p id="adjust-current-qty" class="text-2xl font-bold text-gray-900"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('marketer.static_text.marketer_listings_show.adjustment') }} <span class="text-xs text-gray-400">(موجب للإضافة، سالب للخصم)</span></label>
                    <input type="number" name="adjustment" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40"
                        placeholder="{{ __('marketer.static_text.marketer_listings_show.e_g_10_or_5') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('marketer.static_text.marketer_listings_show.reason') }} <span class="text-red-500">*</span></label>
                    <select name="reason" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400/40">
                        <option value="">{{ __('marketer.static_text.marketer_listings_show.select_reason') }}</option>
                        <option value="received_stock">{{ __('marketer.static_text.marketer_listings_show.goods_received') }}</option>
                        <option value="damaged_goods">{{ __('marketer.static_text.marketer_listings_show.damaged_goods') }}</option>
                        <option value="inventory_count">{{ __('marketer.static_text.marketer_listings_show.stock_count') }}</option>
                        <option value="returned_to_vendor">{{ __('marketer.static_text.marketer_listings_show.return_to_seller') }}</option>
                        <option value="transfer">{{ __('marketer.static_text.marketer_listings_show.transfer') }}</option>
                        <option value="other">{{ __('marketer.static_text.marketer_listings_show.other') }}</option>
                    </select>
                </div>
                <div id="adjust-error" class="hidden text-sm text-red-600 bg-red-50 rounded-lg p-3"></div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-gray-900 hover:bg-gray-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                        {{ __('marketer.static_text.marketer_listings_show.confirm_adjustment') }}
                    </button>
                    <button type="button" id="adjust-cancel-btn"
                        class="flex-1 border border-gray-200 hover:bg-gray-50 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
                        {{ __('marketer.static_text.marketer_listings_show.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('adjust-stock-modal');
            const form = document.getElementById('adjust-form');
            const invIdInput = document.getElementById('adjust-inv-id');
            const warehouseNameEl = document.getElementById('adjust-warehouse-name');
            const currentQtyEl = document.getElementById('adjust-current-qty');
            const errorEl = document.getElementById('adjust-error');

            function openModal(invId, warehouseName, onHand) {
                invIdInput.value = invId;
                warehouseNameEl.textContent = warehouseName;
                currentQtyEl.textContent = onHand;
                form.querySelector('[name="adjustment"]').value = '';
                form.querySelector('[name="reason"]').value = '';
                errorEl.classList.add('hidden');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            document.querySelectorAll('.btn-adjust-stock').forEach(btn => {
                btn.addEventListener('click', () => {
                    openModal(btn.dataset.invId, btn.dataset.warehouse, btn.dataset.onHand);
                });
            });

            document.getElementById('adjust-modal-close').addEventListener('click', closeModal);
            document.getElementById('adjust-cancel-btn').addEventListener('click', closeModal);

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                errorEl.classList.add('hidden');

                const data = new FormData(form);
                const body = {
                    warehouse_inventory_id: data.get('warehouse_inventory_id'),
                    adjustment: parseInt(data.get('adjustment'), 10),
                    reason: data.get('reason'),
                    _token: '{{ csrf_token() }}',
                };

                try {
                    const res = await fetch('{{ route('marketer.listings.adjust-stock', $listing) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(body),
                    });
                    const json = await res.json();
                    if (json.success) {
                        closeModal();
                        window.location.reload();
                    } else {
                        errorEl.textContent = json.message;
                        errorEl.classList.remove('hidden');
                    }
                } catch {
                    errorEl.textContent = 'حدث خطأ. يرجى المحاولة مرة أخرى.';
                    errorEl.classList.remove('hidden');
                }
            });
        })();
    </script>

@endsection
