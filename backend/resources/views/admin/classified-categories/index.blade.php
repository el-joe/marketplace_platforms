@extends('layouts.admin')

@section('title', __('admin.classifieds.category_tree_title'))

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" defer></script>
@endpush

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.classifieds.category_tree_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.classifieds.category_tree_desc') }}</p>
        </div>
        <button type="button" data-modal-open="category-modal" data-mode="create"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.classifieds.new_category') }}
        </button>
    </div>

    {{-- Tree table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-8 px-3 py-3"></th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.name') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.icon') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.contract_template') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.classifieds.map') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.classifieds.sketch') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.classifieds.attachments') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.classifieds.listings') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('common.active') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody id="category-tree" class="divide-y divide-gray-100">
                    @forelse($roots as $root)
                        {{-- Parent row --}}
                        <tr class="hover:bg-gray-50 bg-white category-row" data-id="{{ $root->id }}">
                            <td class="px-3 py-3 text-gray-300 cursor-grab drag-handle">
                                <x-heroicon name="bars-3" class="w-4 h-4" />
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-900">{{ $root->name_en }}</span>
                                <span class="block text-xs text-gray-400" dir="rtl">{{ $root->name_ar }}</span>
                                <span class="block text-xs text-gray-300 font-mono">{{ $root->slug }}</span>
                            </td>
                            <td class="px-4 py-3 text-lg">{{ $root->icon ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                {{ $root->contractTemplate?->name ?? '—' }}
                                @if($root->contractTemplate)
                                    <span class="text-gray-300">v{{ $root->contractTemplate->version }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($root->requires_location_map)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('common.yes') }}</span>
                                @else
                                    <span class="text-gray-300 text-xs">{{ __('common.no') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($root->requires_sketch_upload)
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">{{ __('common.yes') }}</span>
                                @else
                                    <span class="text-gray-300 text-xs">{{ __('common.no') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if(!empty($root->required_attachment_types))
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        {{ count($root->required_attachment_types) }}
                                    </span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500 text-xs">
                                {{ $root->active_listing_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" dir="ltr"
                                        class="btn-toggle-active relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $root->is_active ? 'bg-emerald-500' : 'bg-gray-200' }}"
                                        data-id="{{ $root->id }}" data-active="{{ $root->is_active ? '1' : '0' }}"
                                        data-has-children="{{ $root->children->isNotEmpty() ? '1' : '0' }}"
                                        aria-label="{{ __('admin.classifieds.toggle_active') }}">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform {{ $root->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button type="button"
                                        class="btn-edit-category text-xs text-primary-600 font-medium hover:underline"
                                        data-category="{{ json_encode(['id' => $root->id, 'name_en' => $root->name_en, 'name_ar' => $root->name_ar, 'slug' => $root->slug, 'icon' => $root->icon, 'parent_id' => $root->parent_id, 'requires_location_map' => $root->requires_location_map, 'requires_sketch_upload' => $root->requires_sketch_upload, 'contract_template_id' => $root->contract_template_id, 'required_attachment_types' => $root->required_attachment_types ?? [], 'is_active' => $root->is_active, 'sort_order' => $root->sort_order]) }}">
                                    {{ __('common.edit') }}
                                </button>
                                <button type="button"
                                        class="btn-delete-category ms-2 text-xs text-red-500 hover:underline"
                                        data-id="{{ $root->id }}" data-name="{{ $root->name_en }}">
                                    {{ __('common.delete') }}
                                </button>
                            </td>
                        </tr>
                        {{-- Children rows --}}
                        @foreach($root->children->sortBy('sort_order') as $child)
                        <tr class="hover:bg-gray-50 bg-gray-50/40 category-row" data-id="{{ $child->id }}">
                            <td class="px-3 py-3 text-gray-200"></td>
                            <td class="px-4 py-3 ps-10">
                                <span class="text-gray-400 me-1">↳</span>
                                <span class="font-medium text-gray-800">{{ $child->name_en }}</span>
                                <span class="block text-xs text-gray-400 ps-4" dir="rtl">{{ $child->name_ar }}</span>
                                <span class="block text-xs text-gray-300 font-mono ps-4">{{ $child->slug }}</span>
                            </td>
                            <td class="px-4 py-3 text-lg">{{ $child->icon ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                {{ $child->contractTemplate?->name ?? '—' }}
                                @if($child->contractTemplate)
                                    <span class="text-gray-300">v{{ $child->contractTemplate->version }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($child->requires_location_map)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('common.yes') }}</span>
                                @else
                                    <span class="text-gray-300 text-xs">{{ __('common.no') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($child->requires_sketch_upload)
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">{{ __('common.yes') }}</span>
                                @else
                                    <span class="text-gray-300 text-xs">{{ __('common.no') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if(!empty($child->required_attachment_types))
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        {{ count($child->required_attachment_types) }}
                                    </span>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500 text-xs">
                                {{ $child->active_listing_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" dir="ltr"
                                        class="btn-toggle-active relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $child->is_active ? 'bg-emerald-500' : 'bg-gray-200' }}"
                                        data-id="{{ $child->id }}" data-active="{{ $child->is_active ? '1' : '0' }}"
                                        data-has-children="0"
                                        aria-label="{{ __('admin.classifieds.toggle_active') }}">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform {{ $child->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button type="button"
                                        class="btn-edit-category text-xs text-primary-600 font-medium hover:underline"
                                        data-category="{{ json_encode(['id' => $child->id, 'name_en' => $child->name_en, 'name_ar' => $child->name_ar, 'slug' => $child->slug, 'icon' => $child->icon, 'parent_id' => $child->parent_id, 'requires_location_map' => $child->requires_location_map, 'requires_sketch_upload' => $child->requires_sketch_upload, 'contract_template_id' => $child->contract_template_id, 'required_attachment_types' => $child->required_attachment_types ?? [], 'is_active' => $child->is_active, 'sort_order' => $child->sort_order]) }}">
                                    {{ __('common.edit') }}
                                </button>
                                <button type="button"
                                        class="btn-delete-category ms-2 text-xs text-red-500 hover:underline"
                                        data-id="{{ $child->id }}" data-name="{{ $child->name_en }}">
                                    {{ __('common.delete') }}
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-gray-400">{{ __('admin.classifieds.no_categories_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Category Modal --}}
<x-modal id="category-modal" title="{{ __('admin.classifieds.category_modal_title') }}" size="lg">
    <form id="category-form" class="space-y-4">
        @csrf
        <input type="hidden" id="form-category-id" value="">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.name_en_label') }} <span class="text-red-500">*</span></label>
                <input type="text" name="name_en" id="f-name-en" required maxlength="150" dir="ltr"
                       class="form-input w-full text-sm" placeholder="{{ __('admin.classifieds.name_en_placeholder_example') }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.name_ar_label') }} <span class="text-red-500">*</span></label>
                <input type="text" name="name_ar" id="f-name-ar" required maxlength="150" dir="rtl"
                       class="form-input w-full text-sm" placeholder="{{ __('admin.classifieds.name_ar_placeholder_example') }}">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.slug_field') }}</label>
                <input type="text" name="slug" id="f-slug" maxlength="150"
                       class="form-input w-full text-sm font-mono" placeholder="{{ __('admin.classifieds.slug_auto_generated') }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.icon_field') }}</label>
                <input type="text" name="icon" id="f-icon" maxlength="50"
                       class="form-input w-full text-sm" placeholder="🏠">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.parent_category') }}</label>
                <select name="parent_id" id="f-parent-id" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.classifieds.top_level') }}</option>
                    @foreach($roots as $root)
                        <option value="{{ $root->id }}">{{ $root->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.contract_template') }}</label>
                <select name="contract_template_id" id="f-template-id" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.classifieds.no_template') }}</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl->id }}">{{ $tpl->name }} (v{{ $tpl->version }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.required_attachment_types') }}</label>
            <select id="f-attach-select" multiple
                    class="form-input w-full text-sm" style="height:130px;">
                <option value="ownership_deed">{{ __('admin.classifieds.attach_ownership_deed') }}</option>
                <option value="id_copy">{{ __('admin.classifieds.attach_id_copy') }}</option>
                <option value="floor_plan">{{ __('admin.classifieds.attach_floor_plan') }}</option>
                <option value="title_deed">{{ __('admin.classifieds.attach_title_deed') }}</option>
                <option value="noc_letter">{{ __('admin.classifieds.attach_noc_letter') }}</option>
                <option value="trade_license">{{ __('admin.classifieds.attach_trade_license') }}</option>
                <option value="passport_copy">{{ __('admin.classifieds.attach_passport_copy') }}</option>
                <option value="utility_bill">{{ __('admin.classifieds.attach_utility_bill') }}</option>
                <option value="photos">{{ __('admin.classifieds.attach_photos') }}</option>
                <option value="sketch">{{ __('admin.classifieds.attach_sketch') }}</option>
            </select>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.classifieds.attachment_hint') }}</p>
            <div id="f-attach-hidden"></div>
        </div>

        <div class="flex flex-wrap items-center gap-6">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="requires_location_map" id="f-req-map" value="1" class="rounded text-primary-600">
                <span class="text-sm text-gray-700">{{ __('admin.classifieds.requires_location_map') }}</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="requires_sketch_upload" id="f-req-sketch" value="1" class="rounded text-primary-600">
                <span class="text-sm text-gray-700">{{ __('admin.classifieds.requires_sketch_upload_ar') }}</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="is_active" id="f-is-active" value="1" class="rounded text-primary-600" checked>
                <span class="text-sm text-gray-700">{{ __('common.active') }}</span>
            </label>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.sort_order') }}</label>
            <input type="number" name="sort_order" id="f-sort-order" value="0" min="0"
                   class="form-input w-28 text-sm">
        </div>

        <div id="form-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    </form>

    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-save-category" class="btn btn-primary">{{ __('common.save') }}</button>
    </x-slot:footer>
</x-modal>

{{-- Delete confirmation modal --}}
<x-modal id="delete-modal" title="{{ __('admin.classifieds.delete_category_title') }}" size="sm">
    <p class="text-sm text-gray-700">{!! str_replace(':name', '<strong id="delete-cat-name"></strong>', __('admin.classifieds.delete_category_confirm', ['name' => ':name'])) !!}</p>
    <input type="hidden" id="delete-cat-id">
    <div id="delete-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-delete" class="btn btn-danger">{{ __('common.delete') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    new_category: "{{ __('admin.classifieds.new_category') }}",
    edit_category_title: "{{ __('admin.classifieds.edit_category_modal_title') }}",
    deactivate_parent_warning: "{{ __('admin.classifieds.deactivate_parent_warning') }}",
    toggle_failed: "{{ __('admin.classifieds.toggle_failed') }}",
    delete_failed: "{{ __('admin.classifieds.delete_failed') }}",
    error_occurred: "{{ __('common.error') }}",
});

(function () {
    const CSRF  = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const base  = '{{ rtrim(route("admin.classifieds.categories.index"), "/") }}';

    // ── Helpers ──────────────────────────────────────────────────────────────
    function openModal(id) {
        document.getElementById(id)?.classList.remove('hidden');
    }
    function closeModal(id) {
        document.getElementById(id)?.classList.add('hidden');
    }
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('[id]')?.classList.add('hidden');
        });
    });

    async function request(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };
    }

    // ── Attachment type multi-select sync ────────────────────────────────────
    const attachSelect  = document.getElementById('f-attach-select');
    const attachHidden  = document.getElementById('f-attach-hidden');

    function syncAttach() {
        attachHidden.innerHTML = '';
        Array.from(attachSelect.selectedOptions).forEach(opt => {
            const inp = Object.assign(document.createElement('input'), { type: 'hidden', name: 'required_attachment_types[]', value: opt.value });
            attachHidden.appendChild(inp);
        });
    }
    attachSelect.addEventListener('change', syncAttach);

    // ── Open "New Category" ───────────────────────────────────────────────────
    document.querySelector('[data-mode="create"]')?.addEventListener('click', () => {
        resetForm();
        document.querySelector('#category-modal [id$="-title"]').textContent = window.TRANSLATIONS.new_category;
        openModal('category-modal');
    });

    function resetForm() {
        document.getElementById('form-category-id').value = '';
        ['f-name-en','f-name-ar','f-slug','f-icon'].forEach(id => document.getElementById(id).value = '');
        Array.from(attachSelect.options).forEach(o => o.selected = false);
        document.getElementById('f-parent-id').value       = '';
        document.getElementById('f-template-id').value     = '';
        document.getElementById('f-req-map').checked       = false;
        document.getElementById('f-req-sketch').checked    = false;
        document.getElementById('f-is-active').checked     = true;
        document.getElementById('f-sort-order').value      = '0';
        document.getElementById('form-error').classList.add('hidden');
        attachHidden.innerHTML = '';
    }

    // ── Open "Edit Category" ──────────────────────────────────────────────────
    document.querySelectorAll('.btn-edit-category').forEach(btn => {
        btn.addEventListener('click', () => {
            const cat = JSON.parse(btn.dataset.category);
            document.getElementById('form-category-id').value      = cat.id;
            document.getElementById('f-name-en').value             = cat.name_en ?? '';
            document.getElementById('f-name-ar').value             = cat.name_ar ?? '';
            document.getElementById('f-slug').value                = cat.slug ?? '';
            document.getElementById('f-icon').value                = cat.icon ?? '';
            document.getElementById('f-parent-id').value           = cat.parent_id ?? '';
            document.getElementById('f-template-id').value         = cat.contract_template_id ?? '';
            document.getElementById('f-req-map').checked           = !!cat.requires_location_map;
            document.getElementById('f-req-sketch').checked        = !!cat.requires_sketch_upload;
            document.getElementById('f-is-active').checked         = !!cat.is_active;
            document.getElementById('f-sort-order').value          = cat.sort_order ?? 0;
            const selected = new Set(cat.required_attachment_types ?? []);
            Array.from(attachSelect.options).forEach(o => o.selected = selected.has(o.value));
            syncAttach();
            document.getElementById('form-error').classList.add('hidden');
            document.querySelector('#category-modal [id$="-title"]').textContent = window.TRANSLATIONS.edit_category_title;
            openModal('category-modal');
        });
    });

    // ── Save (create or update) ───────────────────────────────────────────────
    document.getElementById('btn-save-category').addEventListener('click', async () => {
        syncAttach();
        const id   = document.getElementById('form-category-id').value;
        const form = document.getElementById('category-form');
        const fd   = new FormData(form);
        const body = {};
        fd.forEach((v, k) => {
            if (k.endsWith('[]')) {
                const key = k.slice(0, -2);
                (body[key] = body[key] ?? []).push(v);
            } else {
                body[k] = v;
            }
        });
        // Checkboxes: if not in fd they're unchecked
        ['requires_location_map', 'requires_sketch_upload', 'is_active'].forEach(k => {
            body[k] = fd.has(k) ? 1 : 0;
        });

        const url    = id ? `${base}/${id}` : base;
        const method = id ? 'PUT' : 'POST';
        const { ok, data } = await request(url, method, body);

        if (ok) {
            closeModal('category-modal');
            location.reload();
        } else {
            const errEl = document.getElementById('form-error');
            errEl.textContent = data.message ?? (data.errors ? Object.values(data.errors).flat().join(' ') : window.TRANSLATIONS.error_occurred);
            errEl.classList.remove('hidden');
        }
    });

    // ── Toggle Active ─────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-toggle-active').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id         = btn.dataset.id;
            const isActive   = btn.dataset.active === '1';
            const hasChildren = btn.dataset.hasChildren === '1';

            if (isActive && hasChildren) {
                if (!confirm(window.TRANSLATIONS.deactivate_parent_warning)) {
                    return;
                }
            }

            const { ok, data } = await request(`${base}/${id}/toggle`, 'POST');
            if (ok) {
                const nowActive = data.is_active;
                btn.dataset.active = nowActive ? '1' : '0';
                btn.classList.toggle('bg-emerald-500', nowActive);
                btn.classList.toggle('bg-gray-200', !nowActive);
                const knob = btn.querySelector('span');
                knob.classList.toggle('translate-x-4', nowActive);
                knob.classList.toggle('translate-x-0.5', !nowActive);
            } else {
                alert(data.message ?? window.TRANSLATIONS.toggle_failed);
            }
        });
    });

    // ── Delete ────────────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-delete-category').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete-cat-id').value  = btn.dataset.id;
            document.getElementById('delete-cat-name').textContent = btn.dataset.name;
            document.getElementById('delete-error').classList.add('hidden');
            openModal('delete-modal');
        });
    });

    document.getElementById('btn-confirm-delete').addEventListener('click', async () => {
        const id = document.getElementById('delete-cat-id').value;
        const { ok, data } = await request(`${base}/${id}`, 'DELETE');
        if (ok) {
            closeModal('delete-modal');
            location.reload();
        } else {
            const errEl = document.getElementById('delete-error');
            errEl.textContent = data.message ?? window.TRANSLATIONS.delete_failed;
            errEl.classList.remove('hidden');
        }
    });

    // ── SortableJS (top-level rows only) ──────────────────────────────────────
    const treeBody = document.getElementById('category-tree');
    if (typeof Sortable !== 'undefined' && treeBody) {
        Sortable.create(treeBody, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: async () => {
                const ids = [...treeBody.querySelectorAll('tr.category-row[data-id]')]
                    .filter(tr => !tr.classList.contains('bg-gray-50/40')) // parent rows only
                    .map(tr => tr.dataset.id);

                await request('{{ route("admin.classifieds.categories.reorder") }}', 'POST', { ids });
            },
        });
    }
})();
</script>
@endpush
@endsection
