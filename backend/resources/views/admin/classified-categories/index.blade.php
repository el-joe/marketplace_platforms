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
        <div class="table-responsive">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-8 px-3 py-3"></th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.name') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('classified.admin.image_icon') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('classified.admin.appears_on') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('classified.admin.countries') }}</th>
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
                        @include('admin.classified-categories._row', ['cat' => $root, 'isChild' => false])
                        @foreach($root->children->sortBy('sort_order') as $child)
                            @include('admin.classified-categories._row', ['cat' => $child, 'isChild' => true])
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-10 text-center text-gray-400">{{ __('admin.classifieds.no_categories_yet') }}</td>
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
                <p class="mt-0.5 text-xs text-gray-400">{{ __('classified.admin.icon_help') }}</p>
            </div>
        </div>
        <p class="-mt-2 text-xs text-gray-400">{{ __('classified.admin.slug_help') }}</p>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('classified.admin.description_en') }}</label>
                <textarea name="description_en" id="f-desc-en" rows="2" maxlength="500" dir="ltr" class="form-input w-full text-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('classified.admin.description_ar') }}</label>
                <textarea name="description_ar" id="f-desc-ar" rows="2" maxlength="500" dir="rtl" class="form-input w-full text-sm"></textarea>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('classified.admin.image') }}</label>
            <div class="flex items-center gap-3">
                <img id="f-image-preview" src="" alt="" class="hidden h-14 w-14 rounded-lg border object-cover">
                <input type="file" name="image" id="f-image" accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-primary-700">
            </div>
            <label id="f-remove-image-wrap" class="mt-1 hidden items-center gap-2 text-xs text-red-600">
                <input type="checkbox" name="remove_image" id="f-remove-image" value="1"> {{ __('classified.admin.remove_image') }}
            </label>
            <p class="mt-0.5 text-xs text-gray-400">{{ __('classified.admin.image_help') }}</p>
        </div>

        <div id="f-placement-section" class="space-y-2 rounded-lg border border-gray-100 bg-gray-50 p-3">
            <p class="text-xs font-medium text-gray-700">{{ __('classified.admin.placement_title') }}</p>
            <input type="hidden" name="placements_submitted" value="1">
            <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                @foreach($placements as $placement)
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="placements[]" value="{{ $placement->value }}" data-placement class="rounded text-primary-600">
                        {{ $placement->label() }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-400">{{ __('classified.admin.placement_help') }}</p>
        </div>

        <div class="space-y-2 rounded-lg border border-gray-100 bg-gray-50 p-3">
            <p class="text-xs font-medium text-gray-700">{{ __('classified.admin.countries_title') }}</p>
            <input type="hidden" name="countries_submitted" value="1">
            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-3">
                @foreach($countries as $country)
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="country_ids[]" value="{{ $country->id }}" data-country class="rounded text-primary-600">
                        {{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-400">{{ __('classified.admin.countries_help') }}</p>
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
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('contracts.admin.requires_title') }}</label>
                <div class="flex gap-5 pt-1.5 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="radio" name="requires_contract" id="f-requires-yes" value="1"> {{ __('contracts.admin.yes') }}</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" name="requires_contract" id="f-requires-no" value="0" checked> {{ __('contracts.admin.no') }}</label>
                </div>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('contracts.admin.requires_help') }}</p>
            </div>
        </div>

        <div id="f-contract-section" class="hidden space-y-3 rounded-lg border border-gray-100 bg-gray-50 p-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('contracts.admin.all_countries_template') }}</label>
                <select name="contract_template_id" id="f-template-id" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.classifieds.no_template') }}</option>
                    @foreach($templates->whereNull('country_id') as $tpl)
                        <option value="{{ $tpl->id }}">{{ $tpl->name }} (v{{ $tpl->version }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">{{ __('contracts.admin.all_countries_template_hint') }}</p>
            </div>
            @if($contractData['countries']->isNotEmpty())
                <div class="space-y-2">
                    <p class="text-xs font-medium text-gray-700">{{ __('contracts.admin.template_per_country') }}</p>
                    @foreach($contractData['countries'] as $country)
                        @php($countryTemplates = $templates->where('country_id', $country->id))
                        <div class="flex items-center gap-2">
                            <span class="w-32 shrink-0 text-xs text-gray-600">{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}</span>
                            <select data-country-contract="{{ $country->id }}" class="form-input w-full text-xs" @disabled($countryTemplates->isEmpty())>
                                <option value="">{{ $countryTemplates->isEmpty() ? __('contracts.admin.no_template_for_country') : __('contracts.admin.use_country_default') }}</option>
                                @foreach($countryTemplates as $tpl)
                                    <option value="{{ $tpl->id }}">{{ $tpl->name }} (v{{ $tpl->version }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <p class="text-xs text-gray-400">{{ __('contracts.admin.template_per_country_hint') }}</p>
                </div>
            @endif
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
    <p class="mt-2 text-xs text-gray-500">{{ __('classified.admin.delete_hint') }}</p>
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
    deactivate_confirm: @json(__('classified.admin.deactivate_confirm')),
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

    // ── Image preview, placements, countries ─────────────────────────────────
    const imageInput   = document.getElementById('f-image');
    const imagePreview = document.getElementById('f-image-preview');
    const removeWrap   = document.getElementById('f-remove-image-wrap');
    const removeBox    = document.getElementById('f-remove-image');

    function setMedia(url) {
        imageInput.value = '';
        removeBox.checked = false;
        imagePreview.classList.toggle('hidden', !url);
        imagePreview.src = url || '';
        removeWrap.classList.toggle('hidden', !url);
        removeWrap.classList.toggle('inline-flex', !!url);
    }
    imageInput.addEventListener('change', () => {
        const file = imageInput.files[0];
        if (file) {
            imagePreview.src = URL.createObjectURL(file);
            imagePreview.classList.remove('hidden');
        }
    });

    function setPlacementsAndCountries(placements, countryIds) {
        document.querySelectorAll('[data-placement]').forEach(el => el.checked = placements.includes(el.value));
        document.querySelectorAll('[data-country]').forEach(el => el.checked = countryIds.includes(el.value));
    }

    // Placements belong to top-level sections; sub-categories appear under their section.
    function syncPlacementVisibility(parentId) {
        document.getElementById('f-placement-section').classList.toggle('hidden', !!parentId);
    }
    document.getElementById('f-parent-id').addEventListener('change', e => syncPlacementVisibility(e.target.value));

    // ── Open "New Category" ───────────────────────────────────────────────────
    document.querySelector('[data-mode="create"]')?.addEventListener('click', () => {
        resetForm();
        document.querySelector('#category-modal [id$="-title"]').textContent = window.TRANSLATIONS.new_category;
        openModal('category-modal');
    });

    // ── Requires individual contract (Yes/No) and template per country ───────
    function setContractState(requires, perCountry) {
        document.getElementById(requires ? 'f-requires-yes' : 'f-requires-no').checked = true;
        document.getElementById('f-contract-section').classList.toggle('hidden', !requires);
        document.querySelectorAll('[data-country-contract]').forEach(el => {
            el.value = (perCountry && !Array.isArray(perCountry) && perCountry[el.dataset.countryContract]) || '';
        });
    }
    document.querySelectorAll('input[name="requires_contract"]').forEach(el => el.addEventListener('change', () => {
        document.getElementById('f-contract-section').classList.toggle('hidden', !document.getElementById('f-requires-yes').checked);
    }));

    function resetForm() {
        document.getElementById('form-category-id').value = '';
        ['f-name-en','f-name-ar','f-slug','f-icon','f-desc-en','f-desc-ar'].forEach(id => document.getElementById(id).value = '');
        setMedia(null);
        setPlacementsAndCountries(['nav', 'landing'], []);
        syncPlacementVisibility('');
        Array.from(attachSelect.options).forEach(o => o.selected = false);
        document.getElementById('f-parent-id').value       = '';
        document.getElementById('f-template-id').value     = '';
        setContractState(false, {});
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
            document.getElementById('f-desc-en').value             = cat.description_en ?? '';
            document.getElementById('f-desc-ar').value             = cat.description_ar ?? '';
            setMedia(cat.image_url);
            setPlacementsAndCountries(cat.placements ?? [], cat.country_ids ?? []);
            document.getElementById('f-parent-id').value           = cat.parent_id ?? '';
            syncPlacementVisibility(cat.parent_id ?? '');
            document.getElementById('f-template-id').value         = cat.contract_template_id ?? '';
            setContractState(!!cat.requires_contract, cat.country_contracts ?? {});
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
        fd.set('requires_contract', document.getElementById('f-requires-yes').checked ? 1 : 0);
        document.querySelectorAll('[data-country-contract]').forEach(el => {
            if (el.value) fd.set(`country_contracts[${el.dataset.countryContract}]`, el.value);
        });
        // Checkboxes: if not in fd they're unchecked
        ['requires_location_map', 'requires_sketch_upload', 'is_active'].forEach(k => {
            fd.set(k, fd.has(k) ? 1 : 0);
        });
        if (!(imageInput.files && imageInput.files.length)) fd.delete('image');
        if (id) fd.set('_method', 'PUT');

        const saveBtn = document.getElementById('btn-save-category');
        saveBtn.disabled = true;
        const res  = await fetch(id ? `${base}/${id}` : base, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: fd,
        });
        const data = await res.json().catch(() => ({}));
        saveBtn.disabled = false;

        if (res.ok) {
            if (data.warning) alert(data.warning);
            closeModal('category-modal');
            location.reload();
        } else {
            const errEl = document.getElementById('form-error');
            errEl.textContent = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message ?? window.TRANSLATIONS.error_occurred);
            errEl.classList.remove('hidden');
        }
    });

    // ── Toggle Active ─────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-toggle-active').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id         = btn.dataset.id;
            const isActive   = btn.dataset.active === '1';

            if (isActive) {
                if (!confirm(window.TRANSLATIONS.deactivate_confirm)) {
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
