@extends('layouts.admin')

@section('title', $transfer->transfer_number . ' — ' . __('admin.warehouses_section.transfers'))

@section('content')

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.warehouses.index') }}" class="hover:text-primary-600">{{ __('admin.warehouses_section.title') }}</a>
        <span>/</span>
        <a href="{{ route('admin.warehouses.transfers.index') }}" class="hover:text-primary-600">{{ __('admin.warehouses_section.transfers') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-mono font-medium">{{ $transfer->transfer_number }}</span>
    </nav>

    {{-- ─── Header ──────────────────────────────────────────────────────────── --}}
    @php
        $statusBadge = match ($transfer->status) {
            \App\Enums\InventoryTransferStatus::Draft => 'bg-yellow-100 text-yellow-700',
            \App\Enums\InventoryTransferStatus::InTransit => 'bg-blue-100 text-blue-700',
            \App\Enums\InventoryTransferStatus::Received => 'bg-green-100 text-green-700',
            \App\Enums\InventoryTransferStatus::Cancelled => 'bg-gray-100 text-gray-500',
            default => 'bg-gray-100 text-gray-700',
        };
    @endphp

    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $transfer->transfer_number }}</h1>
            @php
                $transferStatusLabel = match ($transfer->status) {
                    \App\Enums\InventoryTransferStatus::Draft => __('common.pending'),
                    \App\Enums\InventoryTransferStatus::InTransit => __('admin.warehouses_section.in_transit'),
                    \App\Enums\InventoryTransferStatus::Received => __('admin.warehouses_section.received'),
                    \App\Enums\InventoryTransferStatus::Cancelled => __('common.cancelled'),
                    default => $transfer->status->value,
                };
            @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium {{ $statusBadge }}">
                {{ $transferStatusLabel }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            @if($transfer->status === \App\Enums\InventoryTransferStatus::Draft)
                <form action="{{ route('admin.warehouses.transfers.cancel', $transfer->id) }}" method="POST"
                    onsubmit="return confirm('{{ __('admin.warehouses_section.cancel_transfer_confirm') }}')">
                    @csrf @method('POST')
                    <button type="submit" class="btn btn-ghost btn-sm text-red-500">{{ __('admin.warehouses_section.cancel_transfer') }}</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
            {{ $errors->first('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- ─── Main ──────────────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Items --}}
            <x-card>
                <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.warehouses_section.transfer_items') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.listing') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.requested') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.received') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.damaged') }}</th>
                                <th class="pb-3">{{ __('admin.warehouses_section.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($transfer->items as $item)
                                <tr>
                                    <td class="py-2.5 pr-4">
                                        <span class="text-xs font-medium text-gray-800">
                                            {{ $item->vendorListing?->productVariant?->product?->name_en ?? '—' }}
                                        </span>
                                        <span class="block text-xs text-gray-400 font-mono">
                                            {{ $item->vendor_listing_id }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 pr-4 text-end tabular-nums text-gray-700">
                                        {{ number_format($item->quantity_requested) }}
                                    </td>
                                    <td class="py-2.5 pr-4 text-end tabular-nums text-green-700">
                                        {{ $item->quantity_received !== null ? number_format($item->quantity_received) : '—' }}
                                    </td>
                                    <td
                                        class="py-2.5 pr-4 text-end tabular-nums {{ $item->damaged_quantity > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                        {{ $item->damaged_quantity > 0 ? number_format($item->damaged_quantity) : '—' }}
                                    </td>
                                    <td class="py-2.5 text-xs text-gray-400">
                                        {{ $item->condition_notes ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-sm text-gray-400">{{ __('admin.warehouses_section.no_items') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- Ship action --}}
            @if($transfer->status === \App\Enums\InventoryTransferStatus::Draft)
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.warehouses_section.mark_as_shipped') }}</h3>
                    <form action="{{ route('admin.warehouses.transfers.ship', $transfer->id) }}" method="POST"
                        class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.carrier') }}</label>
                                <input type="text" name="carrier" class="form-input w-full text-sm" placeholder="e.g. FedEx"
                                    value="{{ old('carrier') }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.tracking_number') }}</label>
                                <input type="text" name="tracking_number" class="form-input w-full text-sm"
                                    placeholder="{{ __('admin.warehouses_section.optional') }}" value="{{ old('tracking_number') }}">
                            </div>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary btn-sm"
                                onclick="return confirm('{{ __('admin.warehouses_section.ship_transfer_confirm') }}')">
                                {{ __('admin.warehouses_section.ship_transfer') }}
                            </button>
                        </div>
                    </form>
                </x-card>
            @endif

            {{-- Receive action --}}
            @if($transfer->status === \App\Enums\InventoryTransferStatus::InTransit)
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.warehouses_section.receive_transfer') }}</h3>
                    <form action="{{ route('admin.warehouses.transfers.receive', $transfer->id) }}" method="POST"
                        class="space-y-4">
                        @csrf
                        @foreach($transfer->items as $item)
                            <div class="border border-gray-100 rounded-lg p-4">
                                <p class="text-sm font-medium text-gray-800 mb-3">
                                    {{ $item->vendorListing?->productVariant?->product?->name_en ?? $item->vendor_listing_id }}
                                    <span class="text-xs text-gray-400 ml-2">{{ __('admin.warehouses_section.requested_qty_hint', ['qty' => $item->quantity_requested]) }}</span>
                                </p>
                                <input type="hidden" name="items[{{ $loop->index }}][inventory_transfer_item_id]"
                                    value="{{ $item->id }}">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">{{ __('admin.warehouses_section.qty_received') }}</label>
                                        <input type="number" name="items[{{ $loop->index }}][quantity_received]"
                                            class="form-input w-full text-sm" min="0" value="{{ $item->quantity_requested }}"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">{{ __('admin.warehouses_section.damaged') }}</label>
                                        <input type="number" name="items[{{ $loop->index }}][damaged_quantity]"
                                            class="form-input w-full text-sm" min="0" value="0">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">{{ __('admin.warehouses_section.notes') }}</label>
                                        <input type="text" name="items[{{ $loop->index }}][condition_notes]"
                                            class="form-input w-full text-sm" placeholder="{{ __('admin.warehouses_section.optional') }}">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div>
                            <button type="submit" class="btn btn-primary btn-sm"
                                onclick="return confirm('{{ __('admin.warehouses_section.confirm_receipt_confirm') }}')">
                                {{ __('admin.warehouses_section.confirm_receipt') }}
                            </button>
                        </div>
                    </form>
                </x-card>
            @endif

        </div>

        {{-- ─── Sidebar ─────────────────────────────────────────────────── --}}
        <div class="space-y-5">
            <x-card>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.warehouses_section.transfer_info') }}</h3>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.from') }}</dt>
                        <dd class="mt-0.5 font-medium">
                            <a href="{{ route('admin.warehouses.show', $transfer->sourceWarehouse?->id) }}"
                                class="text-primary-600 hover:underline">
                                {{ $transfer->sourceWarehouse?->name ?? '—' }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.to') }}</dt>
                        <dd class="mt-0.5 font-medium">
                            <a href="{{ route('admin.warehouses.show', $transfer->destinationWarehouse?->id) }}"
                                class="text-primary-600 hover:underline">
                                {{ $transfer->destinationWarehouse?->name ?? '—' }}
                            </a>
                        </dd>
                    </div>
                    @if($transfer->vendor)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.vendor_column') }}</dt>
                            <dd class="mt-0.5">{{ $transfer->vendor->store_name }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.initiated_by_label') }}</dt>
                        <dd class="mt-0.5">{{ $transfer->initiatedBy?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.created_column') }}</dt>
                        <dd class="mt-0.5">{{ $transfer->created_at?->format('M d, Y H:i') }}</dd>
                    </div>
                    @if($transfer->expected_arrival_date)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.expected_arrival') }}</dt>
                            <dd class="mt-0.5">{{ $transfer->expected_arrival_date->format('M d, Y') }}</dd>
                        </div>
                    @endif
                    @if($transfer->carrier || $transfer->tracking_number)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.carrier_tracking') }}</dt>
                            <dd class="mt-0.5">
                                {{ $transfer->carrier ?? '—' }}
                                @if($transfer->tracking_number)
                                    <span class="block font-mono text-xs text-gray-500">{{ $transfer->tracking_number }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if($transfer->shipped_at)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.shipped') }}</dt>
                            <dd class="mt-0.5">{{ $transfer->shipped_at->format('M d, Y H:i') }}</dd>
                        </div>
                    @endif
                    @if($transfer->received_at)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.received') }}</dt>
                            <dd class="mt-0.5">{{ $transfer->received_at->format('M d, Y H:i') }}</dd>
                        </div>
                    @endif
                    @if($transfer->notes)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.notes') }}</dt>
                            <dd class="mt-0.5 text-gray-600">{{ $transfer->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        </div>

    </div>

@endsection
