@extends('layouts.admin')

@section('title', $shippingCompany->name)

@section('content')

{{-- ─── Header ──────────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <a href="{{ route('admin.shipping-companies.index') }}"
           class="text-sm text-indigo-600 hover:underline">{{ __('admin.shipping_section.back_to_shipping_companies') }}</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $shippingCompany->name }}</h1>
        @if($shippingCompany->legal_name)
        <p class="text-sm text-gray-500">{{ $shippingCompany->legal_name }}</p>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <button type="button" id="btn-edit-company"
                class="btn btn-secondary btn-sm"
                data-id="{{ $shippingCompany->id }}"
                data-name="{{ $shippingCompany->name }}"
                data-legal-name="{{ $shippingCompany->legal_name }}"
                data-country-id="{{ $shippingCompany->country_id }}"
                data-contact-email="{{ $shippingCompany->contact_email }}"
                data-contact-phone="{{ $shippingCompany->contact_phone }}"
                data-served-countries="{{ json_encode($shippingCompany->served_countries ?? []) }}"
                data-can-notify="{{ $shippingCompany->can_supervisors_receive_all_notifications ? '1' : '0' }}"
                data-logo="{{ $shippingCompany->logo_path ? \Illuminate\Support\Facades\Storage::url($shippingCompany->logo_path) : '' }}">
            {{ __('admin.shipping_section.edit_company') }}
        </button>
        @if($shippingCompany->status === \App\Enums\ShippingCompanyStatus::Pending)
        <form method="POST" action="{{ route('admin.shipping-companies.approve', $shippingCompany->id) }}">
            @csrf
            <button class="btn btn-success btn-sm">{{ __('admin.shipping_section.approve') }}</button>
        </form>
        @endif
        @if($shippingCompany->status !== \App\Enums\ShippingCompanyStatus::Suspended)
        <form method="POST" action="{{ route('admin.shipping-companies.suspend', $shippingCompany->id) }}"
              onsubmit="return confirm('{{ __('admin.shipping_section.suspend_confirm') }}')">
            @csrf
            <button class="btn btn-danger btn-sm">{{ __('admin.shipping_section.suspend') }}</button>
        </form>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ─── Info Card ──────────────────────────────────────────────────────── --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">{{ __('admin.company_details') }}</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-gray-500 text-xs uppercase tracking-wide">{{ __('admin.shipping_section.company_details_status') }}</dt>
                    <dd class="mt-0.5">
                        @php $sc = ['active'=>'emerald','pending'=>'amber','suspended'=>'red'][$shippingCompany->status->value] ?? 'gray'; @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700 capitalize">
                            {{ __('admin.shipping_section.company_status_' . $shippingCompany->status->value) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 text-xs uppercase tracking-wide">{{ __('admin.shipping_section.company_details_country') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $shippingCompany->country?->name_en ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 text-xs uppercase tracking-wide">{{ __('admin.shipping_section.contact_email') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $shippingCompany->contact_email }}</dd>
                </div>
                @if($shippingCompany->contact_phone)
                <div>
                    <dt class="text-gray-500 text-xs uppercase tracking-wide">{{ __('admin.shipping_section.phone') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $shippingCompany->contact_phone }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-gray-500 text-xs uppercase tracking-wide">{{ __('admin.shipping_section.supervisor_notifications_label') }}</dt>
                    <dd class="font-medium {{ $shippingCompany->can_supervisors_receive_all_notifications ? 'text-emerald-600' : 'text-gray-400' }}">
                        {{ $shippingCompany->can_supervisors_receive_all_notifications ? __('admin.shipping_section.enabled_company_level') : __('admin.shipping_section.disabled') }}
                    </dd>
                </div>
                @if($shippingCompany->approved_at)
                <div>
                    <dt class="text-gray-500 text-xs uppercase tracking-wide">{{ __('admin.shipping_section.approved_at') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $shippingCompany->approved_at->format('Y-m-d H:i') }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- ─── Supervisors ─────────────────────────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-bold text-gray-900">{{ __('admin.shipping_section.supervisors') }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.shipping_section.supervisors_desc') }}</p>
                    </div>
                    <button type="button" id="btn-add-supervisor"
                            class="btn btn-primary btn-sm">
                        + {{ __('admin.shipping_section.add_supervisor') }}
                    </button>
                </div>
            </div>
            @if($shippingCompany->supervisors->isEmpty())
            <div class="px-6 py-8 text-center text-gray-400 text-sm">{{ __('admin.shipping_section.no_supervisors') }}</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.name_col') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.email_col') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('common.country') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.permissions_col') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.active_col') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.notifications_col') }}</th>
                            <th class="px-6 py-3 text-end font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($shippingCompany->supervisors as $sup)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $sup->name }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $sup->email }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $sup->country?->name_en ?? '—' }}</td>
                            <td class="px-6 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($sup->permissions ?? [] as $perm)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $perm }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $sup->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' }}">
                                    {{ $sup->is_active ? __('common.yes') : __('common.no') }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <button type="button"
                                        onclick="toggleNotifications('{{ $sup->id }}', this)"
                                        data-url="{{ route('admin.shipping-companies.supervisors.toggle-notifications', $sup->id) }}"
                                        data-on-label="{{ __('admin.shipping_section.notif_on') }}"
                                        data-off-label="{{ __('admin.shipping_section.notif_off') }}"
                                        class="text-xs font-semibold px-3 py-1 rounded-full transition
                                            {{ $sup->receives_all_notifications
                                                ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                                                : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
                                    {{ $sup->receives_all_notifications ? __('admin.shipping_section.notif_on') : __('admin.shipping_section.notif_off') }}
                                </button>
                            </td>
                            <td class="px-6 py-3 text-end">
                                <button type="button"
                                        class="btn-edit-supervisor text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                        data-id="{{ $sup->id }}"
                                        data-name="{{ $sup->name }}"
                                        data-email="{{ $sup->email }}"
                                        data-phone="{{ $sup->phone ?? '' }}"
                                        data-country-id="{{ $sup->country_id ?? '' }}"
                                        data-permissions="{{ json_encode($sup->permissions ?? []) }}"
                                        data-is-active="{{ $sup->is_active ? '1' : '0' }}">
                                    {{ __('common.edit') }}
                                </button>
                                <button type="button"
                                        class="btn-delete-supervisor text-xs text-red-500 hover:text-red-700 font-medium"
                                        data-url="{{ route('admin.shipping-companies.supervisors.destroy', $sup->id) }}">
                                    {{ __('common.delete') }}
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ─── Recent Agents ───────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-bold text-gray-900">{{ __('admin.shipping_section.agents_latest') }}</h2>
            </div>
            @if($shippingCompany->agents->isEmpty())
            <div class="px-6 py-8 text-center text-gray-400 text-sm">{{ __('admin.shipping_section.no_agents') }}</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.name_col') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.phone') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('common.status') }}</th>
                            <th class="px-6 py-3 text-start font-semibold">{{ __('admin.shipping_section.rating_col') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($shippingCompany->agents as $agent)
                        @php $ac = ['active'=>'emerald','suspended'=>'red','inactive'=>'gray','on_shift'=>'blue'][$agent->status->value] ?? 'gray'; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $agent->name }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ $agent->phone }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $ac }}-100 text-{{ $ac }}-700 capitalize">
                                    {{ __('admin.shipping_section.agent_status_' . $agent->status->value) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-600">{{ number_format($agent->rating_avg, 1) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ─── Add Supervisor Modal ──────────────────────────────────────────────── --}}
<div id="supervisor-modal" class="modal-backdrop hidden">
    <div class="modal-box w-full max-w-lg !p-0 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="supervisor-modal-title" class="text-lg font-semibold text-gray-900">{{ __('admin.shipping_section.add_supervisor') }}</h3>
            <button type="button" id="supervisor-modal-close" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <form id="supervisor-form" method="POST">
            @csrf
            <input type="hidden" name="_method" id="supervisor-method" value="POST">
            <input type="hidden" id="supervisor-id" value="">
            <input type="hidden" name="shipping_company_id" value="{{ $shippingCompany->id }}">

            <div class="px-6 py-5 space-y-4">

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.full_name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" class="form-input w-full" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.email_required') }} <span class="text-red-500">*</span></label>
                        <input type="email" name="email" class="form-input w-full" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.phone_required') }}</label>
                        <input type="text" name="phone" class="form-input w-full">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.country') }}</label>
                        <select name="country_id" class="form-input w-full">
                            <option value="">— {{ __('admin.shipping_section.supervisor_country_any') }} —</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name_en }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ __('admin.shipping_section.supervisor_country_note') }}
                            @if(collect($shippingCompany->served_countries ?? [])->push($shippingCompany->country_id)->filter()->isNotEmpty())
                                {{ __('admin.shipping_section.supervisor_country_restricted') }}
                            @endif
                        </p>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.shipping_section.permissions_col') }} <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['manage_agents' => 'Manage Agents', 'view_orders' => 'View Orders', 'assign_orders' => 'Assign Orders', 'view_reports' => 'View Reports'] as $value => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="permissions[]" value="{{ $value }}"
                                       class="rounded border-gray-300 text-primary-600">
                                {{ $label }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Password section --}}
                <div id="supervisor-password-section">
                    <div class="border-t border-gray-100 pt-4">
                        <h4 id="supervisor-password-label"
                            class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">
                            {{ __('admin.shipping_section.password_section_create') }}
                        </h4>
                        <div id="supervisor-password-auto" class="text-sm text-gray-500 italic">
                            {{ __('admin.shipping_section.password_auto_generated') }}
                        </div>
                        <div id="supervisor-password-manual" class="hidden grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('admin.shipping_section.new_password') }}
                                </label>
                                <input type="password" name="password" id="supervisor-new-password"
                                       class="form-input w-full" minlength="8"
                                       placeholder="{{ __('admin.shipping_section.password_leave_blank') }}">
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ __('admin.shipping_section.password_leave_blank_hint') }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('admin.shipping_section.confirm_password') }}
                                </label>
                                <input type="password" name="password_confirmation" id="supervisor-confirm-password"
                                       class="form-input w-full" minlength="8">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="supervisor-temp-password-notice" class="hidden bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <p class="text-xs font-medium text-amber-800">{{ __('admin.shipping_section.temp_password_label') }}</p>
                    <p id="supervisor-temp-password-value" class="font-mono text-sm text-amber-900 mt-1 break-all"></p>
                    <p class="text-xs text-amber-600 mt-1">{{ __('admin.shipping_section.temp_password_note') }}</p>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                <button type="button" id="supervisor-modal-close-btn" class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                <button type="submit" id="supervisor-form-submit" class="btn btn-primary btn-sm">{{ __('admin.shipping_section.create_supervisor') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- ─── Create / Edit Company Modal ───────────────────────────────────────── --}}
<div id="company-modal" class="modal-backdrop hidden">
    <div class="modal-box w-full max-w-2xl !p-0 overflow-hidden">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 id="company-modal-title" class="text-lg font-semibold text-gray-900">
                {{ __('admin.shipping_section.edit_company') }}
            </h3>
            <button type="button" id="company-modal-close"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <form id="company-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="company-form-method" value="PUT">
            <input type="hidden" id="company-id" value="">

            <div class="px-6 py-5 space-y-5 max-h-[72vh] overflow-y-auto">

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
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.served_countries') }}
                            </label>
                            <select name="served_countries[]" id="field-served-countries"
                                    multiple class="form-input w-full" style="min-height: 100px;">
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">
                                        {{ $country->name_en }}{{ $country->currency_code ? ' (' . $country->currency_code . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.served_countries_note') }}</p>
                        </div>
                    </div>
                </div>

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
                    {{ __('common.save') }}
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// ── Company Edit Modal ───────────────────────────────────────────────────────
(() => {
    const BASE_URL  = @json(url('shipping-companies'));
    const CSRF      = () => document.querySelector('meta[name="csrf-token"]').content;
    const modal     = document.getElementById('company-modal');
    const form      = document.getElementById('company-form');
    const submitBtn = document.getElementById('company-form-submit');

    document.getElementById('btn-edit-company').addEventListener('click', (e) => {
        const btn = e.currentTarget;
        form.reset();
        document.getElementById('current-logo-wrap').classList.add('hidden');
        document.getElementById('current-logo-img').src = '';

        document.getElementById('company-id').value          = btn.dataset.id;
        document.getElementById('field-name').value          = btn.dataset.name;
        document.getElementById('field-legal-name').value    = btn.dataset.legalName || '';
        document.getElementById('field-country').value       = btn.dataset.countryId || '';
        document.getElementById('field-contact-email').value = btn.dataset.contactEmail;
        document.getElementById('field-contact-phone').value = btn.dataset.contactPhone || '';
        document.getElementById('field-can-notify').checked  = btn.dataset.canNotify === '1';

        const served = JSON.parse(btn.dataset.servedCountries || '[]');
        [...document.getElementById('field-served-countries').options].forEach(opt => {
            opt.selected = served.includes(opt.value);
        });

        const logoUrl = btn.dataset.logo;
        if (logoUrl) {
            document.getElementById('current-logo-img').src = logoUrl;
            document.getElementById('current-logo-wrap').classList.remove('hidden');
        }

        modal.classList.remove('hidden');
    });

    ['company-modal-close', 'company-modal-cancel'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', () => modal.classList.add('hidden'));
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        submitBtn.disabled = true;

        const companyId = document.getElementById('company-id').value;
        const formData   = new FormData(form);
        formData.set('_method', 'PUT');

        if (!document.getElementById('field-can-notify').checked) {
            formData.set('can_supervisors_receive_all_notifications', '0');
        }

        try {
            const res  = await fetch(`${BASE_URL}/${companyId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json' },
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                window.Toast?.success(data.message);
                setTimeout(() => location.reload(), 800);
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
})();
</script>
@endpush

@push('scripts')
<script>
// ── Supervisor Modal ───────────────────────────────────────────────────────
const supervisorModal = document.getElementById('supervisor-modal');
const supervisorForm  = document.getElementById('supervisor-form');
const SUPERVISOR_STORE_URL = '{{ route('admin.shipping-companies.supervisors.store') }}';

function supervisorUpdateUrl(id) {
    return '{{ url('shipping-companies/supervisors') }}/' + id;
}

function openSupervisorModal(mode, data) {
    supervisorForm.reset();
    document.getElementById('supervisor-temp-password-notice').classList.add('hidden');
    document.getElementById('supervisor-new-password').value     = '';
    document.getElementById('supervisor-confirm-password').value = '';

    const isEdit = mode === 'edit';

    document.getElementById('supervisor-modal-title').textContent = isEdit
        ? @json(__('admin.shipping_section.edit_supervisor'))
        : @json(__('admin.shipping_section.add_supervisor'));

    document.getElementById('supervisor-form-submit').textContent = isEdit
        ? @json(__('common.save_changes'))
        : @json(__('admin.shipping_section.create_supervisor'));

    document.getElementById('supervisor-method').value = isEdit ? 'PUT' : 'POST';
    document.getElementById('supervisor-id').value     = isEdit ? data.id : '';

    document.getElementById('supervisor-password-auto').classList.toggle('hidden', isEdit);
    document.getElementById('supervisor-password-manual').classList.toggle('hidden', !isEdit);

    if (isEdit) {
        supervisorForm.querySelector('[name="name"]').value       = data.name;
        supervisorForm.querySelector('[name="email"]').value      = data.email;
        supervisorForm.querySelector('[name="phone"]').value      = data.phone || '';
        supervisorForm.querySelector('[name="country_id"]').value = data.countryId || '';

        supervisorForm.querySelectorAll('[name="permissions[]"]').forEach(cb => {
            cb.checked = (data.permissions || []).includes(cb.value);
        });
    }

    supervisorModal.classList.remove('hidden');
}

document.getElementById('btn-add-supervisor').addEventListener('click', () => {
    openSupervisorModal('create', {});
});

document.querySelectorAll('.btn-edit-supervisor').forEach(btn => {
    btn.addEventListener('click', () => {
        openSupervisorModal('edit', {
            id:          btn.dataset.id,
            name:        btn.dataset.name,
            email:       btn.dataset.email,
            phone:       btn.dataset.phone,
            countryId:   btn.dataset.countryId,
            permissions: JSON.parse(btn.dataset.permissions || '[]'),
            isActive:    btn.dataset.isActive === '1',
        });
    });
});

['supervisor-modal-close', 'supervisor-modal-close-btn'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => {
        supervisorModal.classList.add('hidden');
    });
});

supervisorForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn          = document.getElementById('supervisor-form-submit');
    const supervisorId = document.getElementById('supervisor-id').value;
    const isEdit        = !!supervisorId;

    const newPwd     = document.getElementById('supervisor-new-password').value;
    const confirmPwd = document.getElementById('supervisor-confirm-password').value;
    if (isEdit && newPwd && newPwd !== confirmPwd) {
        alert(@json(__('admin.shipping_section.password_mismatch')));
        return;
    }

    btn.disabled = true;
    const label  = btn.textContent;
    btn.textContent = '...';

    const url      = isEdit ? supervisorUpdateUrl(supervisorId) : SUPERVISOR_STORE_URL;
    const formData = new FormData(supervisorForm);

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await res.json();

        if (data.success) {
            if (!isEdit) {
                // Show temp password
                document.getElementById('supervisor-temp-password-value').textContent = data.temp_password;
                document.getElementById('supervisor-temp-password-notice').classList.remove('hidden');
                btn.disabled    = true;
                btn.textContent = @json(__('common.saved'));
            } else {
                btn.disabled    = true;
                btn.textContent = @json(__('common.saved'));
            }

            // Reload page shortly so the table reflects the change
            setTimeout(() => location.reload(), isEdit ? 1500 : 3000);
        } else {
            const errors = data.errors
                ? Object.values(data.errors).flat().join('\n')
                : data.message;
            btn.disabled    = false;
            btn.textContent = label;
            alert(errors || @json(__('admin.shipping_section.save_failed')));
        }
    } catch (err) {
        btn.disabled    = false;
        btn.textContent = label;
        console.error(err);
    }
});

// ── Supervisor Delete ──────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete-supervisor').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('{{ __('admin.shipping_section.confirm_delete_supervisor') }}')) return;
        const url  = btn.dataset.url;
        const row  = btn.closest('tr');

        const res  = await fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        const data = await res.json();
        if (data.success) row.remove();
        else alert(data.message ?? 'Could not delete supervisor.');
    });
});

function toggleNotifications(supervisorId, btn) {
    const url = btn.dataset.url;
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent = data.enabled ? btn.dataset.onLabel : btn.dataset.offLabel;
            btn.className = btn.className.replace(/bg-\w+-100 text-\w+-\w+/g, '');
            if (data.enabled) {
                btn.classList.add('bg-emerald-100', 'text-emerald-700', 'hover:bg-emerald-200');
                btn.classList.remove('bg-gray-100', 'text-gray-500', 'hover:bg-gray-200');
            } else {
                btn.classList.add('bg-gray-100', 'text-gray-500', 'hover:bg-gray-200');
                btn.classList.remove('bg-emerald-100', 'text-emerald-700', 'hover:bg-emerald-200');
            }
        }
    });
}
</script>
@endpush

@endsection
