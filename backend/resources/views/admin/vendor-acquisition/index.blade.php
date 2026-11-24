@extends('layouts.admin')

@section('title', __('admin.nav.acquisition_commissions'))

@section('content')

<div class="mb-6 flex items-center justify-between gap-4">
    <h1 class="text-xl font-semibold text-gray-900">{{ __('admin.nav.acquisition_commissions') }}</h1>
    @can('acquisition_commissions.manage')
        <button type="button" data-modal-open="acquisition-assign-modal" class="btn btn-primary btn-sm">{{ __('acquisition.actions.assign') }}</button>
    @endcan
</div>

<details class="mb-6 rounded-xl border border-primary-100 bg-primary-50/50 p-4 text-sm text-gray-700" open data-acquisition-help>
    <summary class="cursor-pointer font-semibold text-gray-900">{{ __('acquisition.help.title') }}</summary>
    <ul class="mt-3 space-y-2 list-disc ps-5">
        <li>{{ __('acquisition.help.what') }}</li>
        <li>{{ __('acquisition.help.who') }}</li>
        <li>{{ __('acquisition.help.how') }}</li>
        <li>{{ __('acquisition.help.earn') }}</li>
        <li>{{ __('acquisition.help.expiry') }}</li>
        <li>{{ __('acquisition.help.payout') }}</li>
    </ul>
</details>

<x-card>
    <div class="table-responsive">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-start text-gray-500 border-b border-gray-100">
                    <th class="py-2 pe-4 text-start">{{ __('admin.vendors.title') }}</th>
                    <th class="py-2 pe-4 text-start">{{ __('admin.vendors.agent') }}</th>
                    <th class="py-2 pe-4 text-start">{{ __('admin.vendors.commission_rate') }}</th>
                    <th class="py-2 pe-4 text-start">{{ __('admin.vendors.monthly_min_sales') }} / {{ __('admin.vendors.monthly_max_sales') }}</th>
                    <th class="py-2 pe-4 text-start">{{ __('admin.vendors.total_earned') }}</th>
                    <th class="py-2 pe-4 text-start">{{ __('common.status') }}</th>
                    <th class="py-2 pe-4 text-start">{{ __('admin.vendors.expires_on') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($commissions as $commission)
                    <tr class="border-b border-gray-50">
                        <td class="py-2 pe-4 font-medium text-gray-900">
                            @if($commission->vendor)
                                <a href="{{ route('admin.vendors.acquisition-agent.show', $commission->vendor) }}" class="text-primary-600 hover:underline">
                                    {{ $commission->vendor->store_name }}
                                </a>
                            @endif
                        </td>
                        <td class="py-2 pe-4">{{ $commission->admin?->name }}</td>
                        <td class="py-2 pe-4">{{ number_format($commission->commission_rate / 100, 2) }}%</td>
                        <td class="py-2 pe-4">{{ $commission->monthly_min_sales }} / {{ $commission->monthly_max_sales }}</td>
                        <td class="py-2 pe-4">{{ \App\Support\Money::format($commission->total_earned) }} {{ $commission->currency }}</td>
                        <td class="py-2 pe-4"><x-badge :color="$commission->status === 'active' ? 'success' : ($commission->status === 'expired' ? 'gray' : 'danger')">{{ __('acquisition.status.'.$commission->status) }}</x-badge></td>
                        <td class="py-2 pe-4">{{ admin_tz($commission->valid_until)?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-4 text-center text-gray-400">{{ __('acquisition.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $commissions->links() }}</div>
</x-card>

@can('acquisition_commissions.manage')
<x-modal id="acquisition-assign-modal" title="{{ __('admin.vendors.assign_acquisition_agent') }}" size="md">
    <form id="acquisition-assign-form">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('acquisition.fields.vendor') }} <span class="text-danger-500">*</span></label>
                <select name="vendor_id" class="form-input w-full" required data-async-select
                        data-config='{{ json_encode(['url' => route('admin.acquisition-commissions.vendors.search'), 'placeholder' => __('acquisition.fields.vendor_placeholder')]) }}'></select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendors.select_admin_staff') }} <span class="text-danger-500">*</span></label>
                <select name="admin_id" class="form-input w-full" data-select2-init required>
                    <option value="">{{ __('admin.vendors.select_admin_placeholder') }}</option>
                    @foreach($agentCandidates as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendors.commission_rate_pct') }} <span class="text-danger-500">*</span></label>
                    <input type="number" name="commission_rate_pct" step="0.01" min="0.01" max="100" class="form-input w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendors.duration_months') }} <span class="text-danger-500">*</span></label>
                    <input type="number" name="duration_months" value="12" min="1" max="60" class="form-input w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendors.monthly_min_sales') }} <span class="text-danger-500">*</span></label>
                    <input type="number" name="monthly_min_sales" value="60" min="0" class="form-input w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.vendors.monthly_max_sales') }} <span class="text-danger-500">*</span></label>
                    <input type="number" name="monthly_max_sales" value="100" min="0" class="form-input w-full" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('acquisition.fields.valid_from') }}</label>
                <input type="date" name="valid_from" class="form-input w-full">
                <p class="text-xs text-gray-500 mt-1">{{ __('acquisition.fields.valid_from_hint') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.notes') }}</label>
                <textarea name="notes" rows="2" class="form-input w-full"></textarea>
            </div>
        </div>
        <x-slot name="footer">
            <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('admin.vendors.cancel') }}</button>
            <button type="submit" form="acquisition-assign-form" class="btn btn-primary btn-sm">{{ __('admin.vendors.assign') }}</button>
        </x-slot>
    </form>
</x-modal>
@endcan

@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('acquisition-assign-form');
    if (!form) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const storeUrlTemplate = {{ Js::from(route('admin.vendors.acquisition-agent.store', ['vendor' => '__VENDOR__'])) }};

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const data = new FormData(form);
        const vendorId = data.get('vendor_id');
        if (!vendorId) return;
        const payload = {};
        data.forEach((value, key) => { if (key !== 'vendor_id' && key !== 'commission_rate_pct' && value !== '') payload[key] = value; });
        payload.commission_rate = Math.round((parseFloat(data.get('commission_rate_pct')) || 0) * 100);

        const button = document.querySelector('[form="acquisition-assign-form"][type="submit"]');
        button && (button.disabled = true);
        try {
            const res = await fetch(storeUrlTemplate.replace('__VENDOR__', encodeURIComponent(vendorId)), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const body = await res.json().catch(() => ({}));
            if (res.ok) {
                window.Toast?.success(body.message);
                setTimeout(() => location.reload(), 700);
            } else {
                window.Toast?.error(body.message ?? @json(__('common.error')));
            }
        } finally {
            button && (button.disabled = false);
        }
    });
})();
</script>
@endpush
