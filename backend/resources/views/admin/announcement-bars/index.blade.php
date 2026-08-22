@extends('layouts.admin')

@section('title', __('admin.announcement_bars.title'))

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.announcement_bars.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.announcement_bars.subtitle') }}</p>
        </div>
        <button type="button" id="btn-new-bar"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.announcement_bars.new_bar') }}
        </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.announcement_bars.name') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.announcement_bars.country') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.announcement_bars.message_english') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.announcement_bars.priority') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.announcement_bars.status') }}</th>
                        <th class="px-4 py-3 text-end font-semibold text-gray-700">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody id="bar-list" class="divide-y divide-gray-100">
                    @forelse($bars as $bar)
                        @php
                            $now = now();
                            if (!$bar->is_active) {
                                $statusKey = 'inactive';
                                $statusColor = 'gray';
                            } elseif ($bar->starts_at && $bar->starts_at->isFuture()) {
                                $statusKey = 'scheduled';
                                $statusColor = 'primary';
                            } elseif ($bar->ends_at && $bar->ends_at->isPast()) {
                                $statusKey = 'inactive';
                                $statusColor = 'gray';
                            } else {
                                $statusKey = 'live';
                                $statusColor = 'success';
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 bar-row" data-id="{{ $bar->id }}">
                            <td class="px-4 py-3 max-w-xs truncate font-medium text-gray-900">{{ $bar->name }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if($bar->country)
                                    {{ $bar->country->flag_emoji }} {{ $bar->country->name_en }}
                                @else
                                    <span class="text-gray-400 text-xs">{{ __('admin.announcement_bars.all_countries') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 max-w-sm truncate text-gray-600">{{ $bar->message_en }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $bar->priority }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-700">
                                    {{ __('admin.announcement_bars.' . $statusKey) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button type="button" dir="ltr"
                                        class="btn-toggle-active relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none align-middle me-2 {{ $bar->is_active ? 'bg-emerald-500' : 'bg-gray-200' }}"
                                        data-id="{{ $bar->id }}" aria-label="{{ __('admin.announcement_bars.toggle_active') }}">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform {{ $bar->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                </button>
                                <button type="button" class="btn-edit-bar text-xs text-primary-600 font-medium hover:underline"
                                        data-bar="{{ json_encode($bar->only(['id','country_id','name','message_en','message_ar','cta_label_en','cta_label_ar','cta_url','bg_color_hex','text_color_hex','is_dismissible','starts_at','ends_at','priority','is_active'])) }}">
                                    {{ __('common.edit') }}
                                </button>
                                <button type="button" class="btn-delete-bar ms-2 text-xs text-red-500 hover:underline"
                                        data-id="{{ $bar->id }}" data-name="{{ $bar->name }}">
                                    {{ __('common.delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('admin.announcement_bars.empty_state') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create / Edit modal --}}
<x-modal id="bar-modal" title="{{ __('admin.announcement_bars.bar') }}" size="lg">
    <form id="bar-form" class="space-y-4">
        @csrf
        <input type="hidden" id="form-bar-id" value="">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.name') }} <span class="text-red-500">*</span></label>
                <input type="text" id="f-name" required maxlength="150" class="form-input w-full text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.country') }}</label>
                <select id="f-country-id" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.announcement_bars.all_countries') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->flag_emoji }} {{ $country->name_en }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.message_english') }} <span class="text-red-500">*</span></label>
                <textarea id="f-message-en" required rows="2" maxlength="500" class="form-input w-full text-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.message_arabic') }} <span class="text-red-500">*</span></label>
                <textarea id="f-message-ar" required rows="2" maxlength="500" dir="rtl" class="form-input w-full text-sm"></textarea>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.cta_label_english') }}</label>
                <input type="text" id="f-cta-label-en" maxlength="100" class="form-input w-full text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.cta_label_arabic') }}</label>
                <input type="text" id="f-cta-label-ar" maxlength="100" dir="rtl" class="form-input w-full text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.cta_url') }}</label>
            <input type="text" id="f-cta-url" maxlength="500" class="form-input w-full text-sm">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.bg_color') }}</label>
                <input type="color" id="f-bg-color" value="#111827" class="form-input w-full h-9">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.text_color') }}</label>
                <input type="color" id="f-text-color" value="#ffffff" class="form-input w-full h-9">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.starts_at') }}</label>
                <input type="datetime-local" id="f-starts-at" class="form-input w-full text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.ends_at') }}</label>
                <input type="datetime-local" id="f-ends-at" class="form-input w-full text-sm">
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.announcement_bars.priority') }}</label>
                <input type="number" id="f-priority" value="0" min="0" class="form-input w-28 text-sm">
            </div>
            <label class="flex items-center gap-2 cursor-pointer select-none mt-4">
                <input type="checkbox" id="f-is-dismissible" class="rounded text-primary-600" checked>
                <span class="text-sm text-gray-700">{{ __('admin.announcement_bars.is_dismissible') }}</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none mt-4">
                <input type="checkbox" id="f-is-active" class="rounded text-primary-600">
                <span class="text-sm text-gray-700">{{ __('common.active') }}</span>
            </label>
        </div>

        <div id="form-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    </form>

    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-save-bar" class="btn btn-primary">{{ __('common.save') }}</button>
    </x-slot:footer>
</x-modal>

{{-- Delete confirmation modal --}}
<x-modal id="delete-modal" title="{{ __('admin.announcement_bars.delete_bar') }}" size="sm">
    <p class="text-sm text-gray-700">{{ __('admin.announcement_bars.delete_confirm') }} "<strong id="delete-bar-name"></strong>"?</p>
    <input type="hidden" id="delete-bar-id">
    <div id="delete-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-delete" class="btn btn-danger">{{ __('common.delete') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const base = '{{ rtrim(route("admin.announcement-bars.index"), "/") }}';

    function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

    document.querySelectorAll('[data-modal-close]').forEach(btn =>
        btn.addEventListener('click', () => btn.closest('[id]')?.classList.add('hidden'))
    );

    async function req(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, data };
    }

    function toDatetimeLocal(value) {
        if (!value) return '';
        const d = new Date(value);
        if (isNaN(d.getTime())) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    function resetForm() {
        document.getElementById('form-bar-id').value = '';
        ['f-name', 'f-message-en', 'f-message-ar', 'f-cta-label-en', 'f-cta-label-ar', 'f-cta-url', 'f-starts-at', 'f-ends-at'].forEach(id => {
            document.getElementById(id).value = '';
        });
        document.getElementById('f-country-id').value = '';
        document.getElementById('f-bg-color').value = '#111827';
        document.getElementById('f-text-color').value = '#ffffff';
        document.getElementById('f-priority').value = '0';
        document.getElementById('f-is-dismissible').checked = true;
        document.getElementById('f-is-active').checked = false;
        document.getElementById('form-error').classList.add('hidden');
    }

    const i18n = {
        newBar: @json(__('admin.announcement_bars.new_bar_title')),
        editBar: @json(__('admin.announcement_bars.edit_bar_title')),
        couldNotDelete: @json(__('admin.announcement_bars.could_not_delete')),
    };

    document.getElementById('btn-new-bar').addEventListener('click', () => {
        resetForm();
        document.querySelector('#bar-modal [id$="-title"]').textContent = i18n.newBar;
        openModal('bar-modal');
    });

    document.querySelectorAll('.btn-edit-bar').forEach(btn => {
        btn.addEventListener('click', () => {
            const bar = JSON.parse(btn.dataset.bar);
            document.getElementById('form-bar-id').value = bar.id;
            document.getElementById('f-country-id').value = bar.country_id ?? '';
            document.getElementById('f-name').value = bar.name;
            document.getElementById('f-message-en').value = bar.message_en;
            document.getElementById('f-message-ar').value = bar.message_ar;
            document.getElementById('f-cta-label-en').value = bar.cta_label_en ?? '';
            document.getElementById('f-cta-label-ar').value = bar.cta_label_ar ?? '';
            document.getElementById('f-cta-url').value = bar.cta_url ?? '';
            document.getElementById('f-bg-color').value = bar.bg_color_hex ?? '#111827';
            document.getElementById('f-text-color').value = bar.text_color_hex ?? '#ffffff';
            document.getElementById('f-starts-at').value = toDatetimeLocal(bar.starts_at);
            document.getElementById('f-ends-at').value = toDatetimeLocal(bar.ends_at);
            document.getElementById('f-priority').value = bar.priority ?? 0;
            document.getElementById('f-is-dismissible').checked = !!bar.is_dismissible;
            document.getElementById('f-is-active').checked = !!bar.is_active;
            document.querySelector('#bar-modal [id$="-title"]').textContent = i18n.editBar;
            document.getElementById('form-error').classList.add('hidden');
            openModal('bar-modal');
        });
    });

    document.getElementById('btn-save-bar').addEventListener('click', async () => {
        const id = document.getElementById('form-bar-id').value;
        const payload = {
            country_id: document.getElementById('f-country-id').value || null,
            name: document.getElementById('f-name').value,
            message_en: document.getElementById('f-message-en').value,
            message_ar: document.getElementById('f-message-ar').value,
            cta_label_en: document.getElementById('f-cta-label-en').value || null,
            cta_label_ar: document.getElementById('f-cta-label-ar').value || null,
            cta_url: document.getElementById('f-cta-url').value || null,
            bg_color_hex: document.getElementById('f-bg-color').value,
            text_color_hex: document.getElementById('f-text-color').value,
            starts_at: document.getElementById('f-starts-at').value || null,
            ends_at: document.getElementById('f-ends-at').value || null,
            priority: parseInt(document.getElementById('f-priority').value) || 0,
            is_dismissible: document.getElementById('f-is-dismissible').checked ? 1 : 0,
            is_active: document.getElementById('f-is-active').checked ? 1 : 0,
        };

        const url = id ? `${base}/${id}` : base;
        const method = id ? 'PUT' : 'POST';
        const errEl = document.getElementById('form-error');

        const { ok, data } = await req(url, method, payload);
        if (ok) {
            closeModal('bar-modal');
            window.location.reload();
        } else {
            errEl.textContent = data.message ?? Object.values(data.errors ?? {}).flat().join(' ');
            errEl.classList.remove('hidden');
        }
    });

    document.querySelectorAll('.btn-delete-bar').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete-bar-name').textContent = btn.dataset.name;
            document.getElementById('delete-bar-id').value = btn.dataset.id;
            document.getElementById('delete-error').classList.add('hidden');
            openModal('delete-modal');
        });
    });

    document.getElementById('btn-confirm-delete').addEventListener('click', async () => {
        const id = document.getElementById('delete-bar-id').value;
        const { ok, data } = await req(`${base}/${id}`, 'DELETE');
        if (ok) {
            closeModal('delete-modal');
            window.location.reload();
        } else {
            const errEl = document.getElementById('delete-error');
            errEl.textContent = data.message ?? i18n.couldNotDelete;
            errEl.classList.remove('hidden');
        }
    });

    document.querySelectorAll('.btn-toggle-active').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const { ok } = await req(`${base}/${id}/toggle`, 'POST');
            if (ok) {
                window.location.reload();
            }
        });
    });
})();
</script>
@endpush
@endsection
