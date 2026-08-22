@extends('layouts.admin')

@section('title', __('admin.shipping_section.title'))

@section('content')

{{-- ─── Page Header ─────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.shipping_section.shipping_companies') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.shipping_section.companies_desc') }}</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.shipping-companies.fallback-rules.index') }}"
           class="btn btn-secondary btn-sm">
            {{ __('admin.shipping_section.fallback_rules') }}
        </a>
        <button type="button" id="btn-add-company" class="btn btn-primary btn-sm">
            + {{ __('admin.shipping_section.add_company') }}
        </button>
    </div>
</div>

{{-- ─── Stats ───────────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['label' => __('admin.shipping_section.stat_total'),     'value' => $stats['total'],     'color' => 'gray'],
        ['label' => __('admin.shipping_section.stat_active'),    'value' => $stats['active'],    'color' => 'success'],
        ['label' => __('admin.shipping_section.stat_pending'),   'value' => $stats['pending'],   'color' => 'warning'],
        ['label' => __('admin.shipping_section.stat_suspended'), 'value' => $stats['suspended'], 'color' => 'danger'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-black text-{{ $stat['color'] === 'gray' ? 'gray-700' : ($stat['color'] === 'success' ? 'emerald-600' : ($stat['color'] === 'warning' ? 'amber-600' : 'red-600')) }}">
            {{ $stat['value'] }}
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- ─── Table ───────────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.company_col') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.country_col') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.contact_col') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.supervisors_col') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.agents_col') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.status_col') }}</th>
                    <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.actions_col') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($companies as $company)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-900">{{ $company->name }}</div>
                        @if($company->legal_name)
                        <div class="text-xs text-gray-400">{{ $company->legal_name }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $company->country?->name_en ?? '—' }}</td>
                    <td class="px-6 py-4">
                        <div class="text-gray-700">{{ $company->contact_email }}</div>
                        @if($company->contact_phone)
                        <div class="text-xs text-gray-400">{{ $company->contact_phone }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center font-semibold text-gray-700">{{ $company->supervisors_count }}</td>
                    <td class="px-6 py-4 text-center font-semibold text-gray-700">{{ $company->agents_count }}</td>
                    <td class="px-6 py-4">
                        @php
                            $sc = ['active'=>'emerald','pending'=>'amber','suspended'=>'red'][$company->status->value] ?? 'gray';
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700 capitalize">
                            {{ __('admin.shipping_section.company_status_' . $company->status->value) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.shipping-companies.show', $company->id) }}"
                               class="text-indigo-600 hover:underline text-xs font-medium">{{ __('admin.shipping_section.view') }}</a>
                            <button type="button"
                                    class="btn-edit-company text-indigo-600 hover:underline text-xs font-medium"
                                    data-id="{{ $company->id }}"
                                    data-name="{{ $company->name }}"
                                    data-legal-name="{{ $company->legal_name }}"
                                    data-country-id="{{ $company->country_id }}"
                                    data-contact-email="{{ $company->contact_email }}"
                                    data-contact-phone="{{ $company->contact_phone }}"
                                    data-served-countries="{{ json_encode($company->served_countries ?? []) }}"
                                    data-can-notify="{{ $company->can_supervisors_receive_all_notifications ? '1' : '0' }}"
                                    data-logo="{{ $company->logo_path ? \Illuminate\Support\Facades\Storage::url($company->logo_path) : '' }}">
                                {{ __('admin.shipping_section.edit') }}
                            </button>
                            <button type="button"
                                    class="btn-delete-company text-red-500 hover:underline text-xs font-medium"
                                    data-id="{{ $company->id }}"
                                    data-name="{{ $company->name }}">
                                {{ __('admin.shipping_section.delete') }}
                            </button>
                            @if($company->status === \App\Enums\ShippingCompanyStatus::Pending)
                            <form method="POST" action="{{ route('admin.shipping-companies.approve', $company->id) }}">
                                @csrf
                                <button class="text-emerald-600 hover:underline text-xs font-medium">{{ __('admin.shipping_section.approve') }}</button>
                            </form>
                            @endif
                            @if($company->status !== \App\Enums\ShippingCompanyStatus::Suspended)
                            <form method="POST" action="{{ route('admin.shipping-companies.suspend', $company->id) }}"
                                  onsubmit="return confirm('{{ __('admin.shipping_section.suspend_confirm') }}')">
                                @csrf
                                <button class="text-red-500 hover:underline text-xs font-medium">{{ __('admin.shipping_section.suspend') }}</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-400 text-sm">{{ __('admin.no_shipping_companies') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($companies->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $companies->links() }}
    </div>
    @endif
</div>

{{-- ─── Create / Edit Company Modal ───────────────────────────────────────── --}}
<div id="company-modal" class="modal-backdrop hidden">
    <div class="modal-box w-full max-w-2xl !p-0 overflow-hidden">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="company-modal-title" class="text-lg font-semibold text-gray-900">
                {{ __('admin.shipping_section.add_company') }}
            </h3>
            <button type="button" id="company-modal-close"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <form id="company-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="company-form-method" value="POST">
            <input type="hidden" id="company-id" value="">

            <div class="px-6 py-5 space-y-5 max-h-[72vh] overflow-y-auto">

                {{-- Basic Info --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                        {{ __('admin.shipping_section.section_basic_info') }}
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.company_name') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="field-name" class="form-input w-full" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.legal_name') }}
                            </label>
                            <input type="text" name="legal_name" id="field-legal-name" class="form-input w-full">
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.contact_email') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="contact_email" id="field-contact-email" class="form-input w-full" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.phone') }}
                            </label>
                            <input type="text" name="contact_phone" id="field-contact-phone" class="form-input w-full">
                        </div>
                    </div>
                </div>

                {{-- Geography --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                        {{ __('admin.shipping_section.section_geography') }}
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.home_country') }}
                            </label>
                            <select name="country_id" id="field-country" class="form-input w-full">
                                <option value="">— {{ __('admin.shipping_section.select_country') }} —</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name_en }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.home_country_note') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.status_label') }} <span class="text-red-500">*</span>
                            </label>
                            <select name="status" id="field-status" class="form-input w-full" required>
                                <option value="pending">{{ __('admin.shipping_section.company_status_pending') }}</option>
                                <option value="active">{{ __('admin.shipping_section.company_status_active') }}</option>
                                <option value="suspended">{{ __('admin.shipping_section.company_status_suspended') }}</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.served_countries') }}
                            </label>
                            <select name="served_countries[]" id="field-served-countries"
                                    multiple class="form-input w-full" style="min-height: 100px;">
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">
                                        {{ $country->name_en }} ({{ $country->currency_code }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.served_countries_note') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Logo --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                        {{ __('admin.shipping_section.section_logo') }}
                    </h4>
                    <div id="current-logo-wrap" class="hidden mb-3">
                        <img id="current-logo-img" src="" alt="Logo"
                             class="h-14 w-auto object-contain rounded border border-gray-200 p-1">
                        <label class="flex items-center gap-2 mt-2 text-sm text-red-500 cursor-pointer">
                            <input type="checkbox" name="remove_logo" value="1" id="field-remove-logo"
                                   class="rounded border-gray-300">
                            {{ __('admin.shipping_section.remove_logo') }}
                        </label>
                    </div>
                    <input type="file" name="logo" id="field-logo"
                           accept="image/*" class="form-input w-full text-sm">
                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.logo_hint') }}</p>
                </div>

                {{-- Settings --}}
                <div>
                    <label class="flex items-center gap-3 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="can_supervisors_receive_all_notifications"
                               id="field-can-notify" value="1"
                               class="rounded border-gray-300 text-primary-600" checked>
                        {{ __('admin.shipping_section.can_supervisors_receive_all_notifications_label') }}
                    </label>
                    <p class="text-xs text-gray-400 mt-1 ml-6">
                        {{ __('admin.shipping_section.can_supervisors_receive_all_notifications_note') }}
                    </p>
                </div>

            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                <button type="button" id="company-modal-cancel" class="btn btn-ghost btn-sm">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" id="company-form-submit" class="btn btn-primary btn-sm">
                    {{ __('admin.shipping_section.create_company') }}
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
const BASE_URL    = @json(url('shipping-companies'));
const CSRF        = () => document.querySelector('meta[name="csrf-token"]').content;
const modal       = document.getElementById('company-modal');
const form        = document.getElementById('company-form');
const submitBtn   = document.getElementById('company-form-submit');
const modalTitle  = document.getElementById('company-modal-title');

document.getElementById('btn-add-company').addEventListener('click', () => {
    resetForm();
    document.getElementById('company-id').value = '';
    document.getElementById('company-form-method').value = 'POST';
    modalTitle.textContent = @json(__('admin.shipping_section.add_company'));
    submitBtn.textContent  = @json(__('admin.shipping_section.create_company'));
    modal.classList.remove('hidden');
});

document.querySelectorAll('.btn-edit-company').forEach(btn => {
    btn.addEventListener('click', () => {
        resetForm();

        document.getElementById('company-id').value             = btn.dataset.id;
        document.getElementById('company-form-method').value    = 'PUT';
        document.getElementById('field-name').value             = btn.dataset.name;
        document.getElementById('field-legal-name').value       = btn.dataset.legalName || '';
        document.getElementById('field-country').value          = btn.dataset.countryId || '';
        document.getElementById('field-contact-email').value    = btn.dataset.contactEmail;
        document.getElementById('field-contact-phone').value    = btn.dataset.contactPhone || '';
        document.getElementById('field-can-notify').checked     = btn.dataset.canNotify === '1';

        document.getElementById('field-status').closest('div').style.display = 'none';

        const served = JSON.parse(btn.dataset.servedCountries || '[]');
        [...document.getElementById('field-served-countries').options].forEach(opt => {
            opt.selected = served.includes(opt.value);
        });

        const logoUrl = btn.dataset.logo;
        if (logoUrl) {
            document.getElementById('current-logo-img').src = logoUrl;
            document.getElementById('current-logo-wrap').classList.remove('hidden');
        }

        modalTitle.textContent = @json(__('admin.shipping_section.edit_company'));
        submitBtn.textContent  = @json(__('common.save'));
        modal.classList.remove('hidden');
    });
});

['company-modal-close', 'company-modal-cancel'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => modal.classList.add('hidden'));
});

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    submitBtn.disabled = true;

    const companyId = document.getElementById('company-id').value;
    const isEdit    = !!companyId;
    const url       = isEdit ? `${BASE_URL}/${companyId}` : BASE_URL;

    const formData  = new FormData(form);
    if (isEdit) formData.set('_method', 'PUT');

    if (!document.getElementById('field-can-notify').checked) {
        formData.set('can_supervisors_receive_all_notifications', '0');
    }

    try {
        const res  = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json' },
            body: formData,
        });
        const data = await res.json();

        if (data.success) {
            window.Toast?.success(data.message);
            if (data.redirect) {
                setTimeout(() => window.location.href = data.redirect, 800);
            } else {
                setTimeout(() => location.reload(), 800);
            }
        } else {
            const errors = data.errors ? Object.values(data.errors).flat().join('\n') : data.message;
            window.Toast?.error(errors || @json(__('admin.shipping_section.failed_to_save')));
            submitBtn.disabled = false;
        }
    } catch {
        window.Toast?.error(@json(__('admin.shipping_section.failed_to_save')));
        submitBtn.disabled = false;
    }
});

document.querySelectorAll('.btn-delete-company').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm(`${@json(__('admin.shipping_section.delete_company_confirm'))} "${btn.dataset.name}"?`)) return;

        const res  = await fetch(`${BASE_URL}/${btn.dataset.id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json' },
        });
        const data = await res.json();

        if (data.success) {
            window.Toast?.success(data.message);
            setTimeout(() => location.reload(), 800);
        } else {
            window.Toast?.error(data.message);
        }
    });
});

function resetForm() {
    form.reset();
    document.getElementById('company-id').value = '';
    document.getElementById('current-logo-wrap').classList.add('hidden');
    document.getElementById('current-logo-img').src = '';
    document.getElementById('field-status').closest('div').style.display = '';
    document.getElementById('field-can-notify').checked = true;
    [...document.getElementById('field-served-countries').options].forEach(o => o.selected = false);
}
</script>
@endpush
