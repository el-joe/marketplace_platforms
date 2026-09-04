@extends('layouts.admin')
@section('title', __('admin.classified_attributes.title'))

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.classified_attributes.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.classified_attributes.subtitle') }}</p>
        </div>
        <button type="button" id="btn-new-attr"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" /> {{ __('admin.classified_attributes.new_attribute') }}
        </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classified_attributes.code') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classified_attributes.label') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classified_attributes.type') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classified_attributes.options_count') }}</th>
                    <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classified_attributes.unit') }}</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('common.active') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="attr-table-body">
                @forelse($definitions as $def)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <code class="text-xs bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-mono">{{ $def->code }}</code>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $def->label_en }}</div>
                        <div class="text-xs text-gray-400" dir="rtl">{{ $def->label_ar }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                            {{ match($def->input_type) { 'select'=>'bg-blue-50 text-blue-700', 'number'=>'bg-purple-50 text-purple-700', 'boolean'=>'bg-amber-50 text-amber-700', default=>'bg-gray-100 text-gray-600' } }}">
                            {{ $def->input_type }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ $def->input_type === 'select' ? count($def->options ?? []).' options' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ $def->unit_en ? $def->unit_en.' / '.$def->unit_ar : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($def->is_active)
                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('common.yes') }}</span>
                        @else
                            <span class="text-gray-300 text-xs">{{ __('common.no') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-end whitespace-nowrap">
                        <button type="button" class="btn-edit-attr text-xs text-primary-600 font-medium hover:underline"
                                data-def="{{ json_encode($def->only(['id','code','label_en','label_ar','input_type','options','unit_en','unit_ar','sort_order','is_active'])) }}">
                            {{ __('common.edit') }}
                        </button>
                        <button type="button" class="btn-del-attr ms-2 text-xs text-red-500 hover:underline"
                                data-id="{{ $def->id }}" data-name="{{ $def->label_en }}">
                            {{ __('common.delete') }}
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">{{ __('admin.classified_attributes.no_attributes_yet') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal --}}
<x-modal id="attr-modal" title="{{ __('admin.classified_attributes.modal_title') }}" size="lg">
    <form id="attr-form" class="space-y-4">
        @csrf
        <input type="hidden" id="attr-id">

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classified_attributes.code') }} <span class="text-red-500">*</span></label>
                <input type="text" name="code" id="f-code" required maxlength="60" pattern="[a-z0-9_]+"
                       class="form-input w-full text-sm font-mono" placeholder="e.g. year, km, brand">
                <p class="mt-0.5 text-xs text-gray-400">{{ __('admin.classified_attributes.code_hint') }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classified_attributes.label_en') }} <span class="text-red-500">*</span></label>
                <input type="text" name="label_en" id="f-label-en" required maxlength="120" dir="ltr" class="form-input w-full text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classified_attributes.label_ar') }} <span class="text-red-500">*</span></label>
                <input type="text" name="label_ar" id="f-label-ar" required maxlength="120" dir="rtl" class="form-input w-full text-sm">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classified_attributes.type') }} <span class="text-red-500">*</span></label>
                <select name="input_type" id="f-type" class="form-input w-full text-sm">
                    <option value="text">text</option>
                    <option value="number">number</option>
                    <option value="select">select (dropdown)</option>
                    <option value="boolean">boolean (yes/no)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classified_attributes.unit_en') }}</label>
                <input type="text" name="unit_en" id="f-unit-en" maxlength="30" dir="ltr" class="form-input w-full text-sm" placeholder="km, m², year">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.classified_attributes.unit_ar') }}</label>
                <input type="text" name="unit_ar" id="f-unit-ar" maxlength="30" dir="rtl" class="form-input w-full text-sm" placeholder="كم، م²، سنة">
            </div>
        </div>

        <div id="opts-builder" class="hidden space-y-2">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-medium text-gray-700">{{ __('admin.classified_attributes.options') }}</label>
                <button type="button" id="btn-add-opt" class="text-xs text-primary-600 hover:underline">+ Add option</button>
            </div>
            <div class="grid grid-cols-3 gap-1 text-xs text-gray-400 px-1">
                <span>Machine value</span><span>English label</span><span>Arabic label</span>
            </div>
            <div id="opts-list" class="space-y-1"></div>
            <p class="text-xs text-gray-400">{{ __('admin.classified_attributes.options_hint') }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" id="f-active" name="is_active" value="1" checked class="rounded border-gray-300 text-primary-600">
            {{ __('common.active') }}
        </label>

        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
            <button type="button" data-modal-close="attr-modal"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">{{ __('common.cancel') }}</button>
            <button type="submit"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">{{ __('common.save') }}</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

    // Show/hide options builder
    document.getElementById('f-type').addEventListener('change', function () {
        document.getElementById('opts-builder').classList.toggle('hidden', this.value !== 'select');
    });

    // Add option row
    function addOptRow(v = '', en = '', ar = '') {
        const row = document.createElement('div');
        row.className = 'flex gap-1 items-center opt-row';
        row.innerHTML = `
            <input class="form-input text-xs flex-1 font-mono opt-v" placeholder="value" value="${v}">
            <input class="form-input text-xs flex-1 opt-en" placeholder="English" dir="ltr" value="${en}">
            <input class="form-input text-xs flex-1 opt-ar" placeholder="عربي" dir="rtl" value="${ar}">
            <button type="button" class="text-red-400 hover:text-red-600 text-lg px-1" onclick="this.closest('.opt-row').remove()">×</button>
        `;
        document.getElementById('opts-list').appendChild(row);
    }
    document.getElementById('btn-add-opt')?.addEventListener('click', () => addOptRow());

    function collectOpts() {
        return [...document.querySelectorAll('#opts-list .opt-row')].map(r => ({
            value:    r.querySelector('.opt-v').value.trim(),
            label_en: r.querySelector('.opt-en').value.trim(),
            label_ar: r.querySelector('.opt-ar').value.trim(),
        })).filter(o => o.value);
    }

    function openModal(def = null) {
        document.getElementById('attr-id').value       = def?.id ?? '';
        document.getElementById('f-code').value        = def?.code ?? '';
        document.getElementById('f-label-en').value    = def?.label_en ?? '';
        document.getElementById('f-label-ar').value    = def?.label_ar ?? '';
        document.getElementById('f-type').value        = def?.input_type ?? 'text';
        document.getElementById('f-unit-en').value     = def?.unit_en ?? '';
        document.getElementById('f-unit-ar').value     = def?.unit_ar ?? '';
        document.getElementById('f-active').checked    = def ? !!def.is_active : true;
        document.getElementById('opts-list').innerHTML = '';
        document.getElementById('opts-builder').classList.toggle('hidden', def?.input_type !== 'select');
        if (def?.input_type === 'select') (def.options || []).forEach(o => addOptRow(o.value, o.label_en, o.label_ar));
        document.getElementById('f-code').disabled = !!def?.id; // code is immutable after creation
        document.getElementById('attr-modal')?.classList.remove('hidden');
    }

    document.getElementById('btn-new-attr')?.addEventListener('click', () => openModal());
    document.addEventListener('click', e => {
        const btn = e.target.closest('.btn-edit-attr');
        if (btn) openModal(JSON.parse(btn.dataset.def));
    });

    document.getElementById('attr-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('attr-id').value;
        const res = await fetch(id ? `{{ url('admin/classifieds/attributes') }}/${id}` : '{{ route("admin.classifieds.attributes.store") }}', {
            method:  id ? 'PUT' : 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                code:       document.getElementById('f-code').value,
                label_en:   document.getElementById('f-label-en').value,
                label_ar:   document.getElementById('f-label-ar').value,
                input_type: document.getElementById('f-type').value,
                unit_en:    document.getElementById('f-unit-en').value || null,
                unit_ar:    document.getElementById('f-unit-ar').value || null,
                is_active:  document.getElementById('f-active').checked,
                options:    collectOpts(),
            }),
        }).then(r => r.json());
        if (res.definition) { window.Toast?.success(res.message); location.reload(); }
        else { window.Toast?.error(Object.values(res.errors ?? {})[0]?.[0] ?? res.message ?? 'Error'); }
    });

    document.addEventListener('click', async e => {
        const btn = e.target.closest('.btn-del-attr');
        if (!btn || !confirm(`Delete "${btn.dataset.name}"?`)) return;
        const res = await fetch(`{{ url('admin/classifieds/attributes') }}/${btn.dataset.id}`, {
            method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
        }).then(r => r.json());
        if (res.message && !res.errors) { window.Toast?.success(res.message); btn.closest('tr')?.remove(); }
        else window.Toast?.error(res.message ?? 'Cannot delete');
    });
})();
</script>
@endpush
