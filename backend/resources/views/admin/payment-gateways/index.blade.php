@extends('layouts.admin')

@section('title', 'Payment Gateways')

@section('content')

{{-- Header --}}
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Payment Gateways</h1>
        <p class="text-sm text-gray-500 mt-0.5">Configure which payment gateways are available per country.</p>
    </div>
</div>

{{-- Global Gateway Registry --}}
<div class="mb-8">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Available Gateways</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        @foreach($gateways as $gw)
            <div class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-4 text-center shadow-sm">
                @if($gw->image)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($gw->image) }}" alt="{{ $gw->name }}" class="h-8 w-auto object-contain">
                @else
                    <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400">
                        <x-heroicon name="credit-card" class="w-5 h-5" />
                    </div>
                @endif
                <span class="text-sm font-medium text-gray-900">{{ $gw->name }}</span>
                <span class="text-xs rounded-full px-2 py-0.5
                    @if($gw->type === 'redirect') bg-blue-50 text-blue-700
                    @elseif($gw->type === 'offline') bg-amber-50 text-amber-700
                    @elseif($gw->type === 'internal') bg-purple-50 text-purple-700
                    @else bg-green-50 text-green-700
                    @endif">
                    {{ ucfirst($gw->type) }}
                </span>
                <div class="flex gap-1.5 text-xs text-gray-400 mt-1">
                    @if($gw->supports_webhook)
                        <span title="Supports webhooks">🔔</span>
                    @endif
                    @if($gw->supports_refund)
                        <span title="Supports refunds">↩️</span>
                    @endif
                </div>
                {{-- Image upload --}}
                <div class="mt-2 flex flex-col items-center gap-1">
                    <label class="cursor-pointer">
                        <input type="file" accept="image/*" class="hidden"
                               onchange="uploadGatewayImage('{{ $gw->id }}', this)">
                        <span class="text-xs text-primary-600 hover:underline font-medium">
                            {{ $gw->image ? __('admin.payment_gateways.change_image') : __('admin.payment_gateways.upload_image') }}
                        </span>
                    </label>
                    @if($gw->image)
                        <button type="button"
                                class="text-xs text-red-500 hover:underline"
                                onclick="deleteGatewayImage('{{ $gw->id }}', this)">
                            {{ __('admin.payment_gateways.remove_image') }}
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Per-Country Configuration --}}
@forelse($countries as $country)
    <div class="mb-4 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        {{-- Country Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-900">
                    {{ $country->name_en }}
                    <span class="ml-1 text-sm font-normal text-gray-400">({{ $country->currency_code }})</span>
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ $country->countryPaymentGateways->count() }} gateway(s) configured</p>
            </div>
            <button type="button"
                class="btn-add-country-gateway inline-flex items-center gap-1 rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100"
                data-country-id="{{ $country->id }}"
                data-country-name="{{ $country->name_en }}"
                data-currency="{{ $country->currency_code }}"
                data-existing="{{ json_encode($country->countryPaymentGateways->pluck('gateway_id')->all()) }}">
                <x-heroicon name="plus" class="w-3.5 h-3.5" /> Add Gateway
            </button>
        </div>

        {{-- Configured Gateways List --}}
        @if($country->countryPaymentGateways->isEmpty())
            <p class="px-6 py-4 text-sm text-gray-400 italic">No gateways configured for this country.</p>
        @else
            <ul class="divide-y divide-gray-100" id="sortable-{{ $country->id }}">
                @foreach($country->countryPaymentGateways as $cpg)
                    <li class="flex items-center gap-3 px-5 py-3" data-id="{{ $cpg->id }}">
                        {{-- Drag handle --}}
                        <span class="cursor-grab text-gray-300 hover:text-gray-400">
                            <x-heroicon name="bars-3" class="w-4 h-4" />
                        </span>
                        {{-- Logo --}}
                        <div class="w-8 flex-shrink-0 flex items-center justify-center">
                            @if($cpg->gateway?->image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($cpg->gateway->image) }}" alt="" class="h-6 w-auto object-contain">
                            @else
                                <x-heroicon name="credit-card" class="w-5 h-5 text-gray-300" />
                            @endif
                        </div>
                        {{-- Name + badges --}}
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-medium text-gray-900">{{ $cpg->display_name_en }}</span>
                            <span class="ml-1.5 text-xs text-gray-400">{{ $cpg->gateway?->name }}</span>
                            {{-- Environment badge --}}
                            <span class="ml-1.5 text-xs rounded px-1.5 py-0.5
                                {{ $cpg->environment === 'production' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                {{ $cpg->environment === 'production' ? 'Production' : 'Sandbox' }}
                            </span>
                            {{-- Configured badge --}}
                            @if($cpg->is_configured)
                                <span class="ml-1 text-xs rounded px-1.5 py-0.5 bg-success-50 text-success-700 border border-success-200">
                                    ✓ Configured
                                </span>
                            @elseif(!empty($cpg->gateway?->required_fields))
                                <span class="ml-1 text-xs rounded px-1.5 py-0.5 bg-red-50 text-red-700 border border-red-200">
                                    ⚠ No credentials
                                </span>
                            @endif
                        </div>
                        {{-- Fee --}}
                        @if($cpg->fee_pct || $cpg->fee_fixed)
                            <span class="text-xs text-gray-400">
                                @if($cpg->fee_pct) {{ number_format($cpg->fee_pct, 2) }}% @endif
                                @if($cpg->fee_fixed) +{{ $cpg->fee_fixed }} @endif
                            </span>
                        @endif
                        {{-- Toggle --}}
                        <button type="button"
                            class="btn-toggle-gateway rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $cpg->is_active ? 'bg-success-50 text-success-700 hover:bg-success-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}"
                            data-id="{{ $cpg->id }}">
                            {{ $cpg->is_active ? 'Active' : 'Inactive' }}
                        </button>
                        {{-- Test --}}
                        @if($cpg->gateway?->code && !in_array($cpg->gateway->code, ['cod', 'wallet']))
                            <button type="button"
                                class="btn-test-gateway text-xs text-primary-600 hover:text-primary-800 border border-primary-200 rounded px-2 py-0.5"
                                data-id="{{ $cpg->id }}">
                                Test
                            </button>
                            <span class="test-result-{{ $cpg->id }} text-xs text-gray-400"></span>
                        @endif
                        {{-- Edit --}}
                        <button type="button"
                            class="btn-edit-gateway p-1 text-gray-400 hover:text-primary-600 rounded"
                            data-row="{{ json_encode([
                                'id'              => $cpg->id,
                                'country_id'      => $cpg->country_id,
                                'gateway_id'      => $cpg->gateway_id,
                                'gateway_code'    => $cpg->gateway?->code,
                                'gateway_name'    => $cpg->gateway?->name,
                                'required_fields' => $cpg->gateway?->required_fields ?? [],
                                'display_name_en' => $cpg->display_name_en,
                                'display_name_ar' => $cpg->display_name_ar,
                                'environment'     => $cpg->environment,
                                'is_active'       => $cpg->is_active,
                                'fee_pct'         => $cpg->fee_pct,
                                'fee_fixed'       => $cpg->fee_fixed,
                                'min_order'       => $cpg->min_order,
                                'max_order'       => $cpg->max_order,
                                'sort_order'      => $cpg->sort_order,
                            ]) }}">
                            <x-heroicon name="pencil-square" class="w-4 h-4" />
                        </button>
                        {{-- Delete --}}
                        <button type="button"
                            class="btn-delete-gateway p-1 text-gray-400 hover:text-red-600 rounded"
                            data-id="{{ $cpg->id }}" data-name="{{ $cpg->display_name_en }}">
                            <x-heroicon name="trash" class="w-4 h-4" />
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@empty
    <p class="text-gray-400 italic">No active countries found.</p>
@endforelse

{{-- ── Add / Edit Modal ──────────────────────────────────────────────────── --}}
<x-modal id="gateway-modal" title="Configure Gateway" size="lg">
    <form id="gateway-form" novalidate>
        @csrf
        <input type="hidden" id="gw-id">
        <input type="hidden" id="gw-http" value="POST">
        <input type="hidden" id="gw-country-id">

        <div class="px-6 py-4 space-y-4">
            {{-- Gateway selector (add mode only) --}}
            <div id="gw-gateway-select-wrapper">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gateway</label>
                <select id="gw-gateway-id" name="gateway_id"
                    class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                    <option value="">— Select gateway —</option>
                    @foreach($gateways as $gw)
                        <option value="{{ $gw->id }}"
                            data-code="{{ $gw->code }}"
                            data-fields="{{ json_encode($gw->required_fields) }}"
                            data-name-en="{{ $gw->name }}"
                            data-name-ar="{{ $gw->name_ar }}">
                            {{ $gw->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            {{-- Edit mode: read-only gateway name --}}
            <div id="gw-gateway-name-wrapper" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gateway</label>
                <p id="gw-gateway-name-display" class="text-sm font-semibold text-gray-900"></p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name (EN)</label>
                    <input type="text" name="display_name_en" id="gw-display-name-en"
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name (AR)</label>
                    <input type="text" name="display_name_ar" id="gw-display-name-ar" dir="rtl"
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Environment</label>
                    <select name="environment" id="gw-environment"
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                        <option value="sandbox">Sandbox (testing)</option>
                        <option value="production">Production (live)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="gw-sort-order" value="0" min="0"
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fee %</label>
                    <input type="number" name="fee_pct" id="gw-fee-pct" step="0.01" min="0" max="100" value="0"
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fixed Fee (base units)</label>
                    <input type="number" name="fee_fixed" id="gw-fee-fixed" min="0" value="0"
                        class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                </div>
            </div>

            {{-- API Credentials (dynamic — built from required_fields) --}}
            <div id="gw-credentials-section" class="pt-3 border-t border-gray-100">
                <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center gap-1">
                    <x-heroicon name="key" class="w-4 h-4 text-gray-400" /> API Credentials
                </label>
                <p class="text-xs text-gray-400 mb-3">Stored encrypted. Leave blank to keep existing values when editing.</p>
                <div id="gw-credentials-fields" class="space-y-3">
                    <p class="text-xs text-gray-400 italic">Select a gateway above to see required fields.</p>
                </div>
            </div>

            {{-- Webhook Secret --}}
            <div class="pt-3 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret</label>
                <input type="password" name="webhook_secret" id="gw-webhook-secret" autocomplete="off"
                    placeholder="Leave blank to keep existing"
                    class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                <p class="text-xs text-gray-400 mt-1">Used to verify incoming webhook signatures.</p>
            </div>

            {{-- Test connection --}}
            <div class="flex items-center gap-3 pt-2">
                <button type="button" id="btn-test-gateway-connection" disabled
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40">
                    <x-heroicon name="signal" class="w-3.5 h-3.5" /> Test Connection
                </button>
                <span id="gw-test-result" class="text-xs text-gray-500"></span>
            </div>
        </div>

        <x-slot:footer>
            <button type="button" data-modal-close class="btn-secondary">Cancel</button>
            <button type="submit" form="gateway-form" class="btn-primary">Save Gateway</button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- Delete confirm modal --}}
<x-modal id="delete-gateway-modal" title="Remove Gateway" size="sm">
    <div class="px-6 py-4">
        <p id="delete-gateway-message" class="text-sm text-gray-600"></p>
        <input type="hidden" id="delete-gateway-id">
    </div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn-secondary">Cancel</button>
        <button type="button" id="btn-confirm-delete-gateway" class="btn-danger">Remove</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
async function uploadGatewayImage(gatewayId, input) {
    const file = input.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('image', file);

    const res = await fetch(`{{ url('/admin/payment-gateways/gateways') }}/${gatewayId}/image`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: formData,
    });

    const data = await res.json();
    if (data.success) {
        window.Toast?.success(data.message);
        setTimeout(() => location.reload(), 800);
    } else {
        window.Toast?.error(data.message ?? 'Upload failed.');
    }
}

async function deleteGatewayImage(gatewayId, btn) {
    if (!confirm('Remove this image?')) return;

    const res = await fetch(`{{ url('/admin/payment-gateways/gateways') }}/${gatewayId}/image`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    });

    const data = await res.json();
    if (data.success) {
        window.Toast?.success(data.message);
        setTimeout(() => location.reload(), 800);
    }
}
</script>
@endpush

@vite(['resources/js/admin/payment-gateways.js'])
@endsection
