@extends('layouts.admin')

@section('title', __('admin.vendor_document_types.title'))

@section('content')

    {{-- ─── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.vendor_document_types.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.vendor_document_types.page_subtitle') }}</p>
        </div>
        <button type="button" onclick="openCreateModal()" class="btn-primary">
            <x-heroicon name="plus" class="w-4 h-4 mr-1.5 inline" /> {{ __('admin.vendor_document_types.new_document_type') }}
        </button>
    </div>

    {{-- ─── Matrix Table ────────────────────────────────────────────────────────── --}}
    <div class="card overflow-x-auto">
        <table class="w-full text-sm" id="doc-types-table">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="px-4 py-3 text-start font-semibold text-gray-700 min-w-[220px]">{{ __('admin.vendor_document_types.document_type_column') }}</th>
                    <th class="px-3 py-3 text-center font-semibold text-gray-700 text-xs">{{ __('admin.vendor_document_types.expiry_column') }}</th>
                    <th class="px-3 py-3 text-center font-semibold text-gray-700 text-xs">{{ __('admin.vendor_document_types.status_column') }}</th>
                    @foreach($countries as $country)
                        <th class="px-2 py-3 text-center font-semibold text-gray-700 text-xs min-w-[110px]">
                            <span title="{{ $country->name_en }}">
                                {{ $country->flag_emoji ?? '' }} {{ $country->iso_code_2 }}
                            </span>
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-end font-semibold text-gray-700">{{ __('admin.vendor_document_types.actions_column') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($types as $type)
                    <tr class="hover:bg-gray-50 transition-colors {{ $type->is_active ? '' : 'opacity-60' }}"
                        data-type-id="{{ $type->id }}">
                        {{-- Name + code --}}
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $type->name_en }}</div>
                            <div class="text-xs text-gray-400 font-mono mt-0.5">{{ $type->code }}</div>
                            @if($type->name_ar)
                                <div class="text-xs text-gray-500 mt-0.5 font-medium" dir="rtl">{{ $type->name_ar }}</div>
                            @endif
                        </td>

                        {{-- Expiry required --}}
                        <td class="px-3 py-3 text-center">
                            @if($type->requires_expiry_date)
                                <span
                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 text-amber-700"
                                    title="{{ __('admin.vendor_document_types.requires_expiry_title') }}">
                                    <x-heroicon name="calendar" class="w-3 h-3" />
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Active badge --}}
                        <td class="px-3 py-3 text-center">
                            @if($type->is_active)
                                <span class="badge badge-success">{{ __('admin.vendor_document_types.active') }}</span>
                            @else
                                <span class="badge badge-gray">{{ __('admin.vendor_document_types.inactive') }}</span>
                            @endif
                        </td>

                        {{-- Per-country requirement selects --}}
                        @foreach($countries as $country)
                                @php
                                    $req = $type->countryRequirements->firstWhere('country_id', $country->id);
                                    $level = $req?->requirement_level?->value ?? 'optional';
                                @endphp
                                <td class="px-2 py-3 text-center">
                                    <div class="relative inline-block">
                                        <select class="req-select text-xs rounded border px-1.5 py-1 pr-5 appearance-none focus:outline-none focus:ring-2 focus:ring-primary-400 cursor-pointer
                                                                        {{ $level === 'mandatory' ? 'bg-red-50 border-red-200 text-red-700'
                            : ($level === 'not_applicable' ? 'bg-gray-100 border-gray-200 text-gray-400'
                                : 'bg-white border-gray-200 text-gray-600') }}" data-type-id="{{ $type->id }}"
                                            data-country-id="{{ $country->id }}" onchange="saveRequirement(this)">
                                            <option value="mandatory" {{ $level === 'mandatory' ? 'selected' : '' }}>{{ __('admin.vendor_document_types.mandatory') }}</option>
                                            <option value="optional" {{ $level === 'optional' ? 'selected' : '' }}>{{ __('admin.vendor_document_types.optional') }}</option>
                                            <option value="not_applicable" {{ $level === 'not_applicable' ? 'selected' : '' }}>{{ __('admin.vendor_document_types.na_option') }}
                                            </option>
                                        </select>
                                        {{-- Saved checkmark --}}
                                        <span
                                            class="req-saved invisible absolute -top-2 -right-2 w-4 h-4 bg-green-500 text-white rounded-full flex items-center justify-center"
                                            aria-hidden="true">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    </div>
                                </td>
                        @endforeach

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <button type="button" class="btn-icon text-gray-400 hover:text-primary-600" title="{{ __('admin.vendor_document_types.edit') }}"
                                onclick="openEditModal({{ $type->toJson() }})">
                                <x-heroicon name="pencil-square" class="w-4 h-4" />
                            </button>
                            <button type="button" class="btn-icon text-gray-400 hover:text-amber-600 ml-1"
                                title="{{ $type->is_active ? __('admin.vendor_document_types.deactivate') : __('admin.vendor_document_types.activate') }}"
                                onclick="toggleType('{{ $type->id }}', {{ $type->is_active ? 'true' : 'false' }})">
                                @if($type->is_active)
                                    <x-heroicon name="eye-slash" class="w-4 h-4" />
                                @else
                                    <x-heroicon name="eye" class="w-4 h-4" />
                                @endif
                            </button>
                            <button type="button" class="btn-icon text-gray-400 hover:text-red-600 ml-1" title="{{ __('admin.vendor_document_types.delete') }}"
                                onclick="deleteType('{{ $type->id }}', '{{ addslashes($type->name_en) }}')">
                                <x-heroicon name="trash" class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 4 + $countries->count() }}" class="px-4 py-10 text-center text-gray-400">
                            {{ __('admin.vendor_document_types.no_document_types_yet', ['action' => __('admin.vendor_document_types.new_document_type')]) }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ─── Create / Edit Modal ────────────────────────────────────────────────── --}}
    <x-modal id="doc-type-modal" title="{{ __('admin.vendor_document_types.new_document_type_title') }}" size="lg">
        <form id="doc-type-form" class="space-y-4" onsubmit="submitDocTypeForm(event)">
            <input type="hidden" id="dt-id" name="id" value="">

            <div id="code-change-warning"
                class="hidden bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-2 text-sm">
                {{ __('admin.vendor_document_types.code_change_warning') }}
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-form.input name="dt_code" id="dt-code" label="{{ __('admin.vendor_document_types.code_slug') }}" placeholder="{{ __('admin.vendor_document_types.code_placeholder') }}" required />
                <x-form.input name="dt_sort_order" id="dt-sort-order" label="{{ __('admin.vendor_document_types.sort_order') }}" type="number" placeholder="0" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-form.input name="dt_name_en" id="dt-name-en" label="{{ __('admin.vendor_document_types.name_en_label') }}" placeholder="{{ __('admin.vendor_document_types.name_en_placeholder') }}" dir="ltr"
                    required />
                <x-form.input name="dt_name_ar" id="dt-name-ar" label="{{ __('admin.vendor_document_types.name_ar_label') }}" placeholder="{{ __('admin.vendor_document_types.name_ar_placeholder') }}" dir="rtl" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-form.textarea name="dt_description_en" id="dt-description-en" label="{{ __('admin.vendor_document_types.description_en_label') }}" rows="2" dir="ltr" />
                <x-form.textarea name="dt_description_ar" id="dt-description-ar" label="{{ __('admin.vendor_document_types.description_ar_label') }}" rows="2" dir="rtl" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">{{ __('admin.vendor_document_types.accepted_file_types') }} <span
                            class="text-gray-400 font-normal">{{ __('admin.vendor_document_types.comma_separated') }}</span></label>
                    <input type="text" id="dt-accepted-file-types" class="form-input" placeholder="{{ __('admin.vendor_document_types.file_types_placeholder') }}" />
                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.vendor_document_types.file_types_hint') }}</p>
                </div>
                <x-form.input name="dt_max_file_size_kb" id="dt-max-file-size-kb" label="{{ __('admin.vendor_document_types.max_file_size_kb') }}" type="number"
                    placeholder="5120" />
            </div>

            <div class="flex items-center gap-3">
                <x-form.toggle name="dt_requires_expiry_date" id="dt-requires-expiry-date" label="{{ __('admin.vendor_document_types.requires_expiry_date') }}" />
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="btn-secondary">{{ __('admin.vendor_document_types.cancel') }}</button>
            <button type="submit" form="doc-type-form" class="btn-primary" id="doc-type-submit">{{ __('admin.vendor_document_types.save') }}</button>
        </x-slot:footer>
    </x-modal>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            newDocumentTypeTitle: @json(__('admin.vendor_document_types.new_document_type_title')),
            editDocumentTypeTitle: @json(__('admin.vendor_document_types.edit_document_type_title')),
            deactivate: @json(__('admin.vendor_document_types.deactivate')),
            activate: @json(__('admin.vendor_document_types.activate')),
            deactivateConfirmSuffix: @json(__('admin.vendor_document_types.deactivate_instead_confirm')),
            deleteConfirm: @json(__('admin.vendor_document_types.delete_confirm')),
            deleteBtn: @json(__('admin.vendor_document_types.delete')),
            failedUpdateStatus: @json(__('admin.vendor_document_types.failed_update_status')),
            failedDelete: @json(__('admin.vendor_document_types.failed_delete')),
            failedDeactivate: @json(__('admin.vendor_document_types.failed_deactivate')),
            failedSaveRequirement: @json(__('admin.vendor_document_types.failed_save_requirement')),
            confirmBtn: @json(__('admin.vendor_document_types.confirm_btn')),
            cancelBtn: @json(__('admin.vendor_document_types.cancel')),
            deactivateConfirm: @json(__('admin.vendor_document_types.deactivate_confirm')),
            activateConfirm: @json(__('admin.vendor_document_types.activate_confirm')),
            deleteConfirmTemplate: @json(__('admin.vendor_document_types.delete_confirm', ['name' => '__NAME__'])),
        });

        document.addEventListener('DOMContentLoaded', () => {
            const pending = sessionStorage.getItem('__toast');
            if (pending) {
                sessionStorage.removeItem('__toast');
                try {
                    const { type, message } = JSON.parse(pending);
                    if (type === 'success') Toast.success(message);
                    else if (type === 'error') Toast.error(message);
                    else Toast.info(message);
                } catch {}
            }
        });

        const ROUTES = {
            store: '{{ route('admin.vendor-document-types.store') }}',
            update: (id) => `/vendor-document-types/${id}`,
            destroy: (id) => `/vendor-document-types/${id}`,
            toggle: (id) => `/vendor-document-types/${id}/toggle`,
            requirement: '{{ route('admin.vendor-document-types.requirements.update') }}',
        };

        const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

        // ── Toast confirm helper ────────────────────────────────────────────────────

        function toastConfirm(message, onConfirm, { danger = false, confirmLabel = window.TRANSLATIONS.confirmBtn } = {}) {
            const node = document.createElement('div');
            node.style.cssText = 'min-width:200px';
            node.innerHTML = `
                    <div style="font-size:13px;font-weight:600;margin-bottom:10px;line-height:1.4">${message}</div>
                    <div style="display:flex;gap:8px">
                        <button class="tc-yes" style="background:#fff;color:${danger ? '#dc2626' : '#1d4ed8'};border:none;padding:5px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;white-space:nowrap">${confirmLabel}</button>
                        <button class="tc-no" style="background:transparent;color:rgba(255,255,255,0.9);border:1px solid rgba(255,255,255,0.4);padding:5px 14px;border-radius:6px;cursor:pointer;font-size:12px;white-space:nowrap">${window.TRANSLATIONS.cancelBtn}</button>
                    </div>
                `;
            const toast = Toastify({
                node,
                duration: -1,
                gravity: 'top',
                position: 'right',
                stopOnFocus: true,
                style: {
                    background: danger ? '#b91c1c' : '#1e3a8a',
                    borderRadius: '0.6rem',
                    padding: '14px 16px',
                    boxShadow: '0 4px 16px rgba(0,0,0,0.25)',
                },
            });
            toast.showToast();
            node.querySelector('.tc-yes').onclick = () => { toast.hideToast(); onConfirm(); };
            node.querySelector('.tc-no').onclick = () => { toast.hideToast(); };
        }

        // ── Modal helpers ──────────────────────────────────────────────────────────

        function setToggle(checked) {
            const el = document.getElementById('dt_requires_expiry_date');
            if (!el) return;
            el.checked = checked;
            el.dispatchEvent(new Event('change'));
        }

        function openCreateModal() {
            document.getElementById('dt-id').value = '';
            document.getElementById('dt_code').value = '';
            document.getElementById('dt_name_en').value = '';
            document.getElementById('dt_name_ar').value = '';
            document.getElementById('dt_description_en').value = '';
            document.getElementById('dt_description_ar').value = '';
            document.getElementById('dt-accepted-file-types').value = '';
            document.getElementById('dt_max_file_size_kb').value = '';
            document.getElementById('dt_sort_order').value = '0';
            setToggle(false);
            document.getElementById('code-change-warning').classList.add('hidden');
            document.querySelector('#doc-type-modal [id$="-title"]').textContent = window.TRANSLATIONS.newDocumentTypeTitle;
            openModal('doc-type-modal');
        }

        function openEditModal(type) {
            document.getElementById('dt-id').value = type.id;
            document.getElementById('dt_code').value = type.code;
            document.getElementById('dt_name_en').value = type.name_en;
            document.getElementById('dt_name_ar').value = type.name_ar ?? '';
            document.getElementById('dt_description_en').value = type.description_en ?? '';
            document.getElementById('dt_description_ar').value = type.description_ar ?? '';
            document.getElementById('dt-accepted-file-types').value = (type.accepted_file_types ?? []).join(', ');
            document.getElementById('dt_max_file_size_kb').value = type.max_file_size_kb ?? '';
            document.getElementById('dt_sort_order').value = type.sort_order ?? 0;
            setToggle(!!type.requires_expiry_date);
            document.getElementById('code-change-warning').classList.add('hidden');

            const codeInput = document.getElementById('dt_code');
            const originalCode = type.code;
            codeInput.oninput = () => {
                document.getElementById('code-change-warning')
                    .classList.toggle('hidden', codeInput.value === originalCode);
            };

            const title = document.getElementById('doc-type-modal').querySelector('[id$="-title"]');
            if (title) title.textContent = window.TRANSLATIONS.editDocumentTypeTitle;
            openModal('doc-type-modal');
        }

        // ── Form submit ────────────────────────────────────────────────────────────

        async function submitDocTypeForm(e) {
            e.preventDefault();

            const id = document.getElementById('dt-id').value;
            const isEdit = !!id;

            const fileTypesRaw = document.getElementById('dt-accepted-file-types').value.trim();
            const acceptedFileTypes = fileTypesRaw
                ? fileTypesRaw.split(',').map(s => s.trim()).filter(Boolean)
                : [];

            const payload = {
                code: document.getElementById('dt_code').value,
                name_en: document.getElementById('dt_name_en').value,
                name_ar: document.getElementById('dt_name_ar').value,
                description_en: document.getElementById('dt_description_en').value || null,
                description_ar: document.getElementById('dt_description_ar').value || null,
                accepted_file_types: acceptedFileTypes.length ? acceptedFileTypes : null,
                max_file_size_kb: document.getElementById('dt_max_file_size_kb').value || null,
                sort_order: document.getElementById('dt_sort_order').value || 0,
                requires_expiry_date: document.getElementById('dt_requires_expiry_date').checked ? 1 : 0,
            };

            const url = isEdit ? ROUTES.update(id) : ROUTES.store;
            const method = isEdit ? 'PUT' : 'POST';

            const btn = document.getElementById('doc-type-submit');
            btn.disabled = true;

            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();

                if (!res.ok) {
                    Object.values(json.errors ?? { _: [json.message ?? 'An error occurred.'] })
                        .flat()
                        .forEach(m => Toast.error(m));
                    return;
                }

                sessionStorage.setItem('__toast', JSON.stringify({ type: 'success', message: json.message }));
                closeModal('doc-type-modal');
                window.location.reload();
            } finally {
                btn.disabled = false;
            }
        }

        // ── Toggle active ──────────────────────────────────────────────────────────

        function toggleType(id, currentlyActive) {
            const label = currentlyActive ? window.TRANSLATIONS.deactivate : window.TRANSLATIONS.activate;
            const confirmMsg = currentlyActive ? window.TRANSLATIONS.deactivateConfirm : window.TRANSLATIONS.activateConfirm;
            toastConfirm(confirmMsg, async () => {
                const res = await fetch(ROUTES.toggle(id), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (json.success) {
                    sessionStorage.setItem('__toast', JSON.stringify({ type: 'success', message: json.message }));
                    window.location.reload();
                } else {
                    Toast.error(json.message ?? window.TRANSLATIONS.failedUpdateStatus);
                }
            }, { confirmLabel: label });
        }

        // ── Delete ─────────────────────────────────────────────────────────────────

        function deleteType(id, name) {
            const msg = window.TRANSLATIONS.deleteConfirmTemplate.replace('__NAME__', `<strong>${name}</strong>`);
            toastConfirm(msg, () => executeDelete(id), {
                danger: true,
                confirmLabel: window.TRANSLATIONS.deleteBtn,
            });
        }

        async function executeDelete(id) {
            const res = await fetch(ROUTES.destroy(id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            const json = await res.json();

            if (json.success) {
                sessionStorage.setItem('__toast', JSON.stringify({ type: 'success', message: json.message }));
                window.location.reload();
                return;
            }

            if (res.status === 422) {
                toastConfirm(json.message + window.TRANSLATIONS.deactivateConfirmSuffix, async () => {
                    const r2 = await fetch(ROUTES.toggle(id), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    });
                    const j2 = await r2.json();
                    j2.success ? window.location.reload() : Toast.error(j2.message ?? window.TRANSLATIONS.failedDeactivate);
                }, { confirmLabel: window.TRANSLATIONS.deactivate });
            } else {
                Toast.error(json.message ?? window.TRANSLATIONS.failedDelete);
            }
        }

        // ── Requirement cell AJAX save ─────────────────────────────────────────────

        async function saveRequirement(select) {
            const typeId = select.dataset.typeId;
            const countryId = select.dataset.countryId;
            const level = select.value;

            applySelectColour(select, level);

            const savedMark = select.parentElement.querySelector('.req-saved');
            const res = await fetch(ROUTES.requirement, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ vendor_document_type_id: typeId, country_id: countryId, requirement_level: level }),
            });

            if (res.ok) {
                savedMark.classList.remove('invisible');
                setTimeout(() => savedMark.classList.add('invisible'), 1200);
            } else {
                Toast.error(window.TRANSLATIONS.failedSaveRequirement);
                window.location.reload();
            }
        }

        function applySelectColour(select, level) {
            select.className = select.className.replace(/bg-\S+|border-\S+|text-\S+/g, '').trim();
            const colourMap = {
                mandatory: 'bg-red-50 border-red-200 text-red-700',
                optional: 'bg-white border-gray-200 text-gray-600',
                not_applicable: 'bg-gray-100 border-gray-200 text-gray-400',
            };
            select.classList.add(...(colourMap[level] ?? colourMap.optional).split(' '));
        }

        // ── Modal open/close ───────────────────────────────────────────────────────

        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('hidden');
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        }
    </script>
@endpush
