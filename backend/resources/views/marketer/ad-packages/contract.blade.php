@extends('layouts.marketer')
@section('title', __('ad_packages.contract_terms'))
@section('page-title', $package->name)

@section('content')
<form method="POST" action="{{ route('marketer.ad-packages.subscribe', $package->id) }}" enctype="multipart/form-data"
      class="bg-white rounded-xl border border-gray-200 p-6 space-y-5 max-w-2xl">
    @csrf
    <div class="text-sm space-y-1">
        <div class="flex justify-between"><span>{{ __('ad_packages.package_price') }}</span><span>{{ number_format($package->price) }} {{ $package->currency }}</span></div>
        <div class="flex justify-between"><span>{{ __('ad_packages.vat_included', ['pct' => $package->vat_pct]) }}</span><span>{{ number_format($package->vat_amount) }} {{ $package->currency }}</span></div>
        <div class="flex justify-between font-bold border-t pt-1"><span>{{ __('ad_packages.total_with_vat') }}</span><span>{{ number_format($package->total) }} {{ $package->currency }}</span></div>
    </div>
    <div class="h-56 overflow-y-auto border rounded-lg p-4 text-sm text-gray-700 leading-7">
        <h4 class="font-bold mb-2">{{ __('ad_packages.contract_terms') }}</h4>
        {!! nl2br(e(__('ad_packages.contract_body', ['days' => $package->duration_days]))) !!}
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">{{ __('ad_packages.payment_method') }}</label>
        <label class="flex items-center gap-2"><input type="radio" name="payment_method" value="wallet" checked> {{ __('ad_packages.pay_wallet') }} ({{ number_format($walletBalance) }} {{ $package->currency }})</label>
        <label class="flex items-center gap-2"><input type="radio" name="payment_method" value="bank_transfer"> {{ __('ad_packages.pay_bank') }}</label>
        @error('payment_method')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm mb-1">{{ __('ad_packages.payment_proof') }}</label>
        <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf">
        @error('payment_proof')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="accept_terms" value="1"> {{ __('ad_packages.accept_terms') }}</label>
    @error('accept_terms')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    <button type="submit" onclick="this.form.requestSubmit ? null : null" class="bg-blue-600 text-white rounded-lg px-6 py-2"
            data-once>{{ __('ad_packages.subscribe_now') }}</button>
</form>
<script>
document.querySelectorAll('form').forEach(f => f.addEventListener('submit', () => {
    const b = f.querySelector('[data-once]'); if (b) setTimeout(() => b.disabled = true, 0);
}));
</script>
@endsection
