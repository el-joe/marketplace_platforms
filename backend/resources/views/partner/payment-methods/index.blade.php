@extends('layouts.partner')

@section('title', __('partner.payment_methods.title'))
@section('page-title', __('partner.payment_methods.title'))

@section('content')

    @unless($isFbmVendor)
        <div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6 flex items-start gap-3">
            <span>🔒</span>
            <p class="text-sm text-yellow-800">{{ __('partner.payment_methods.fbm_only_notice') }}</p>
        </div>
    @endunless

    <p class="text-sm text-gray-500 mb-6">{{ __('partner.payment_methods.description') }}</p>

    <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
        @forelse($gateways as $gateway)
            <div class="flex items-center justify-between px-5 py-4" data-gateway-id="{{ $gateway->id }}">
                <div class="flex items-center gap-3">
                    @if($gateway->image)
                        <img src="{{ $gateway->image }}" alt="" class="w-8 h-8 object-contain">
                    @endif
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ $gateway->name_ar ?? $gateway->name }}</p>
                        <p class="text-xs text-gray-400">{{ $gateway->code }}</p>
                    </div>
                </div>
                <label class="relative inline-flex items-center {{ $isFbmVendor ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' }}">
                    <input type="checkbox" class="toggle-gateway sr-only peer"
                           data-gateway-id="{{ $gateway->id }}"
                           {{ in_array($gateway->id, $enabledGatewayIds) ? 'checked' : '' }}
                           {{ $isFbmVendor ? '' : 'disabled' }}>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:bg-gray-900
                                after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white
                                after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full
                                rtl:peer-checked:after:-translate-x-full"></div>
                </label>
            </div>
        @empty
            <div class="py-16 text-center text-gray-400 text-sm">{{ __('partner.payment_methods.no_gateways') }}</div>
        @endforelse
    </div>

@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const updateUrl = @json(route('partner.payment-methods.update'));

    document.querySelectorAll('.toggle-gateway').forEach((input) => {
        input.addEventListener('change', () => {
            fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_gateway_id: input.dataset.gatewayId,
                    is_enabled: input.checked,
                }),
            }).then(async (res) => {
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || 'Error');
                    input.checked = !input.checked;
                }
            });
        });
    });
})();
</script>
@endpush
