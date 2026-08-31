@extends('layouts.admin')

@section('title', __('admin.travel.travel_categories'))

@section('content')
<div class="p-6 space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900">{{ __('admin.travel.travel_categories') }}</h1>
        <button onclick="openAddModal(null)"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" /> {{ __('admin.travel.add_category') }}
        </button>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Tree table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700 w-8"></th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.name_en') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.name_ar') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.slug') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.travel.packages') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('common.status') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.travel.sort_order') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($categories as $category)
                        {{-- Parent row --}}
                        <tr class="hover:bg-gray-50 bg-gray-50/40" id="cat-row-{{ $category->id }}">
                            <td class="px-4 py-3 text-lg text-center">{{ $category->icon ?? '📁' }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $category->name_en }}</td>
                            <td class="px-4 py-3 text-gray-600" dir="rtl">{{ $category->name_ar }}</td>
                            <td class="px-4 py-3 text-gray-400 font-mono text-xs">{{ $category->slug }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ number_format($category->packages_count) }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($category->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('common.active') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">{{ __('admin.hidden') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ $category->sort_order }}</td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button onclick="openAddModal('{{ $category->id }}')"
                                        class="text-xs text-primary-600 hover:underline mr-3">+ {{ __('admin.travel.add_child') }}</button>
                                <button onclick="openEditModal({{ json_encode($category) }})"
                                        class="text-xs text-primary-600 hover:underline mr-3">{{ __('common.edit') }}</button>
                                <button onclick="deleteCategory('{{ $category->id }}', '{{ addslashes($category->name_en) }}')"
                                        class="text-xs text-red-600 hover:underline">{{ __('common.delete') }}</button>
                            </td>
                        </tr>

                        {{-- Children rows --}}
                        @foreach($category->children as $child)
                        <tr class="hover:bg-gray-50" id="cat-row-{{ $child->id }}">
                            <td class="px-4 py-3 text-center">
                                <span class="text-gray-300 text-xs pl-4">↳</span>
                                {{ $child->icon ?? '' }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 pl-8">{{ $child->name_en }}</td>
                            <td class="px-4 py-3 text-gray-600" dir="rtl">{{ $child->name_ar }}</td>
                            <td class="px-4 py-3 text-gray-400 font-mono text-xs">{{ $child->slug }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ number_format($child->packages_count) }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($child->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('common.active') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">{{ __('admin.hidden') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ $child->sort_order }}</td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button onclick="openEditModal({{ json_encode($child) }})"
                                        class="text-xs text-primary-600 hover:underline mr-3">{{ __('common.edit') }}</button>
                                <button onclick="deleteCategory('{{ $child->id }}', '{{ addslashes($child->name_en) }}')"
                                        class="text-xs text-red-600 hover:underline">{{ __('common.delete') }}</button>
                            </td>
                        </tr>
                        @endforeach

                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400">{{ __('admin.travel.no_categories_found') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add / Edit Modal --}}
<div id="cat-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4"
     onclick="if(event.target===this) closeModal()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 id="modal-title" class="font-semibold text-gray-900">{{ __('admin.travel.add_category_title') }}</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <x-heroicon name="x-mark" class="w-5 h-5" />
            </button>
        </div>
        <div class="px-6 py-4 space-y-4">
            <input type="hidden" id="cat-id">

            {{-- Parent --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.travel.parent_category') }}</label>
                <select id="f-parent" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">{{ __('admin.travel.top_level') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name_en }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.name_en') }} *</label>
                    <input id="f-name-en" dir="ltr" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Beach Holidays">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.name_ar') }} *</label>
                    <input id="f-name-ar" dir="rtl" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="عطلات شاطئية">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.travel.icon') }}</label>
                    <input id="f-icon" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="🏖️">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.travel.sort_order') }}</label>
                    <input id="f-sort" type="number" min="0" value="0" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input id="f-active" type="checkbox" checked class="rounded border-gray-300 text-primary-600">
                <span class="text-sm text-gray-700">{{ __('admin.travel.active_visible') }}</span>
            </label>

            <div id="modal-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700"></div>
        </div>
        <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100">
            <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
            <button onclick="saveCategory()" id="modal-save-btn"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 flex items-center gap-2">
                <span id="modal-save-label">{{ __('common.save') }}</span>
                <svg id="modal-spinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const STORE_URL  = '{{ route('admin.travel.categories.store') }}';
const UPDATE_URL = '{{ url('admin/travel/categories') }}';
const DELETE_URL = '{{ url('admin/travel/categories') }}';
const CSRF       = document.querySelector('meta[name=csrf-token]').content;

function openAddModal(parentId = null) {
    document.getElementById('cat-id').value      = '';
    document.getElementById('f-parent').value    = parentId ?? '';
    document.getElementById('f-name-en').value   = '';
    document.getElementById('f-name-ar').value   = '';
    document.getElementById('f-icon').value      = '';
    document.getElementById('f-sort').value      = '0';
    document.getElementById('f-active').checked  = true;
    document.getElementById('modal-title').textContent = '{{ __('admin.travel.add_category_title') }}';
    document.getElementById('modal-error').classList.add('hidden');
    document.getElementById('cat-modal').classList.remove('hidden');
    document.getElementById('f-name-en').focus();
}

function openEditModal(cat) {
    document.getElementById('cat-id').value      = cat.id;
    document.getElementById('f-parent').value    = cat.parent_id ?? '';
    document.getElementById('f-name-en').value   = cat.name_en;
    document.getElementById('f-name-ar').value   = cat.name_ar;
    document.getElementById('f-icon').value      = cat.icon ?? '';
    document.getElementById('f-sort').value      = cat.sort_order ?? 0;
    document.getElementById('f-active').checked  = !!cat.is_active;
    document.getElementById('modal-title').textContent = '{{ __('admin.travel.edit_category_title') }}';
    document.getElementById('modal-error').classList.add('hidden');
    document.getElementById('cat-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('cat-modal').classList.add('hidden');
}

async function saveCategory() {
    const id     = document.getElementById('cat-id').value;
    const isEdit = !!id;
    const btn    = document.getElementById('modal-save-btn');
    const label  = document.getElementById('modal-save-label');
    const spin   = document.getElementById('modal-spinner');
    const errEl  = document.getElementById('modal-error');

    const payload = {
        parent_id:  document.getElementById('f-parent').value || null,
        name_en:    document.getElementById('f-name-en').value.trim(),
        name_ar:    document.getElementById('f-name-ar').value.trim(),
        icon:       document.getElementById('f-icon').value.trim() || null,
        sort_order: parseInt(document.getElementById('f-sort').value) || 0,
        is_active:  document.getElementById('f-active').checked,
    };

    if (!payload.name_en || !payload.name_ar) {
        errEl.textContent = '{{ __('admin.travel.category_name_required') }}';
        errEl.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    label.textContent = '{{ __('admin.travel.saving') }}';
    spin.classList.remove('hidden');
    errEl.classList.add('hidden');

    try {
        const url    = isEdit ? `${UPDATE_URL}/${id}` : STORE_URL;
        const method = isEdit ? 'PUT' : 'POST';

        const resp = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify(payload),
        });

        const data = await resp.json();

        if (!resp.ok) {
            const firstError = data.errors
                ? Object.values(data.errors).flat()[0]
                : (data.message ?? '{{ __('admin.travel.save_failed') }}');
            errEl.textContent = firstError;
            errEl.classList.remove('hidden');
            return;
        }

        closeModal();
        window.location.reload();

    } catch (e) {
        errEl.textContent = '{{ __('admin.travel.save_failed') }}';
        errEl.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        label.textContent = '{{ __('common.save') }}';
        spin.classList.add('hidden');
    }
}

async function deleteCategory(id, name) {
    if (!confirm(`{{ __('admin.travel.category_delete_confirm') }}`.replace(':name', name))) return;

    const resp = await fetch(`${DELETE_URL}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });

    const data = await resp.json();

    if (!resp.ok) {
        alert(data.message ?? '{{ __('admin.travel.delete_failed') }}');
        return;
    }

    document.getElementById(`cat-row-${id}`)?.remove();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});
</script>
@endpush
