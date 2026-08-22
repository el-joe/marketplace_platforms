@extends('layouts.admin')

@section('title', __('admin.classifieds.contract_templates_title'))

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.classifieds.contract_templates_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.classifieds.contract_templates_desc') }}</p>
        </div>
        <button type="button" data-modal-open="template-modal" data-mode="create"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.classifieds.new_template') }}
        </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.name') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.classifieds.version') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('common.active') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.created') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($templates as $tpl)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $tpl->name }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                                v{{ $tpl->version }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($tpl->is_active)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('common.active') }}</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ __('common.inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $tpl->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <button type="button"
                                    class="btn-edit-template text-xs text-primary-600 font-medium hover:underline"
                                    data-template="{{ json_encode(['id' => $tpl->id, 'name' => $tpl->name, 'content_en' => $tpl->content_en, 'content_ar' => $tpl->content_ar, 'is_active' => $tpl->is_active]) }}">
                                {{ __('common.edit') }}
                            </button>
                            <button type="button"
                                    class="btn-delete-template ms-2 text-xs text-red-500 hover:underline"
                                    data-id="{{ $tpl->id }}" data-name="{{ $tpl->name }}">
                                {{ __('common.delete') }}
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400">{{ __('admin.classifieds.no_templates') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Template Modal --}}
<x-modal id="template-modal" title="{{ __('admin.classifieds.contract_template') }}" size="xl">
    <form id="template-form" class="space-y-4">
        @csrf
        <input type="hidden" id="ft-id" value="">

        <div class="flex items-center gap-4">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.template_name') }} <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="ft-name" required maxlength="200"
                       class="form-input w-full text-sm" placeholder="{{ __('admin.classifieds.template_name_placeholder') }}">
            </div>
            <label class="flex items-center gap-2 cursor-pointer mt-5 select-none">
                <input type="checkbox" name="is_active" id="ft-is-active" value="1" class="rounded text-primary-600" checked>
                <span class="text-sm text-gray-700">{{ __('common.active') }}</span>
            </label>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.contract_body_en') }} <span class="text-red-500">*</span></label>
            <textarea name="content_en" id="ft-content-en" rows="10" required dir="ltr"
                      class="form-input w-full text-sm font-mono" placeholder="{{ __('admin.classifieds.contract_body_en_placeholder') }}"></textarea>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classifieds.contract_body_ar') }} <span class="text-red-500">*</span></label>
            <textarea name="content_ar" id="ft-content-ar" rows="10" required dir="rtl"
                      class="form-input w-full text-sm font-mono" placeholder="{{ __('admin.classifieds.contract_body_ar_placeholder') }}"></textarea>
        </div>

        <p id="ft-version-note" class="hidden text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            <strong>{{ __('admin.classifieds.note_label') }}</strong> {{ __('admin.classifieds.version_note') }}
        </p>

        <div id="ft-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    </form>

    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-save-template" class="btn btn-primary">{{ __('common.save') }}</button>
    </x-slot:footer>
</x-modal>

{{-- Delete confirmation modal --}}
<x-modal id="delete-template-modal" title="{{ __('admin.classifieds.delete_template') }}" size="sm">
    <p class="text-sm text-gray-700">{!! str_replace(':name', '<strong id="del-tpl-name"></strong>', __('admin.classifieds.delete_template_confirm', ['name' => ':name'])) !!}</p>
    <input type="hidden" id="del-tpl-id">
    <div id="del-tpl-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-del-tpl" class="btn btn-danger">{{ __('common.delete') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    new_template: "{{ __('admin.classifieds.new_template') }}",
    edit_template_title: "{{ __('admin.classifieds.edit_template_modal_title') }}",
    error_occurred: "{{ __('common.error') }}",
    delete_failed: "{{ __('admin.classifieds.delete_failed') }}",
});

(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const base = '{{ rtrim(route("admin.classifieds.contract-templates.index"), "/") }}';

    function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('[id]')?.classList.add('hidden'));
    });

    async function request(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, data };
    }

    // Store original content for change-detection
    let originalContentEn = '', originalContentAr = '';

    function resetForm() {
        document.getElementById('ft-id').value         = '';
        document.getElementById('ft-name').value       = '';
        document.getElementById('ft-content-en').value = '';
        document.getElementById('ft-content-ar').value = '';
        document.getElementById('ft-is-active').checked = true;
        document.getElementById('ft-error').classList.add('hidden');
        document.getElementById('ft-version-note').classList.add('hidden');
        originalContentEn = ''; originalContentAr = '';
    }

    document.querySelector('[data-mode="create"]')?.addEventListener('click', () => {
        resetForm();
        document.querySelector('#template-modal [id$="-title"]').textContent = window.TRANSLATIONS.new_template;
        openModal('template-modal');
    });

    // Show versioning note when content changes
    ['ft-content-en', 'ft-content-ar'].forEach(id => {
        document.getElementById(id).addEventListener('input', () => {
            const changed = document.getElementById('ft-content-en').value !== originalContentEn
                         || document.getElementById('ft-content-ar').value !== originalContentAr;
            const isEdit = !!document.getElementById('ft-id').value;
            document.getElementById('ft-version-note').classList.toggle('hidden', !(isEdit && changed));
        });
    });

    document.querySelectorAll('.btn-edit-template').forEach(btn => {
        btn.addEventListener('click', () => {
            const tpl = JSON.parse(btn.dataset.template);
            document.getElementById('ft-id').value          = tpl.id;
            document.getElementById('ft-name').value        = tpl.name ?? '';
            document.getElementById('ft-content-en').value  = tpl.content_en ?? '';
            document.getElementById('ft-content-ar').value  = tpl.content_ar ?? '';
            document.getElementById('ft-is-active').checked = !!tpl.is_active;
            originalContentEn = tpl.content_en ?? '';
            originalContentAr = tpl.content_ar ?? '';
            document.getElementById('ft-error').classList.add('hidden');
            document.getElementById('ft-version-note').classList.add('hidden');
            document.querySelector('#template-modal [id$="-title"]').textContent = window.TRANSLATIONS.edit_template_title;
            openModal('template-modal');
        });
    });

    document.getElementById('btn-save-template').addEventListener('click', async () => {
        const id = document.getElementById('ft-id').value;
        const body = {
            name:       document.getElementById('ft-name').value,
            content_en: document.getElementById('ft-content-en').value,
            content_ar: document.getElementById('ft-content-ar').value,
            is_active:  document.getElementById('ft-is-active').checked ? 1 : 0,
        };

        const url    = id ? `${base}/${id}` : base;
        const method = id ? 'PUT' : 'POST';
        const { ok, data } = await request(url, method, body);

        if (ok) {
            closeModal('template-modal');
            location.reload();
        } else {
            const errEl = document.getElementById('ft-error');
            errEl.textContent = data.message ?? (data.errors ? Object.values(data.errors).flat().join(' ') : window.TRANSLATIONS.error_occurred);
            errEl.classList.remove('hidden');
        }
    });

    document.querySelectorAll('.btn-delete-template').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('del-tpl-id').value        = btn.dataset.id;
            document.getElementById('del-tpl-name').textContent = btn.dataset.name;
            document.getElementById('del-tpl-error').classList.add('hidden');
            openModal('delete-template-modal');
        });
    });

    document.getElementById('btn-confirm-del-tpl').addEventListener('click', async () => {
        const id = document.getElementById('del-tpl-id').value;
        const { ok, data } = await request(`${base}/${id}`, 'DELETE');
        if (ok) {
            closeModal('delete-template-modal');
            location.reload();
        } else {
            const errEl = document.getElementById('del-tpl-error');
            errEl.textContent = data.message ?? window.TRANSLATIONS.delete_failed;
            errEl.classList.remove('hidden');
        }
    });
})();
</script>
@endpush
@endsection
