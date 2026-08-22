@extends('layouts.partner')
@section('title', __('partner.warehouses.transfer_title', ['number' => $transfer->transfer_number]))
@section('page-title', __('partner.warehouses.transfer_title', ['number' => $transfer->transfer_number]))

@push('scripts')
    @vite('resources/js/partner/warehouses.js')
    <script>
        window.TRANSFER_SHOW_CFG = {
            transferId: '{{ $transfer->id }}',
            shipUrl:    '{{ route('partner.warehouses.transfers.ship', $transfer->id) }}',
            cancelUrl:  '{{ route('partner.warehouses.transfers.cancel', $transfer->id) }}',
            status:     '{{ $transfer->status->value }}',
        };
    </script>
@endpush

@section('content')

    <div class="mb-5">
        <a href="{{ route('partner.warehouses.transfers.index') }}"
            class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
            {{ __('partner.warehouses.back_to_transfers') }}
        </a>
    </div>

    {{-- Header card --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-5">
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="font-mono font-bold text-lg text-gray-900">{{ $transfer->transfer_number }}</span>
                    @php
                        $statusClasses = match($transfer->status) {
                            \App\Enums\InventoryTransferStatus::Draft      => 'bg-gray-100 text-gray-600',
                            \App\Enums\InventoryTransferStatus::InTransit  => 'bg-blue-100 text-blue-700',
                            \App\Enums\InventoryTransferStatus::Received   => 'bg-green-100 text-green-700',
                            \App\Enums\InventoryTransferStatus::Cancelled  => 'bg-red-100 text-red-600',
                            default      => 'bg-gray-100 text-gray-600',
                        };
                        $statusLabels = [
                            'draft'      => __('partner.warehouses.status_draft'),
                            'in_transit' => __('partner.warehouses.status_in_transit'),
                            'received'   => __('partner.warehouses.status_received'),
                            'cancelled'  => __('partner.warehouses.status_cancelled'),
                        ];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold {{ $statusClasses }}">
                        {{ $statusLabels[$transfer->status->value] ?? ucfirst(str_replace('_', ' ', $transfer->status->value)) }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm">
                    <div>
                        <span class="text-gray-500">{{ __('partner.warehouses.from_label') }}</span>
                        <span class="font-medium text-gray-800 ml-1">{{ $transfer->sourceWarehouse->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">{{ __('partner.warehouses.to_label') }}</span>
                        <span class="font-medium text-gray-800 ml-1">{{ $transfer->destinationWarehouse->name }}</span>
                    </div>
                    @if($transfer->expected_arrival_date)
                        <div>
                            <span class="text-gray-500">{{ __('partner.warehouses.expected_arrival_label') }}</span>
                            <span class="font-medium text-gray-800 ml-1">{{ $transfer->expected_arrival_date->format('d M Y') }}</span>
                        </div>
                    @endif
                    @if($transfer->carrier)
                        <div>
                            <span class="text-gray-500">{{ __('partner.warehouses.carrier_label') }}</span>
                            <span class="font-medium text-gray-800 ml-1">{{ $transfer->carrier }}</span>
                        </div>
                    @endif
                    @if($transfer->tracking_number)
                        <div>
                            <span class="text-gray-500">{{ __('partner.warehouses.tracking_label') }}</span>
                            <span class="font-medium text-gray-800 ml-1">{{ $transfer->tracking_number }}</span>
                        </div>
                    @endif
                    @if($transfer->shipped_at)
                        <div>
                            <span class="text-gray-500">{{ __('partner.warehouses.shipped_label') }}</span>
                            <span class="font-medium text-gray-800 ml-1">{{ $transfer->shipped_at->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                </div>
                @if($transfer->notes)
                    <p class="mt-2 text-sm text-gray-500 italic">{{ $transfer->notes }}</p>
                @endif
            </div>

            <div class="flex gap-2 flex-shrink-0 ml-4">
                @if($transfer->status === \App\Enums\InventoryTransferStatus::Draft)
                    <button id="ship-transfer-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 transition-colors">
                        {{ __('partner.warehouses.mark_as_shipped') }}
                    </button>
                    <button id="cancel-transfer-btn"
                        class="inline-flex items-center px-3 py-2 border border-red-200 text-red-600 text-sm font-medium rounded-xl hover:bg-red-50 transition-colors">
                        {{ __('common.cancel') }}
                    </button>
                @elseif($transfer->status === \App\Enums\InventoryTransferStatus::InTransit)
                    <button id="cancel-transfer-btn"
                        class="inline-flex items-center px-3 py-2 border border-red-200 text-red-600 text-sm font-medium rounded-xl hover:bg-red-50 transition-colors">
                        {{ __('partner.warehouses.cancel_transfer') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Items table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="font-semibold text-gray-800 text-sm">{{ __('partner.warehouses.transfer_items_count', ['count' => $transfer->items->count()]) }}</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2.5 text-left">{{ __('partner.warehouses.product') }}</th>
                    <th class="px-4 py-2.5 text-left">{{ __('partner.warehouses.sku') }}</th>
                    <th class="px-4 py-2.5 text-right">{{ __('partner.warehouses.requested') }}</th>
                    <th class="px-4 py-2.5 text-right">{{ __('partner.warehouses.received') }}</th>
                    <th class="px-4 py-2.5 text-right">{{ __('partner.warehouses.damaged') }}</th>
                    @if($transfer->items->first()?->condition_notes !== null || $transfer->status === \App\Enums\InventoryTransferStatus::Received)
                        <th class="px-4 py-2.5 text-left">{{ __('partner.warehouses.notes_header') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($transfer->items as $item)
                    @php
                        $product = $item->vendorListing?->productVariant?->product;
                        $variant = $item->vendorListing?->productVariant;
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $product?->name_en ?? '—' }}</p>
                            @if($variant?->variant_name)
                                <p class="text-xs text-gray-500">{{ $variant->variant_name }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $item->vendorListing?->vendor_sku ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ number_format($item->quantity_requested) }}</td>
                        <td class="px-4 py-3 text-right {{ $item->quantity_received > 0 ? 'text-green-700 font-semibold' : 'text-gray-400' }}">
                            {{ number_format($item->quantity_received) }}
                        </td>
                        <td class="px-4 py-3 text-right {{ $item->damaged_quantity > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                            {{ number_format($item->damaged_quantity) }}
                        </td>
                        @if($transfer->items->first()?->condition_notes !== null || $transfer->status === \App\Enums\InventoryTransferStatus::Received)
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $item->condition_notes ?? '—' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Ship modal --}}
    @if($transfer->status === \App\Enums\InventoryTransferStatus::Draft)
        <div id="ship-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">{{ __('partner.warehouses.mark_as_shipped') }}</h3>
                    <button onclick="document.getElementById('ship-modal').classList.add('hidden');document.getElementById('ship-modal').classList.remove('flex');"
                        class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none">&times;</button>
                </div>
                <div class="p-5 space-y-4">
                    <p class="text-sm text-gray-600">{{ __('partner.warehouses.ship_deduct_notice') }}</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.carrier_optional') }}</label>
                        <input type="text" id="ship-carrier" maxlength="100"
                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.tracking_optional') }}</label>
                        <input type="text" id="ship-tracking" maxlength="100"
                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div id="ship-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"></div>
                    <div class="flex justify-end gap-3">
                        <button onclick="document.getElementById('ship-modal').classList.add('hidden');document.getElementById('ship-modal').classList.remove('flex');"
                            class="px-4 py-2 border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button id="confirm-ship-btn"
                            class="px-4 py-2 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 disabled:opacity-50 transition-colors">
                            {{ __('partner.warehouses.confirm_shipment') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection
