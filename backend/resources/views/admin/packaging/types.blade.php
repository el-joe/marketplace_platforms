@extends('layouts.admin')

@section('title', __('admin.packaging_types.title'))

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.packaging_types.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.packaging_types.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.packaging.catalog') }}" class="btn btn-secondary text-sm">{{ __('admin.packaging_types.back_to_catalog') }}</a>
            <button type="button" id="btn-new-type" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('admin.packaging_types.add') }}
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="table-responsive">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ __('admin.packaging_types.col_name_en') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('admin.packaging_types.col_name_ar') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('admin.packaging_types.col_code') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('admin.packaging_types.col_items') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('admin.packaging_types.col_sort') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('admin.packaging_types.col_active') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($types as $type)
                        <tr data-type-row="{{ $type->id }}">
                            <td class="px-4 py-3 text-gray-900">{{ $type->name_en }}</td>
                            <td class="px-4 py-3 text-gray-900" dir="rtl">{{ $type->name_ar }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500" dir="ltr">{{ $type->code }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ number_format($type->supplies_count) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $type->sort_order }}</td>
                            <td class="px-4 py-3">
                                <button type="button" class="js-toggle-type relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $type->is_active ? 'bg-emerald-500' : 'bg-gray-200' }}" data-id="{{ $type->id }}">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform {{ $type->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="js-edit-type text-primary-600 hover:underline text-xs font-medium"
                                        data-type='{{ json_encode($type->only(['id', 'code', 'name_en', 'name_ar', 'is_active', 'sort_order'])) }}'>{{ __('admin.packaging_types.edit') }}</button>
                                    @if($type->supplies_count === 0)
                                        <button type="button" class="js-delete-type text-red-500 hover:underline text-xs font-medium" data-id="{{ $type->id }}">{{ __('admin.packaging_types.delete') }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">{{ __('admin.packaging_types.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-modal id="type-modal" title="{{ __('admin.packaging_types.add_title') }}" size="md">
    <form id="type-form" class="space-y-4">
        <input type="hidden" id="t-id" value="">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_types.name_en') }} <span class="text-red-500">*</span></label>
                <input type="text" id="t-name-en" required maxlength="100" class="form-input w-full text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_types.name_ar') }} <span class="text-red-500">*</span></label>
                <input type="text" id="t-name-ar" required maxlength="100" dir="rtl" class="form-input w-full text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_types.code') }}</label>
            <input type="text" id="t-code" maxlength="50" dir="ltr" pattern="[a-z0-9_]+" class="form-input w-full text-sm font-mono">
            <p class="mt-1 text-xs text-gray-400">{{ __('admin.packaging_types.code_hint') }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.packaging_types.sort_order') }}</label>
                <input type="number" id="t-sort-order" min="0" class="form-input w-full text-sm">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer select-none mb-2">
                    <input type="checkbox" id="t-is-active" checked class="rounded text-primary-600">
                    <span class="text-sm text-gray-700">{{ __('admin.packaging_types.active') }}</span>
                </label>
            </div>
        </div>
        <div id="type-form-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    </form>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('admin.packaging_types.cancel') }}</button>
        <button type="button" id="btn-save-type" class="btn btn-primary">{{ __('admin.packaging_types.save') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const storeUrl = '{{ route('admin.packaging.types.store') }}';
    const baseUrl = '{{ url('packaging/types') }}';
    const i18n = @json(__('admin.packaging_types'));

    function openModal(id) { window.jQuery ? window.jQuery('#' + id).modal('open') : document.getElementById(id)?.classList.remove('hidden'); }

    function fillForm(type) {
        document.getElementById('t-id').value = type?.id ?? '';
        document.getElementById('t-name-en').value = type?.name_en ?? '';
        document.getElementById('t-name-ar').value = type?.name_ar ?? '';
        const code = document.getElementById('t-code');
        code.value = type?.code ?? '';
        code.disabled = !!type;
        document.getElementById('t-sort-order').value = type?.sort_order ?? '';
        document.getElementById('t-is-active').checked = type ? !!type.is_active : true;
        document.getElementById('type-form-error').classList.add('hidden');
        document.querySelector('#type-modal [id$="-title"]').textContent = type ? i18n.edit_title : i18n.add_title;
        openModal('type-modal');
    }

    async function send(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        let data = {};
        try { data = await res.json(); } catch (e) {}
        return { ok: res.ok && data.success !== false, data };
    }

    function errorText(data) {
        return data.message ?? Object.values(data.errors ?? {}).flat().join(' ') ?? i18n.something_wrong;
    }

    document.getElementById('btn-new-type').addEventListener('click', () => fillForm(null));

    document.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.js-edit-type');
        if (editBtn) {
            fillForm(JSON.parse(editBtn.dataset.type));
            return;
        }

        const toggleBtn = e.target.closest('.js-toggle-type');
        if (toggleBtn) {
            const { ok, data } = await send(`${baseUrl}/${toggleBtn.dataset.id}/toggle`, 'PATCH');
            if (ok) {
                toggleBtn.classList.toggle('bg-emerald-500', data.is_active);
                toggleBtn.classList.toggle('bg-gray-200', !data.is_active);
                const dot = toggleBtn.querySelector('span');
                dot.classList.toggle('translate-x-4', data.is_active);
                dot.classList.toggle('translate-x-0.5', !data.is_active);
            } else {
                window.Toast && window.Toast.error(errorText(data));
            }
            return;
        }

        const deleteBtn = e.target.closest('.js-delete-type');
        if (deleteBtn && window.confirm(i18n.delete_confirm)) {
            const { ok, data } = await send(`${baseUrl}/${deleteBtn.dataset.id}`, 'DELETE');
            if (ok) {
                window.location.reload();
            } else {
                window.Toast && window.Toast.error(errorText(data));
            }
        }
    });

    document.getElementById('btn-save-type').addEventListener('click', async () => {
        const id = document.getElementById('t-id').value;
        const sortOrder = document.getElementById('t-sort-order').value;
        const body = {
            name_en: document.getElementById('t-name-en').value,
            name_ar: document.getElementById('t-name-ar').value,
            is_active: document.getElementById('t-is-active').checked,
        };
        if (sortOrder !== '') body.sort_order = parseInt(sortOrder, 10);
        if (!id) {
            const code = document.getElementById('t-code').value.trim();
            if (code !== '') body.code = code;
        }

        const { ok, data } = id ? await send(`${baseUrl}/${id}`, 'PUT', body) : await send(storeUrl, 'POST', body);
        if (ok) {
            window.location.reload();
        } else {
            const errEl = document.getElementById('type-form-error');
            errEl.textContent = errorText(data);
            errEl.classList.remove('hidden');
        }
    });
})();
</script>
@endpush
@endsection
