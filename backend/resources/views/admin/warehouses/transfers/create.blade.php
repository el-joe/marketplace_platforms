@extends('layouts.admin')

@section('title', __('admin.warehouses_section.new_inventory_transfer'))

@section('content')

    {{-- ─── Breadcrumb ──────────────────────────────────────────────────────── --}}
    <nav class="mb-5 text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.warehouses.index') }}" class="hover:text-primary-600">{{ __('admin.warehouses_section.title') }}</a>
        <span>/</span>
        <a href="{{ route('admin.warehouses.transfers.index') }}" class="hover:text-primary-600">{{ __('admin.warehouses_section.transfers') }}</a>
        <span>/</span>
        <span class="text-gray-800 font-medium">{{ __('admin.warehouses_section.new_transfer') }}</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.warehouses_section.new_inventory_transfer') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.warehouses_section.move_stock_desc') }}</p>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
            @foreach($errors->all() as $e) <p>{{ $e }}</p> @endforeach
        </div>
    @endif

    <form action="{{ route('admin.warehouses.transfers.store') }}" method="POST">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- ─── Transfer Details ─────────────────────────────────────── --}}
            <div class="lg:col-span-2 space-y-5">
                <x-card>
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.warehouses_section.transfer_details') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.warehouses_section.source_warehouse') }} <span class="text-red-500">*</span>
                            </label>
                            <select name="source_warehouse_id" class="form-input w-full text-sm" required>
                                <option value="">{{ __('admin.warehouses_section.select_source') }}</option>
                                @foreach($warehouses as $id => $name)
                                    <option value="{{ $id }}" {{ old('source_warehouse_id') == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_warehouse_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.warehouses_section.destination_warehouse') }} <span class="text-red-500">*</span>
                            </label>
                            <select name="destination_warehouse_id" class="form-input w-full text-sm" required>
                                <option value="">{{ __('admin.warehouses_section.select_destination') }}</option>
                                @foreach($warehouses as $id => $name)
                                    <option value="{{ $id }}" {{ old('destination_warehouse_id') == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('destination_warehouse_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.vendor_optional') }}</label>
                            <select name="vendor_id" class="form-input w-full text-sm">
                                <option value="">{{ __('admin.warehouses_section.any_vendor') }}</option>
                                @foreach($vendors as $id => $name)
                                    <option value="{{ $id }}" {{ old('vendor_id') == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.expected_arrival') }}</label>
                            <input type="date" name="expected_arrival_date" class="form-input w-full text-sm"
                                value="{{ old('expected_arrival_date') }}" min="{{ now()->addDay()->format('Y-m-d') }}">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.warehouses_section.notes') }}</label>
                            <textarea name="notes" rows="3" class="form-input w-full text-sm"
                                placeholder="{{ __('admin.warehouses_section.notes_placeholder') }}">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </x-card>

                {{-- ─── Line Items ────────────────────────────────────────── --}}
                <x-card>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.warehouses_section.items') }}</h3>
                        <button type="button" id="add-item-row" class="btn btn-secondary btn-xs">{{ __('admin.warehouses_section.add_item') }}</button>
                    </div>

                    <div id="items-container" class="space-y-3">
                        @php $oldItems = old('items', [[]]); @endphp
                        @foreach($oldItems as $i => $oldItem)
                            <div class="flex items-start gap-3 item-row">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.warehouses_section.vendor_listing_id_sku') }}</label>
                                    <input type="text" name="items[{{ $i }}][vendor_listing_id]"
                                        class="form-input w-full text-sm" placeholder="{{ __('admin.warehouses_section.vendor_listing_id_placeholder') }}"
                                        value="{{ $oldItem['vendor_listing_id'] ?? '' }}" required>
                                </div>
                                <div class="w-32">
                                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.warehouses_section.qty_requested') }}</label>
                                    <input type="number" name="items[{{ $i }}][quantity_requested]"
                                        class="form-input w-full text-sm" placeholder="0" min="1"
                                        value="{{ $oldItem['quantity_requested'] ?? '' }}" required>
                                </div>
                                @if($i > 0)
                                    <button type="button"
                                        class="mt-5 text-red-400 hover:text-red-600 remove-item-row">&times;</button>
                                @else
                                    <div class="w-5 mt-5"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @error('items')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </x-card>
            </div>

            {{-- ─── Sidebar Actions ──────────────────────────────────────── --}}
            <div class="space-y-5">
                <x-card>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">{{ __('admin.warehouses_section.actions') }}</h3>
                    <div class="space-y-2">
                        <button type="submit" class="btn btn-primary w-full">{{ __('admin.warehouses_section.create_transfer') }}</button>
                        <a href="{{ route('admin.warehouses.transfers.index') }}"
                            class="btn btn-ghost w-full text-gray-500">{{ __('common.cancel') }}</a>
                    </div>
                </x-card>
            </div>

        </div>
    </form>

@endsection

@push('scripts')
    <script type="module">
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            vendorListingIdSku: @json(__('admin.warehouses_section.vendor_listing_id_sku')),
            vendorListingIdPlaceholder: @json(__('admin.warehouses_section.vendor_listing_id_placeholder')),
            qtyRequested: @json(__('admin.warehouses_section.qty_requested')),
        });

        document.getElementById('add-item-row').addEventListener('click', function () {
            const container = document.getElementById('items-container');
            const idx = container.querySelectorAll('.item-row').length;

            const row = document.createElement('div');
            row.className = 'flex items-start gap-3 item-row';
            row.innerHTML = `
            <div class="flex-1">
                <label class="block text-xs text-gray-500 mb-1">${window.TRANSLATIONS.vendorListingIdSku}</label>
                <input type="text" name="items[${idx}][vendor_listing_id]"
                    class="form-input w-full text-sm" placeholder="${window.TRANSLATIONS.vendorListingIdPlaceholder}" required>
            </div>
            <div class="w-32">
                <label class="block text-xs text-gray-500 mb-1">${window.TRANSLATIONS.qtyRequested}</label>
                <input type="number" name="items[${idx}][quantity_requested]"
                    class="form-input w-full text-sm" placeholder="0" min="1" required>
            </div>
            <button type="button" class="mt-5 text-red-400 hover:text-red-600 remove-item-row">&times;</button>
        `;
            container.appendChild(row);
        });

        document.getElementById('items-container').addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-item-row')) {
                e.target.closest('.item-row').remove();
            }
        });
    </script>
@endpush
