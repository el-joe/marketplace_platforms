@extends('layouts.partner')

@php
    $product = $listing->productVariant->product;
    $variant = $listing->productVariant;

    $movementTypeLabels = [
        'inbound' => ['text-green-600', __('partner.inventory.movement_types.inbound')],
        'outbound' => ['text-red-600', __('partner.inventory.movement_types.outbound')],
        'adjustment' => ['text-blue-600', __('partner.inventory.movement_types.adjustment')],
        'reservation' => ['text-purple-600', __('partner.inventory.movement_types.reservation')],
        'reservation_release' => ['text-teal-600', __('partner.inventory.movement_types.reservation_release')],
    ];
@endphp

@section('title', __('partner.inventory.movements_title'))
@section('page-title', __('partner.inventory.movements_title'))

@section('content')

    {{-- Product Header --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-gray-900">{{ $product->name_ar ?: $product->name_en }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{{ $variant->variant_name ?: __('partner.inventory.default_variant') }} · <span
                    class="font-mono text-xs">{{ $variant->sku }}</span></p>
        </div>
        <a href="{{ route('partner.listings.show', $listing->id) }}"
            class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-200 rounded-xl px-4 py-2 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            {{ __('partner.inventory.listing_details') }}
        </a>
    </div>

    {{-- Per-Warehouse summary --}}
    @if($listing->warehouseInventories->isNotEmpty())
        <div
            class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-{{ min($listing->warehouseInventories->count(), 4) }} gap-3 mb-6">
            @foreach($listing->warehouseInventories as $inv)
                @php $avail = $inv->quantity_on_hand - $inv->quantity_reserved; @endphp
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 mb-1 truncate">{{ $inv->warehouse->name }}</p>
                    <p @class(['text-2xl font-bold', 'text-red-600' => $avail <= 0, 'text-orange-500' => $avail > 0 && $avail <= $listing->low_stock_threshold, 'text-gray-900' => $avail > $listing->low_stock_threshold])>
                        {{ $avail }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('partner.inventory.table.available') }} · {{ __('partner.inventory.table.on_hand') }}: {{ $inv->quantity_on_hand }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Movements table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">{{ __('partner.inventory.movements_history') }}</h3>
            <span class="text-xs text-gray-400">{{ __('partner.inventory.movements_count', ['count' => $movements->total()]) }}</span>
        </div>

        @if($movements->isEmpty())
            <div class="py-12 text-center">
                <p class="text-sm text-gray-400">{{ __('partner.inventory.no_movements') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr class="text-xs text-gray-500 uppercase">
                            <th class="text-right py-3 px-5 font-medium">{{ __('partner.inventory.movements_table.type') }}</th>
                            <th class="py-3 px-4 text-center font-medium">{{ __('partner.inventory.movements_table.change') }}</th>
                            <th class="py-3 px-4 text-center font-medium">{{ __('partner.inventory.movements_table.stock_after') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('partner.inventory.movements_table.warehouse') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('partner.inventory.movements_table.reason') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('partner.inventory.movements_table.by') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('partner.inventory.movements_table.date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($movements as $mov)
                            @php
                                [$movCls, $movLabel] = $movementTypeLabels[$mov->movement_type?->value]
                                    ?? ['text-gray-600', $mov->movement_type?->value];
                                $deltaSign = $mov->quantity_delta > 0 ? '+' : '';
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-5">
                                    <span class="{{ $movCls }} font-semibold text-xs">{{ $movLabel }}</span>
                                </td>
                                <td
                                    class="py-3 px-4 text-center {{ $mov->quantity_delta > 0 ? 'text-green-600' : 'text-red-600' }} font-bold">
                                    {{ $deltaSign }}{{ $mov->quantity_delta }}
                                </td>
                                <td class="py-3 px-4 text-center text-gray-700 font-medium">{{ $mov->quantity_after }}</td>
                                <td class="py-3 px-4 text-gray-600 text-xs">
                                    {{ $mov->warehouseInventory?->warehouse?->name ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-gray-600 text-xs">{{ $mov->reason ?? '—' }}</td>
                                <td class="py-3 px-4 text-gray-400 text-xs">
                                    {{ $mov->created_by_type ? class_basename($mov->created_by_type) . ' #' . $mov->created_by_id : __('partner.inventory.system') }}
                                </td>
                                <td class="py-3 px-4 text-gray-400 text-xs whitespace-nowrap">
                                    {{ $mov->created_at?->format('Y/m/d H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($movements->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $movements->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection