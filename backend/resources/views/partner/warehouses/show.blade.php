@extends('layouts.partner')
@section('title', $warehouse->name)
@section('page-title', $warehouse->name)

@push('scripts')
    @vite('resources/js/partner/warehouse-detail.js')
    <script>
        window.WAREHOUSE_ID  = '{{ $warehouse->id }}';
        window.IS_OWNED      = {{ $warehouse->owner_vendor_id ? 'true' : 'false' }};
        window.WAREHOUSE_CFG = {
            inventoryUrl:    '{{ route('partner.warehouses.inventory', $warehouse->id) }}',
            adjustBaseUrl:   '{{ url('/partner/warehouses/inventory') }}',
            deactivateUrl:   '{{ $warehouse->owner_vendor_id ? route('partner.warehouses.deactivate', $warehouse->id) : '' }}',
            transfersUrl:    '{{ route('partner.warehouses.transfers.index') }}',
            newTransferUrl:  '{{ $warehouse->owner_vendor_id ? route('partner.warehouses.transfers.create', ['source' => $warehouse->id]) : '' }}',
        };
    </script>
@endpush

@section('content')

    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $warehouse->code }}</span>
                @if($warehouse->owner_vendor_id)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">{{ __('partner.warehouses.seller_owned') }}</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">{{ __('partner.warehouses.platform_fbn') }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">{{ __('partner.warehouses.read_only') }}</span>
                @endif
                @unless($warehouse->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">{{ __('partner.warehouses.inactive') }}</span>
                @endunless
            </div>
            <p class="text-sm text-gray-500">{{ session('locale', 'ar') === 'ar' ? ($warehouse->country->name_ar ?? $warehouse->country->name_en) : $warehouse->country->name_en }}</p>
            @if($warehouse->address)
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ collect([$warehouse->address->street_address, $warehouse->address->area, $warehouse->address->building])->filter()->implode(', ') }}
                </p>
            @endif
        </div>

        @if($warehouse->owner_vendor_id)
            <div class="flex gap-2">
                <button id="edit-warehouse-btn"
                    class="inline-flex items-center px-3 py-1.5 border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    {{ __('partner.warehouses.edit') }}
                </button>
                <a href="{{ route('partner.warehouses.transfers.create', ['source' => $warehouse->id]) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                    {{ __('partner.warehouses.new_transfer') }}
                </a>
            </div>
        @endif
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-primary-700">{{ $usage['total_skus'] }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.warehouses.total_skus') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-700">{{ number_format($usage['total_units']) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.warehouses.total_units_stat') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold {{ $usage['low_stock_count'] > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                {{ $usage['low_stock_count'] }}
            </p>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.warehouses.low_stock') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold {{ $usage['out_of_stock_count'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                {{ $usage['out_of_stock_count'] }}
            </p>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.warehouses.out_of_stock') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-red-500">{{ number_format($usage['damaged_units']) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.warehouses.damaged') }}</p>
        </div>
    </div>

    {{-- Capacity bar --}}
    @if($warehouse->total_capacity_m3)
        @php $pct = round(($warehouse->used_capacity_m3 / $warehouse->total_capacity_m3) * 100, 1); @endphp
        <div class="bg-white rounded-2xl border p-4 mb-6">
            <div class="flex justify-between text-sm mb-2">
                <span class="font-medium text-gray-700">{{ __('partner.warehouses.storage_capacity') }}</span>
                <span class="font-bold {{ $pct > 90 ? 'text-red-600' : 'text-gray-700' }}">
                    {{ $pct }}% {{ __('partner.warehouses.used_suffix') }}
                    <span class="font-normal text-gray-400">({{ $warehouse->used_capacity_m3 }} / {{ $warehouse->total_capacity_m3 }} m³)</span>
                </span>
            </div>
            <div class="h-3 bg-gray-100 rounded-full">
                <div class="h-full rounded-full {{ $pct > 90 ? 'bg-red-500' : 'bg-green-500' }}" style="width:{{ min(100, $pct) }}%"></div>
            </div>
        </div>
    @endif

    {{-- Read-only notice for FBN --}}
    @unless($warehouse->owner_vendor_id)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
            <div>
                <p class="text-sm font-medium text-amber-800">{{ __('partner.warehouses.read_only_view') }}</p>
                <p class="text-xs text-amber-700 mt-0.5">{{ __('partner.warehouses.read_only_view_desc') }}</p>
            </div>
        </div>
    @endunless

    {{-- Inventory table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50 flex items-center gap-3 flex-wrap">
            <input type="text" id="inventory-search"
                placeholder="{{ __('partner.warehouses.search_placeholder') }}"
                class="rounded-xl border border-gray-300 px-3 py-1.5 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-primary-500" />
            <select id="inventory-status-filter"
                class="rounded-xl border border-gray-300 px-3 py-1.5 text-sm w-44 focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="">{{ __('partner.warehouses.all_status') }}</option>
                <option value="low_stock">{{ __('partner.warehouses.low_stock') }}</option>
                <option value="out_of_stock">{{ __('partner.warehouses.out_of_stock') }}</option>
            </select>
            @if($warehouse->owner_vendor_id)
                <span class="text-xs text-gray-400 ml-auto">{{ __('partner.warehouses.adjust_stock_hint') }}</span>
            @else
                <span class="text-xs text-amber-600 ml-auto font-medium">{{ __('partner.warehouses.read_only_adjust_hint') }}</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2.5 text-left">{{ __('partner.warehouses.product') }}</th>
                        <th class="px-4 py-2.5 text-left">{{ __('partner.warehouses.bin') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('partner.warehouses.on_hand') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('partner.warehouses.reserved') }}</th>
                        <th class="px-4 py-2.5 text-right font-bold text-gray-700">{{ __('partner.warehouses.available') }}</th>
                        <th class="px-4 py-2.5 text-right text-blue-600">{{ __('partner.warehouses.inbound') }}</th>
                        <th class="px-4 py-2.5 text-right text-red-500">{{ __('partner.warehouses.damaged') }}</th>
                        <th class="px-4 py-2.5 text-center">{{ __('common.status') }}</th>
                        @if($warehouse->owner_vendor_id)
                            <th class="px-4 py-2.5 w-20"></th>
                        @endif
                    </tr>
                </thead>
                <tbody id="inventory-tbody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>

        <div id="inventory-loading" class="p-12 text-center hidden">
            <div class="animate-spin w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full mx-auto"></div>
        </div>

        <div id="inventory-empty" class="p-12 text-center text-gray-400 text-sm hidden">
            {{ __('partner.warehouses.no_inventory_records') }}
        </div>

        <div id="inventory-pagination" class="px-5 py-3 border-t flex items-center justify-between text-sm text-gray-500"></div>
    </div>

    {{-- Adjust stock modal — only for seller-owned warehouses --}}
    @if($warehouse->owner_vendor_id)
        <div id="adjust-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">{{ __('partner.warehouses.adjust_stock_modal_title') }}</h3>
                    <button onclick="document.getElementById('adjust-modal').classList.add('hidden');document.getElementById('adjust-modal').classList.remove('flex');"
                        class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none">&times;</button>
                </div>
                <div class="p-5 space-y-4">
                    <input type="hidden" id="adjust-inventory-id" />

                    <div id="adjust-product-info" class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                        <img id="adjust-product-img" class="w-12 h-12 rounded-lg object-cover bg-gray-200" src="" alt="" />
                        <div>
                            <p id="adjust-product-name" class="font-medium text-sm">—</p>
                            <p id="adjust-product-variant" class="text-xs text-gray-500">—</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="bg-gray-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500">{{ __('partner.warehouses.on_hand') }}</p>
                            <p id="current-on-hand" class="text-xl font-bold">—</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500">{{ __('partner.warehouses.reserved') }}</p>
                            <p id="current-reserved" class="text-xl font-bold text-gray-600">—</p>
                        </div>
                        <div class="bg-green-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500">{{ __('partner.warehouses.available') }}</p>
                            <p id="current-available" class="text-xl font-bold text-green-700">—</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.adjustment_type') }}</label>
                        <select id="adjust-type-select"
                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                            <option value="adjustment">{{ __('partner.warehouses.type_stock_adjustment') }}</option>
                            <option value="inbound">{{ __('partner.warehouses.type_received_stock') }}</option>
                            <option value="return">{{ __('partner.warehouses.type_customer_return') }}</option>
                            <option value="damage">{{ __('partner.warehouses.type_mark_damaged') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.adjustment_amount') }}</label>
                        <div class="flex items-center gap-3">
                            <button type="button" id="adjust-minus-btn"
                                class="w-10 h-10 bg-red-50 text-red-600 rounded-xl font-bold text-xl hover:bg-red-100 transition-colors">−</button>
                            <input type="number" id="adjust-amount" value="0"
                                class="flex-1 rounded-xl border border-gray-300 px-3 py-2 text-center text-xl font-bold focus:outline-none focus:ring-2 focus:ring-primary-500" />
                            <button type="button" id="adjust-plus-btn"
                                class="w-10 h-10 bg-green-50 text-green-600 rounded-xl font-bold text-xl hover:bg-green-100 transition-colors">+</button>
                        </div>
                        <div class="mt-2 p-3 bg-primary-50 rounded-xl flex justify-between">
                            <span class="text-sm text-primary-700">{{ __('partner.warehouses.new_on_hand') }}</span>
                            <span id="adjust-new-value" class="font-bold text-primary-900">—</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.reason') }} <span class="text-red-500">*</span></label>
                        <textarea id="adjust-reason" rows="2" required maxlength="255"
                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>

                    <div id="adjust-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"></div>

                    <div class="flex justify-end gap-3">
                        <button onclick="document.getElementById('adjust-modal').classList.add('hidden');document.getElementById('adjust-modal').classList.remove('flex');"
                            class="px-4 py-2 border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors">
                            {{ __('common.cancel') }}
                        </button>
                        <button id="confirm-adjust-btn"
                            class="px-4 py-2 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 disabled:opacity-50 transition-colors">
                            {{ __('partner.warehouses.apply') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Edit warehouse modal --}}
        <div id="edit-warehouse-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">{{ __('partner.warehouses.edit_warehouse_title') }}</h3>
                    <button onclick="document.getElementById('edit-warehouse-modal').classList.add('hidden');document.getElementById('edit-warehouse-modal').classList.remove('flex');"
                        class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none">&times;</button>
                </div>
                <form id="edit-warehouse-form" class="p-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.warehouse_name') }}</label>
                        <input type="text" name="name" id="edit-wh-name" value="{{ $warehouse->name }}" maxlength="150"
                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.street_address') }}</label>
                        <input type="text" name="street_address" id="edit-wh-street" value="{{ $warehouse->address?->street_address }}" maxlength="255"
                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.area') }}</label>
                            <input type="text" name="area" id="edit-wh-area" value="{{ $warehouse->address?->area }}" maxlength="255"
                                class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.building') }}</label>
                            <input type="text" name="building" id="edit-wh-building" value="{{ $warehouse->address?->building }}" maxlength="100"
                                class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.warehouses.storage_capacity_m3') }}</label>
                        <input type="number" name="total_capacity_m3" id="edit-wh-capacity" value="{{ $warehouse->total_capacity_m3 }}" min="0" step="0.01"
                            class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div id="edit-wh-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700"></div>
                    <div class="flex justify-between">
                        <button type="button" id="deactivate-btn"
                            class="{{ $warehouse->is_active ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700' }} text-sm font-medium">
                            {{ $warehouse->is_active ? __('partner.warehouses.deactivate_warehouse') : __('partner.warehouses.reactivate_warehouse') }}
                        </button>
                        <div class="flex gap-3">
                            <button type="button" onclick="document.getElementById('edit-warehouse-modal').classList.add('hidden');document.getElementById('edit-warehouse-modal').classList.remove('flex');"
                                class="px-4 py-2 border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50">
                                {{ __('common.cancel') }}
                            </button>
                            <button type="submit" id="save-warehouse-btn"
                                class="px-4 py-2 bg-primary-600 text-white text-sm font-semibold rounded-xl hover:bg-primary-700 disabled:opacity-50 transition-colors">
                                {{ __('partner.warehouses.save_changes') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($warehouse->type === \App\Enums\WarehouseType::SellerOwned)
        <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="text-base font-semibold text-gray-900">{{ __('partner.warehouses.exceptional_zones_title') }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ __('partner.warehouses.exceptional_zones_hint') }}</p>

            @if($exceptionalRoutes->isEmpty())
                <p class="text-sm text-gray-500 mt-4">{{ __('partner.warehouses.no_gap_zones') }}</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-start text-xs font-medium text-gray-500 border-b border-gray-200">
                                <th class="pb-3 pr-4">{{ __('partner.warehouses.zone_column') }}</th>
                                <th class="pb-3 pr-4">{{ __('partner.warehouses.subsidy_rules_column') }}</th>
                                <th class="pb-3 pr-4 text-center">{{ __('partner.warehouses.opted_in_column') }}</th>
                                <th class="pb-3 text-end">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($exceptionalRoutes as $route)
                                <tr class="border-b border-gray-100" data-exceptional-zone-row data-zone-id="{{ $route['zone']->id }}">
                                    <td class="py-3 pr-4 text-gray-800">{{ $route['zone']->name }}</td>
                                    <td class="py-3 pr-4 text-xs text-gray-500">
                                        @forelse($route['subsidy_rules'] as $rule)
                                            <div>{{ $rule->shippingMethod?->name ?? '—' }}:
                                                @if($rule->split_type === 'fixed')
                                                    {{ $rule->vendor_fixed_amount }}/{{ $rule->admin_fixed_amount }}
                                                @else
                                                    {{ $rule->vendor_share_pct }}%/{{ 100 - $rule->vendor_share_pct }}%
                                                @endif
                                            </div>
                                        @empty
                                            <span class="text-gray-400">{{ __('partner.warehouses.no_subsidy_rule') }}</span>
                                        @endforelse
                                    </td>
                                    <td class="py-3 pr-4 text-center">
                                        <span data-exceptional-zone-status
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $route['is_opted_in'] ? 'bg-success-50 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $route['is_opted_in'] ? __('common.active') : __('common.inactive') }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-end">
                                        <button type="button" data-exceptional-zone-toggle
                                            data-url="{{ route('partner.warehouses.exceptional-zones.toggle', [$warehouse->id, $route['zone']->id]) }}"
                                            data-active="{{ $route['is_opted_in'] ? 1 : 0 }}"
                                            class="px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                            {{ $route['is_opted_in'] ? __('partner.warehouses.opt_out') : __('partner.warehouses.opt_in') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <script>
            document.addEventListener('click', async function (e) {
                const btn = e.target.closest('[data-exceptional-zone-toggle]');
                if (!btn) return;

                btn.disabled = true;
                try {
                    const res = await fetch(btn.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Failed to toggle.');

                    const row = btn.closest('[data-exceptional-zone-row]');
                    const status = row.querySelector('[data-exceptional-zone-status]');
                    const isActive = !!json.is_active;

                    status.textContent = isActive ? '{{ __('common.active') }}' : '{{ __('common.inactive') }}';
                    status.className = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' +
                        (isActive ? 'bg-success-50 text-success-700' : 'bg-gray-100 text-gray-500');

                    btn.textContent = isActive ? '{{ __('partner.warehouses.opt_out') }}' : '{{ __('partner.warehouses.opt_in') }}';
                    btn.dataset.active = isActive ? '1' : '0';
                } catch (err) {
                    alert(err.message || 'Failed to toggle.');
                } finally {
                    btn.disabled = false;
                }
            });
        </script>
    @endif

@endsection
