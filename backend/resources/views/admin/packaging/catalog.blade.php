@extends('layouts.admin')

@section('title', __('admin.packaging_catalog.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.packaging_catalog.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.packaging_catalog.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.packaging.requests') }}" class="btn btn-secondary text-sm">{{ __('admin.packaging_catalog.view_orders') }}</a>
            <button type="button" id="btn-new-item" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('admin.packaging_catalog.add_item') }}
            </button>
        </div>
    </div>

    {{-- DataTable --}}
    @php
        $columns = [
            ['title' => '', 'data' => 'image', 'name' => 'image', 'orderable' => false, 'searchable' => false],
            ['title' => __('admin.packaging_catalog.col_name'), 'data' => 'name', 'name' => 'name_en'],
            ['title' => __('admin.packaging_catalog.col_type'), 'data' => 'type', 'name' => 'type', 'searchable' => false],
            ['title' => __('admin.packaging_catalog.col_size'), 'data' => 'size', 'name' => 'size', 'searchable' => false],
            ['title' => __('admin.packaging_catalog.col_unit_cost'), 'data' => 'unit_cost', 'name' => 'unit_cost', 'searchable' => false],
            ['title' => __('admin.packaging_catalog.col_stock'), 'data' => 'stock', 'name' => 'stock', 'searchable' => false],
            ['title' => __('admin.packaging_catalog.col_active'), 'data' => 'status', 'name' => 'status', 'searchable' => false],
            ['title' => __('admin.packaging_catalog.col_countries'), 'data' => 'countries', 'name' => 'countries', 'orderable' => false, 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];

        $filters = [
            [
                'type' => 'select',
                'name' => 'type',
                'label' => __('admin.packaging_catalog.filter_type'),
                'options' => [
                    'box' => __('admin.packaging_catalog.type_box'), 'bag' => __('admin.packaging_catalog.type_bag'), 'tape' => __('admin.packaging_catalog.type_tape'),
                    'label' => __('admin.packaging_catalog.type_label'), 'bubble_wrap' => __('admin.packaging_catalog.type_bubble_wrap'), 'other' => __('admin.packaging_catalog.type_other'),
                ],
            ],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('admin.packaging_catalog.filter_active'),
                'options' => ['1' => __('admin.packaging_catalog.status_active'), '0' => __('admin.packaging_catalog.status_inactive')],
            ],
        ];
    @endphp

    <x-table.datatable id="packaging-catalog-table" url="{{ route('admin.packaging.catalog.datatable') }}"
        :columns="$columns" :filters="$filters" :page-length="25" />
</div>

{{-- Add / Edit Modal --}}
<x-modal id="item-modal" title="{{ __('admin.packaging_catalog.add_item_title') }}" size="lg">
    <form id="item-form" class="space-y-4" enctype="multipart/form-data">
        @csrf
        <input type="hidden" id="f-id" value="">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_catalog.name_english') }} <span class="text-red-500">*</span></label>
                <input type="text" id="f-name-en" required maxlength="255" class="form-input w-full text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_catalog.name_arabic') }} <span class="text-red-500">*</span></label>
                <input type="text" id="f-name-ar" required maxlength="255" dir="rtl" class="form-input w-full text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_catalog.type_label_field') }} <span class="text-red-500">*</span></label>
                <select id="f-type" required class="form-select w-full text-sm">
                    <option value="box">{{ __('admin.packaging_catalog.type_box') }}</option>
                    <option value="bag">{{ __('admin.packaging_catalog.type_bag') }}</option>
                    <option value="tape">{{ __('admin.packaging_catalog.type_tape') }}</option>
                    <option value="label">{{ __('admin.packaging_catalog.type_label') }}</option>
                    <option value="bubble_wrap">{{ __('admin.packaging_catalog.type_bubble_wrap') }}</option>
                    <option value="other">{{ __('admin.packaging_catalog.type_other') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_catalog.size_field') }}</label>
                <input type="text" id="f-size" maxlength="100" class="form-input w-full text-sm" placeholder="{{ __('admin.packaging_catalog.size_placeholder') }}">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_catalog.description_english') }}</label>
                <textarea id="f-description-en" rows="2" class="form-input w-full text-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_catalog.description_arabic') }}</label>
                <textarea id="f-description-ar" rows="2" dir="rtl" class="form-input w-full text-sm"></textarea>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_catalog.sort_order') }}</label>
                <input type="number" id="f-sort-order" value="0" min="0" class="form-input w-full text-sm">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer select-none mb-2">
                    <input type="checkbox" id="f-is-active" checked class="rounded text-primary-600">
                    <span class="text-sm text-gray-700">{{ __('admin.packaging_catalog.active') }}</span>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_catalog.image') }}</label>
            <input type="file" id="f-image" accept="image/jpeg,image/png,image/jpg,image/webp" class="form-input w-full text-sm">
            <img id="f-image-preview" src="" class="hidden mt-2 w-20 h-20 object-cover rounded-lg border border-gray-200">
        </div>

        {{-- Per-country pricing --}}
        <div class="border-t border-gray-200 pt-4">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-xs font-medium text-gray-700">{{ __('admin.packaging_catalog.country_pricing') }} <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-2">
                    <select id="f-add-country" class="form-select text-sm">
                        <option value="">{{ __('admin.packaging_catalog.add_country') }}</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}"
                                    data-name="{{ $country->name_en }}"
                                    data-currency="{{ $country->currency_code }}">{{ $country->name_en }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="btn-add-country-row" class="btn btn-secondary text-xs">{{ __('admin.packaging_catalog.add_country') }}</button>
                </div>
            </div>
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">{{ __('admin.packaging_catalog.col_country') }}</th>
                            <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">{{ __('admin.packaging_catalog.col_currency') }}</th>
                            <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">{{ __('admin.packaging_catalog.col_unit_cost') }}</th>
                            <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">{{ __('admin.packaging_catalog.col_stock') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">{{ __('admin.packaging_catalog.col_active_short') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="country-pricing-rows"></tbody>
                </table>
                <p id="no-countries-msg" class="hidden text-center text-xs text-gray-400 py-4">{{ __('admin.packaging_catalog.no_countries_yet') }}</p>
            </div>
        </div>

        <div id="form-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    </form>

    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('admin.packaging_catalog.cancel') }}</button>
        <button type="button" id="btn-save-item" class="btn btn-primary">{{ __('admin.packaging_catalog.save') }}</button>
    </x-slot:footer>
</x-modal>

{{-- Delete confirmation modal --}}
<x-modal id="delete-item-modal" title="{{ __('admin.packaging_catalog.delete_item_title') }}" size="sm">
    <p class="text-sm text-gray-700">{{ __('admin.packaging_catalog.delete_confirm') }} "<strong id="delete-item-name"></strong>"?</p>
    <input type="hidden" id="delete-item-id">
    <div id="delete-item-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('admin.packaging_catalog.cancel') }}</button>
        <button type="button" id="btn-confirm-delete-item" class="btn btn-danger">{{ __('admin.packaging_catalog.delete') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const storeUrl = '{{ route('admin.packaging.catalog.store') }}';
    const catalogBaseUrl = '{{ url('packaging/catalog') }}';

    const i18n = @json(__('admin.packaging_catalog'));
    const countryMeta = @json($countries->mapWithKeys(fn ($c) => [$c->id => ['name' => $c->name_en, 'currency' => $c->currency_code]]));

    let countryRows = [];

    function openModal(id) { window.jQuery ? window.jQuery('#' + id).modal('open') : document.getElementById(id)?.classList.remove('hidden'); }
    function closeModal(id) { window.jQuery ? window.jQuery('#' + id).modal('close') : document.getElementById(id)?.classList.add('hidden'); }

    function renderCountryRows() {
        const tbody = document.getElementById('country-pricing-rows');
        const emptyMsg = document.getElementById('no-countries-msg');
        tbody.innerHTML = '';

        if (countryRows.length === 0) {
            emptyMsg.classList.remove('hidden');
            return;
        }
        emptyMsg.classList.add('hidden');

        countryRows.forEach((row, idx) => {
            const meta = countryMeta[row.country_id] ?? { name: row.country_id, currency: '' };
            const tr = document.createElement('tr');
            tr.className = 'border-t border-gray-100';
            tr.innerHTML = `
                <td class="px-3 py-2 text-sm text-gray-800">${meta.name}</td>
                <td class="px-3 py-2 text-sm text-gray-500">${meta.currency ?? ''}</td>
                <td class="px-3 py-2"><input type="number" min="0" class="form-input w-24 text-sm cp-unit-cost" value="${row.unit_cost ?? 0}"></td>
                <td class="px-3 py-2"><input type="number" min="0" class="form-input w-24 text-sm cp-stock" placeholder="${i18n.unlimited}" value="${row.stock_available ?? ''}"></td>
                <td class="px-3 py-2 text-center"><input type="checkbox" class="rounded text-primary-600 cp-active" ${row.is_active ? 'checked' : ''}></td>
                <td class="px-3 py-2 text-end"><button type="button" class="text-red-500 hover:underline text-xs cp-remove">&times;</button></td>
            `;
            tr.querySelector('.cp-unit-cost').addEventListener('change', (e) => { row.unit_cost = parseInt(e.target.value) || 0; });
            tr.querySelector('.cp-stock').addEventListener('change', (e) => { row.stock_available = e.target.value === '' ? null : (parseInt(e.target.value) || 0); });
            tr.querySelector('.cp-active').addEventListener('change', (e) => { row.is_active = e.target.checked; });
            tr.querySelector('.cp-remove').addEventListener('click', () => {
                countryRows.splice(idx, 1);
                renderCountryRows();
            });
            tbody.appendChild(tr);
        });
    }

    document.getElementById('btn-add-country-row').addEventListener('click', () => {
        const select = document.getElementById('f-add-country');
        const countryId = select.value;
        if (!countryId) return;
        if (countryRows.some(r => r.country_id === countryId)) {
            select.value = '';
            return;
        }
        countryRows.push({ country_id: countryId, unit_cost: 0, stock_available: null, is_active: true });
        select.value = '';
        renderCountryRows();
    });

    function resetForm() {
        document.getElementById('f-id').value = '';
        ['f-name-en', 'f-name-ar', 'f-size', 'f-description-en', 'f-description-ar', 'f-image'].forEach(id => {
            document.getElementById(id).value = '';
        });
        document.getElementById('f-type').value = 'box';
        document.getElementById('f-sort-order').value = '0';
        document.getElementById('f-is-active').checked = true;
        document.getElementById('f-image-preview').classList.add('hidden');
        document.getElementById('form-error').classList.add('hidden');
        countryRows = [];
        renderCountryRows();
    }

    document.getElementById('f-image').addEventListener('change', function () {
        const file = this.files[0];
        const preview = document.getElementById('f-image-preview');
        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
        }
    });

    document.getElementById('btn-new-item').addEventListener('click', () => {
        resetForm();
        document.querySelector('#item-modal [id$="-title"]').textContent = i18n.add_item_title;
        openModal('item-modal');
    });

    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.js-edit-item');
        if (editBtn) {
            const item = JSON.parse(editBtn.dataset.item);
            resetForm();
            document.getElementById('f-id').value = item.id;
            document.getElementById('f-name-en').value = item.name_en ?? '';
            document.getElementById('f-name-ar').value = item.name_ar ?? '';
            document.getElementById('f-type').value = item.type ?? 'box';
            document.getElementById('f-size').value = item.size ?? '';
            document.getElementById('f-description-en').value = item.description_en ?? '';
            document.getElementById('f-description-ar').value = item.description_ar ?? '';
            document.getElementById('f-sort-order').value = item.sort_order ?? 0;
            document.getElementById('f-is-active').checked = !!item.is_active;
            if (item.image_path) {
                const preview = document.getElementById('f-image-preview');
                preview.src = '/storage/' + item.image_path;
                preview.classList.remove('hidden');
            }
            document.querySelector('#item-modal [id$="-title"]').textContent = i18n.edit_item_title;
            openModal('item-modal');

            fetch(`${catalogBaseUrl}/${item.id}`, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.data.country_pricing)) {
                        countryRows = data.data.country_pricing.map(r => ({
                            country_id: r.country_id,
                            unit_cost: r.unit_cost ?? 0,
                            stock_available: r.stock_available,
                            is_active: !!r.is_active,
                        }));
                        renderCountryRows();
                    }
                })
                .catch(() => {});
        }

        const deleteBtn = e.target.closest('.js-delete-item');
        if (deleteBtn) {
            document.getElementById('delete-item-name').textContent = deleteBtn.dataset.name;
            document.getElementById('delete-item-id').value = deleteBtn.dataset.id;
            document.getElementById('delete-item-error').classList.add('hidden');
            openModal('delete-item-modal');
        }

        const toggleBtn = e.target.closest('.js-toggle-active');
        if (toggleBtn) {
            const id = toggleBtn.dataset.id;
            fetch(`{{ url('packaging/catalog') }}/${id}/toggle`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    const active = data.is_active;
                    toggleBtn.classList.toggle('bg-emerald-500', active);
                    toggleBtn.classList.toggle('bg-gray-200', !active);
                    const dot = toggleBtn.querySelector('span');
                    dot.classList.toggle('translate-x-4', active);
                    dot.classList.toggle('translate-x-0.5', !active);
                }
            });
        }
    });

    document.getElementById('btn-save-item').addEventListener('click', async () => {
        const id = document.getElementById('f-id').value;
        const errEl = document.getElementById('form-error');

        if (countryRows.length === 0) {
            errEl.textContent = i18n.no_countries_yet;
            errEl.classList.remove('hidden');
            return;
        }

        const fd = new FormData();
        fd.append('name_en', document.getElementById('f-name-en').value);
        fd.append('name_ar', document.getElementById('f-name-ar').value);
        fd.append('type', document.getElementById('f-type').value);
        fd.append('size', document.getElementById('f-size').value);
        fd.append('description_en', document.getElementById('f-description-en').value);
        fd.append('description_ar', document.getElementById('f-description-ar').value);
        fd.append('sort_order', document.getElementById('f-sort-order').value || '0');
        fd.append('is_active', document.getElementById('f-is-active').checked ? '1' : '0');
        const imageFile = document.getElementById('f-image').files[0];
        if (imageFile) fd.append('image', imageFile);

        countryRows.forEach((row, i) => {
            fd.append(`country_pricing[${i}][country_id]`, row.country_id);
            fd.append(`country_pricing[${i}][unit_cost]`, row.unit_cost ?? 0);
            if (row.stock_available !== null && row.stock_available !== undefined && row.stock_available !== '') {
                fd.append(`country_pricing[${i}][stock_available]`, row.stock_available);
            }
            fd.append(`country_pricing[${i}][is_active]`, row.is_active ? '1' : '0');
        });

        let url = storeUrl;
        if (id) {
            fd.append('_method', 'PUT');
            url = `{{ url('packaging/catalog') }}/${id}`;
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await res.json();
            if (res.ok && data.success !== false) {
                closeModal('item-modal');
                window.reloadDataTable && window.reloadDataTable('packaging-catalog-table');
                window.Toast && window.Toast.success(data.message || i18n.saved);
            } else {
                errEl.textContent = data.message ?? Object.values(data.errors ?? {}).flat().join(' ');
                errEl.classList.remove('hidden');
            }
        } catch (e) {
            errEl.textContent = i18n.something_wrong;
            errEl.classList.remove('hidden');
        }
    });

    document.getElementById('btn-confirm-delete-item').addEventListener('click', async () => {
        const id = document.getElementById('delete-item-id').value;
        try {
            const res = await fetch(`{{ url('packaging/catalog') }}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (res.ok && data.success !== false) {
                closeModal('delete-item-modal');
                window.reloadDataTable && window.reloadDataTable('packaging-catalog-table');
                window.Toast && window.Toast.success(data.message || i18n.deleted);
            } else {
                const errEl = document.getElementById('delete-item-error');
                errEl.textContent = data.message ?? i18n.could_not_delete;
                errEl.classList.remove('hidden');
            }
        } catch (e) {
            const errEl = document.getElementById('delete-item-error');
            errEl.textContent = i18n.something_wrong;
            errEl.classList.remove('hidden');
        }
    });
})();
</script>
@endpush
@endsection
