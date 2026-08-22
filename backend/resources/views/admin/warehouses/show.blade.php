@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/admin/warehouse-detail.js'])
@endpush

@section('title', $warehouse->name . ' — ' . __('admin.warehouses_section.title'))

@section('content')

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.warehouses.index') }}" class="hover:text-primary-600">{{ __('admin.warehouses_section.title') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium">{{ $warehouse->name }}</span>
    </nav>

    {{-- ─── Header ──────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $warehouse->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5 font-mono">{{ $warehouse->code }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.warehouses.edit', $warehouse->id) }}" class="btn btn-secondary btn-sm">{{ __('admin.warehouses_section.edit') }}</a>
            <button
                class="btn btn-sm js-toggle-active {{ $warehouse->is_active ? 'btn-ghost text-red-500' : 'btn-primary' }}"
                data-url="{{ route('admin.warehouses.toggle-active', $warehouse->id) }}"
                data-active="{{ (int) $warehouse->is_active }}">
                {{ $warehouse->is_active ? __('admin.warehouses_section.deactivate') : __('admin.warehouses_section.activate') }}
            </button>
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

    {{-- ─── Info Cards ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

        <x-card class="lg:col-span-2">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.warehouses_section.details') }}</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.type_column') }}</dt>
                    <dd class="mt-0.5 font-medium">
                        @php
                            $typeBadge = match ($warehouse->type?->value) {
                                'platform_fbn' => 'bg-indigo-100 text-indigo-700',
                                'seller_owned' => 'bg-orange-100 text-orange-700',
                                default        => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $typeBadge }}">
                            {{ str_replace('_', ' ', $warehouse->type?->value) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('common.country') }}</dt>
                    <dd class="mt-0.5">{{ $warehouse->country->name_en ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.owner_vendor') }}</dt>
                    <dd class="mt-0.5">
                        @if($warehouse->ownerVendor)
                            <a href="{{ route('admin.vendors.show', $warehouse->owner_vendor_id) }}"
                               class="text-primary-600 hover:underline">{{ $warehouse->ownerVendor->store_name }}</a>
                        @else
                            <span class="text-gray-400">{{ __('admin.warehouses_section.platform_owned') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.manager_label') }}</dt>
                    <dd class="mt-0.5">{{ $warehouse->managerAdmin->name ?? '—' }}</dd>
                </div>
                @if($warehouse->latitude)
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.location') }}</dt>
                        <dd class="mt-0.5 font-mono text-xs">{{ $warehouse->latitude }}, {{ $warehouse->longitude }}</dd>
                    </div>
                @endif
                @if($warehouse->storage_rate_per_m3_price)
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('admin.warehouses_section.storage_rate') }}</dt>
                        <dd class="mt-0.5">{{ $warehouse->storage_rate_formatted }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-500">{{ __('common.status') }}</dt>
                    <dd class="mt-0.5">
                        @if($warehouse->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">{{ __('common.active') }}</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">{{ __('common.inactive') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.warehouses_section.capacity_title') }}</h3>
            @php $barColor = $usedPct >= 90 ? 'bg-red-500' : ($usedPct >= 70 ? 'bg-warning-500' : 'bg-green-500'); @endphp
            <div class="mb-4">
                <div class="flex justify-between text-sm text-gray-700 mb-1">
                    <span>{{ __('admin.warehouses_section.used_label', ['qty' => number_format($warehouse->used_capacity_m3, 1)]) }}</span>
                    <span class="font-medium {{ $usedPct >= 90 ? 'text-red-600' : '' }}">{{ $usedPct }}%</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-3">
                    <div class="h-3 rounded-full {{ $barColor }}" style="width: {{ min($usedPct, 100) }}%"></div>
                </div>
                <p class="text-xs text-gray-400 mt-1">{{ __('admin.warehouses_section.used_of_total', ['total' => number_format($warehouse->total_capacity_m3, 1)]) }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <p class="text-lg font-bold text-gray-900">{{ number_format($inventoryStats['total_skus']) }}</p>
                    <p class="text-xs text-gray-500">{{ __('admin.warehouses_section.skus') }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <p class="text-lg font-bold text-gray-900">{{ number_format($inventoryStats['total_units']) }}</p>
                    <p class="text-xs text-gray-500">{{ __('admin.warehouses_section.units') }}</p>
                </div>
                <div class="bg-orange-50 rounded-lg p-3 text-center">
                    <p class="text-lg font-bold text-orange-700">{{ number_format($inventoryStats['low_stock']) }}</p>
                    <p class="text-xs text-orange-600">{{ __('admin.warehouses_section.low_stock') }}</p>
                </div>
                <div class="bg-red-50 rounded-lg p-3 text-center">
                    <p class="text-lg font-bold text-red-700">{{ number_format($inventoryStats['out_of_stock']) }}</p>
                    <p class="text-xs text-red-600">{{ __('admin.warehouses_section.out_of_stock') }}</p>
                </div>
            </div>
        </x-card>

    </div>

    {{-- ─── Tabs ────────────────────────────────────────────────────────────── --}}
    <div x-data="{ tab: 'inventory' }">

        <div class="flex gap-1 mb-4 border-b border-gray-200">
            <button @click="tab = 'inventory'"
                :class="tab === 'inventory' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                {{ __('admin.warehouses_section.tab_inventory') }}
            </button>
            <button @click="tab = 'movements'"
                :class="tab === 'movements' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                {{ __('admin.warehouses_section.tab_recent_movements') }}
            </button>
            <button @click="tab = 'transfers'"
                :class="tab === 'transfers' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                {{ __('admin.warehouses_section.tab_transfers') }}
            </button>
            @if($warehouse->owner_vendor_id === null)
                <button @click="tab = 'vendor_limits'"
                    :class="tab === 'vendor_limits' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                    Vendor Limits
                </button>
            @endif
            @if($warehouse->type?->value === 'platform_fbn')
                <button @click="tab = 'overage_fees'"
                    :class="tab === 'overage_fees' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                    Daily Overage Fees
                </button>
            @endif
            <button @click="tab = 'exceptional-zones'"
                :class="tab === 'exceptional-zones' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors">
                {{ __('admin.warehouses_section.tab_exceptional_zones') }}
            </button>
        </div>

        {{-- ─── Inventory Tab ─────────────────────────────────────────────── --}}
        <div x-show="tab === 'inventory'">
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.warehouses_section.tab_inventory') }}</h3>
                    <div class="flex items-center gap-2">
                        <select id="inv-filter-status" class="form-input text-xs w-36">
                            <option value="">{{ __('admin.warehouses_section.all_statuses') }}</option>
                            <option value="low_stock">{{ __('admin.warehouses_section.low_stock') }}</option>
                            <option value="out_of_stock">{{ __('admin.warehouses_section.out_of_stock') }}</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="inventory-table"
                        data-url="{{ route('admin.warehouses.inventory.datatable', $warehouse->id) }}"
                        class="w-full text-sm">
                        <thead>
                            <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.product_sku_column') }}</th>
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.vendor_column') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.on_hand') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.available') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.reserved') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.damaged') }}</th>
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.bin') }}</th>
                                <th class="pb-3"></th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- ─── Recent Movements Tab ──────────────────────────────────────── --}}
        <div x-show="tab === 'movements'" x-cloak>
            <x-card>
                <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.warehouses_section.recent_movements_title') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.time_column') }}</th>
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.type_column') }}</th>
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.product_column') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.delta_column') }}</th>
                                <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.after_column') }}</th>
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.reason_column') }}</th>
                                <th class="pb-3">{{ __('admin.warehouses_section.by_column') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recentMovements as $mv)
                                <tr>
                                    <td class="py-2.5 pr-4 text-xs text-gray-400 whitespace-nowrap">
                                        {{ $mv->created_at->format('M d, H:i') }}
                                    </td>
                                    <td class="py-2.5 pr-4">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono
                                            {{ str_starts_with($mv->movement_type?->value ?? '', 'transfer') ? 'bg-blue-50 text-blue-700' :
                                               ($mv->movement_type?->value === 'damaged' ? 'bg-red-50 text-red-700' :
                                               ($mv->quantity_delta > 0 ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700')) }}">
                                            {{ $mv->movement_type?->value }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 pr-4 text-xs text-gray-700">
                                        {{ $mv->warehouseInventory?->vendorListing?->productVariant?->product?->name_en ?? '—' }}
                                    </td>
                                    <td class="py-2.5 pr-4 text-end tabular-nums font-medium {{ $mv->quantity_delta > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $mv->quantity_delta > 0 ? '+' : '' }}{{ number_format($mv->quantity_delta) }}
                                    </td>
                                    <td class="py-2.5 pr-4 text-end tabular-nums text-gray-600">
                                        {{ number_format($mv->quantity_after) }}
                                    </td>
                                    <td class="py-2.5 pr-4 text-xs text-gray-500 max-w-xs truncate">
                                        {{ $mv->reason ?? '—' }}
                                    </td>
                                    <td class="py-2.5 text-xs text-gray-400">
                                        {{ $mv->createdBy?->name ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-sm text-gray-400">{{ __('admin.warehouses_section.no_movements') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- ─── Transfers Tab ─────────────────────────────────────────────── --}}
        <div x-show="tab === 'transfers'" x-cloak>
            <x-card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.warehouses_section.recent_transfers') }}</h3>
                    <a href="{{ route('admin.warehouses.transfers.create') }}" class="btn btn-primary btn-xs">{{ __('admin.warehouses_section.new_transfer') }}</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.transfer_number_column') }}</th>
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.direction_column') }}</th>
                                <th class="pb-3 pr-4">{{ __('common.status') }}</th>
                                <th class="pb-3 pr-4">{{ __('admin.warehouses_section.created_column') }}</th>
                                <th class="pb-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($transfers as $tf)
                                @php
                                    $isOutbound = $tf->source_warehouse_id === $warehouse->id;
                                    $statusBadge = match($tf->status) {
                                        \App\Enums\InventoryTransferStatus::Draft     => 'bg-yellow-100 text-yellow-700',
                                        \App\Enums\InventoryTransferStatus::InTransit => 'bg-blue-100 text-blue-700',
                                        \App\Enums\InventoryTransferStatus::Received  => 'bg-green-100 text-green-700',
                                        \App\Enums\InventoryTransferStatus::Cancelled => 'bg-gray-100 text-gray-500',
                                        default      => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <tr>
                                    <td class="py-2.5 pr-4">
                                        <a href="{{ route('admin.warehouses.transfers.show', $tf->id) }}"
                                           class="font-mono text-xs text-primary-600 hover:underline">
                                            {{ $tf->transfer_number }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 pr-4 text-xs">
                                        @if($isOutbound)
                                            <span class="text-orange-600">▶ {{ __('admin.warehouses_section.direction_out') }} → {{ $tf->destinationWarehouse?->name ?? '—' }}</span>
                                        @else
                                            <span class="text-green-600">◀ {{ __('admin.warehouses_section.direction_in') }} ← {{ $tf->sourceWarehouse?->name ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pr-4">
                                        @php
                                            $tfStatusLabel = match ($tf->status) {
                                                \App\Enums\InventoryTransferStatus::Draft => __('common.pending'),
                                                \App\Enums\InventoryTransferStatus::InTransit => __('admin.warehouses_section.in_transit'),
                                                \App\Enums\InventoryTransferStatus::Received => __('admin.warehouses_section.received'),
                                                \App\Enums\InventoryTransferStatus::Cancelled => __('common.cancelled'),
                                                default => $tf->status->value,
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }}">
                                            {{ $tfStatusLabel }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 pr-4 text-xs text-gray-400">
                                        {{ $tf->created_at?->format('M d, Y') }}
                                    </td>
                                    <td class="py-2.5">
                                        <a href="{{ route('admin.warehouses.transfers.show', $tf->id) }}"
                                           class="btn btn-xs btn-ghost">{{ __('admin.warehouses_section.view') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-sm text-gray-400">{{ __('admin.warehouses_section.no_transfers_for_warehouse') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- ─── Vendor Limits Tab ─────────────────────────────────────────── --}}
        @if($warehouse->owner_vendor_id === null)
            <div x-show="tab === 'vendor_limits'" x-cloak>
                <x-card>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.warehouses_section.per_vendor_storage_limits') }}</h3>
                        <button type="button" id="btn-add-vendor-limit" class="btn btn-primary btn-xs">{{ __('admin.warehouses_section.add_limit') }}</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                    <th class="pb-3 pr-4">{{ __('admin.warehouses_section.vendor_column') }}</th>
                                    <th class="pb-3 pr-4">{{ __('admin.warehouses_section.limit_type_column') }}</th>
                                    <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.limit_column') }}</th>
                                    <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.current_usage_column') }}</th>
                                    <th class="pb-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($vendorLimits as $vl)
                                    @php
                                        $isQty = $vl['limit_type'] === 'quantity';
                                        $limitValue = $isQty ? number_format($vl['max_quantity']) . ' units' : number_format($vl['max_capacity_m3'], 2) . ' m³';
                                        $usageValue = $isQty ? number_format($vl['current_usage']) . ' units' : number_format($vl['current_usage'], 2) . ' m³';
                                        $overLimit = $isQty
                                            ? $vl['current_usage'] > $vl['max_quantity']
                                            : $vl['current_usage'] > (float) $vl['max_capacity_m3'];
                                    @endphp
                                    <tr>
                                        <td class="py-2.5 pr-4">{{ $vl['vendor_name'] }}</td>
                                        <td class="py-2.5 pr-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $isQty ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                                {{ $isQty ? __('admin.warehouses_section.quantity_type_label') : __('admin.warehouses_section.capacity_m3_label') }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 pr-4 text-end tabular-nums">{{ $limitValue }}</td>
                                        <td class="py-2.5 pr-4 text-end tabular-nums {{ $overLimit ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $usageValue }}</td>
                                        <td class="py-2.5 text-end">
                                            <button type="button"
                                                class="btn btn-xs btn-ghost text-red-500 js-delete-vendor-limit"
                                                data-url="{{ route('admin.warehouses.vendor-limits.destroy', [$warehouse->id, $vl['id']]) }}">
                                                {{ __('admin.warehouses_section.remove') }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-sm text-gray-400">{{ __('admin.warehouses_section.no_vendor_limits') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
        @endif

        {{-- ─── Daily Overage Fees Tab ────────────────────────────────────── --}}
        @if($warehouse->type?->value === 'platform_fbn')
            <div x-show="tab === 'overage_fees'" x-cloak>
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.warehouses_section.daily_overage_fees') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Free storage: {{ $warehouse->free_storage_days }} days.
                        Daily fee: {{ number_format($warehouse->daily_fee_per_unit) }} {{ $warehouse->daily_fee_currency ?? '—' }} per unit/day.
                    </p>
                    <div class="overflow-x-auto">
                        <table id="overage-fees-table"
                            data-url="{{ route('admin.warehouses.overage-fees.datatable', $warehouse->id) }}"
                            class="w-full text-sm">
                            <thead>
                                <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                    <th class="pb-3 pr-4">{{ __('admin.warehouses_section.date_column') }}</th>
                                    <th class="pb-3 pr-4">{{ __('admin.warehouses_section.vendor_column') }}</th>
                                    <th class="pb-3 pr-4">{{ __('admin.warehouses_section.sku_listing_column') }}</th>
                                    <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.units_column') }}</th>
                                    <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.fee_per_unit_column') }}</th>
                                    <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.total_fee_column') }}</th>
                                    <th class="pb-3 pr-4">{{ __('admin.warehouses_section.currency_column') }}</th>
                                    <th class="pb-3">{{ __('admin.warehouses_section.status_column') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </x-card>
            </div>
        @endif

        {{-- ─── Exceptional (Gap) Zones Tab ──────────────────────────────────── --}}
        <div x-show="tab === 'exceptional-zones'" x-cloak>
            <x-card>
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.warehouses_section.exceptional_zones_title') }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ __('admin.warehouses_section.exceptional_zones_hint') }}</p>
                </div>

                @if($allZonesInCountry->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('admin.warehouses_section.no_gap_zones') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                    <th class="pb-3 pr-4">{{ __('admin.warehouses_section.zone_column') }}</th>
                                    <th class="pb-3 pr-4">{{ __('admin.warehouses_section.subsidy_rules_column') }}</th>
                                    <th class="pb-3 pr-4 text-center">{{ __('admin.warehouses_section.opted_in_column') }}</th>
                                    <th class="pb-3 text-end">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allZonesInCountry as $zone)
                                    @php $isOptedIn = (bool) ($exceptionalZones[$zone->id] ?? false); @endphp
                                    <tr class="border-b border-gray-100">
                                        <td class="py-3 pr-4 text-gray-800">{{ $zone->name }}</td>
                                        <td class="py-3 pr-4 text-xs text-gray-500">
                                            @forelse($subsidyRules[$zone->id] ?? [] as $rule)
                                                <div>{{ $rule->shippingMethod?->name ?? '—' }}:
                                                    @if($rule->split_type === 'fixed')
                                                        {{ $rule->vendor_fixed_amount }}/{{ $rule->admin_fixed_amount }} ({{ __('admin.warehouses_section.fixed_label') }})
                                                    @else
                                                        {{ $rule->vendor_share_pct }}%/{{ 100 - $rule->vendor_share_pct }}% ({{ __('admin.warehouses_section.percentage_label') }})
                                                    @endif
                                                    @if(!$rule->warehouse_id)
                                                        <span class="text-gray-400">({{ __('admin.warehouses_section.global_rule_label') }})</span>
                                                    @endif
                                                </div>
                                            @empty
                                                <span class="text-gray-400">{{ __('admin.warehouses_section.no_subsidy_rule') }}</span>
                                            @endforelse
                                        </td>
                                        <td class="py-3 pr-4 text-center">
                                            @if($isOptedIn)
                                                <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700">{{ __('common.active') }}</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ __('common.inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-end">
                                            <form method="POST" action="{{ route('admin.warehouses.exceptional-zones.toggle', [$warehouse->id, $zone->id]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-ghost">
                                                    {{ $isOptedIn ? __('admin.warehouses_section.opt_out') : __('admin.warehouses_section.opt_in') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

    </div>

    {{-- ─── Adjust Inventory Modal ──────────────────────────────────────────── --}}
    <div id="adjust-modal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.warehouses_section.adjust_inventory') }}</h3>
                <button id="close-adjust-modal" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form id="adjust-form" class="px-6 py-5 space-y-4">
                @csrf
                <input type="hidden" id="adjust-url" name="_adjust_url">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.movement_type') }}</label>
                    <select name="movement_type" class="form-input w-full text-sm" required>
                        <option value="adjustment">{{ __('admin.warehouses_section.manual_adjustment') }}</option>
                        <option value="receive">{{ __('admin.warehouses_section.receive') }}</option>
                        <option value="return">{{ __('admin.warehouses_section.return') }}</option>
                        <option value="count">{{ __('admin.warehouses_section.stock_count') }}</option>
                        <option value="damaged">{{ __('admin.warehouses_section.mark_damaged') }}</option>
                        <option value="write_off">{{ __('admin.warehouses_section.write_off') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __('admin.warehouses_section.quantity_delta') }} <span class="text-gray-400">{{ __('admin.warehouses_section.negative_to_remove') }}</span>
                    </label>
                    <input type="number" name="delta" class="form-input w-full text-sm" placeholder="{{ __('admin.warehouses_section.quantity_delta_placeholder') }}" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.reason') }}</label>
                    <input type="text" name="reason" class="form-input w-full text-sm" placeholder="{{ __('admin.warehouses_section.reason_placeholder') }}" required>
                </div>
                <div id="adjust-error" class="hidden text-sm text-red-600 bg-red-50 rounded p-2"></div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" id="cancel-adjust" class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.warehouses_section.apply') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Inventory Movements Modal ────────────────────────────────────────── --}}
    <div id="movements-modal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.warehouses_section.recent_movements_title') }}</h3>
                <button type="button" id="close-movements-modal" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div class="px-6 py-5 max-h-[70vh] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                            <th class="pb-3 pr-4">{{ __('admin.warehouses_section.time_column') }}</th>
                            <th class="pb-3 pr-4">{{ __('admin.warehouses_section.type_column') }}</th>
                            <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.delta_column') }}</th>
                            <th class="pb-3 pr-4 text-end">{{ __('admin.warehouses_section.after_column') }}</th>
                            <th class="pb-3 pr-4">{{ __('admin.warehouses_section.reason_column') }}</th>
                            <th class="pb-3">{{ __('admin.warehouses_section.by_column') }}</th>
                        </tr>
                    </thead>
                    <tbody id="movements-modal-body" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ─── Add Vendor Limit Modal ──────────────────────────────────────────── --}}
    @if($warehouse->owner_vendor_id === null)
        <div id="vendor-limit-modal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('admin.warehouses_section.add_update_vendor_limit') }}</h3>
                    <button type="button" id="close-vendor-limit-modal" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>
                <form id="vendor-limit-form" data-url="{{ route('admin.warehouses.vendor-limits.store', $warehouse->id) }}" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.vendor_field_label') }}</label>
                        <select name="vendor_id" class="form-input w-full text-sm" required>
                            <option value="">{{ __('admin.warehouses_section.select_vendor_placeholder') }}</option>
                            @foreach($vendors as $vendorId => $vendorName)
                                <option value="{{ $vendorId }}">{{ $vendorName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-2">{{ __('admin.warehouses_section.limit_type_column') }}</label>
                        <div class="inline-flex rounded-lg border border-gray-200 p-0.5 bg-gray-50">
                            <button type="button" data-value="quantity" class="js-limit-type-btn px-3 py-1.5 text-xs font-medium rounded-md transition-colors bg-white shadow-sm text-gray-800">
                                {{ __('admin.warehouses_section.quantity_type_label') }}
                            </button>
                            <button type="button" data-value="capacity" class="js-limit-type-btn px-3 py-1.5 text-xs font-medium rounded-md transition-colors text-gray-500">
                                {{ __('admin.warehouses_section.capacity_m3_label') }}
                            </button>
                        </div>
                        <input type="hidden" name="limit_type" value="quantity">
                    </div>
                    <div id="vendor-limit-qty-field">
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.max_quantity_label') }}</label>
                        <input type="number" name="max_quantity" min="1" class="form-input w-full text-sm" placeholder="{{ __('admin.warehouses_section.max_quantity_placeholder') }}">
                    </div>
                    <div id="vendor-limit-capacity-field" class="hidden">
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.max_capacity_label') }}</label>
                        <input type="number" step="0.01" min="0.01" name="max_capacity_m3" class="form-input w-full text-sm" placeholder="{{ __('admin.warehouses_section.max_capacity_placeholder') }}">
                    </div>
                    <div id="vendor-limit-error" class="hidden text-sm text-red-600 bg-red-50 rounded p-2"></div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" id="cancel-vendor-limit" class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
<script type="module">
const INVENTORY_DATATABLE_URL = '{{ route('admin.warehouses.inventory.datatable', $warehouse->id) }}';
const TOGGLE_URL = '{{ route('admin.warehouses.toggle-active', $warehouse->id) }}';
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    noMovements: @json(__('admin.warehouses_section.no_movements')),
    deactivateWarehouseConfirm: @json(__('admin.warehouses_section.deactivate_warehouse_confirm')),
    activateWarehouseConfirm: @json(__('admin.warehouses_section.activate_warehouse_confirm')),
    requestFailed: @json(__('admin.warehouses_section.request_failed')),
});
</script>
@endpush
