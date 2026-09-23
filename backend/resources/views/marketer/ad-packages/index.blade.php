@extends('layouts.marketer')
@section('title', __('ad_packages.nawy_packages'))
@section('page-title', __('ad_packages.ad_packages'))

@section('content')
@if($active)
    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
        {{ __('ad_packages.package_active') }}: {{ $active->expires_at->format('Y-m-d') }} — {{ $active->package->name }}
    </div>
@endif
<p class="mb-4 text-sm text-gray-600">{{ __('ad_packages.wallet_balance') }}: {{ number_format($walletBalance) }} {{ $currency }}</p>
<div class="grid gap-4 md:grid-cols-3">
@forelse($packages as $p)
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col">
        <h3 class="text-lg font-bold">{{ $p->name }}</h3>
        @if($p->description_ar)<p class="text-sm text-gray-500 mt-1">{{ $p->description_ar }}</p>@endif
        <dl class="mt-4 text-sm space-y-1">
            <div class="flex justify-between"><dt>{{ __('ad_packages.package_price') }}</dt><dd>{{ number_format($p->price) }} {{ $p->currency }}</dd></div>
            <div class="flex justify-between"><dt>{{ __('ad_packages.vat_included', ['pct' => $p->vat_pct]) }}</dt><dd>{{ number_format($p->vat_amount) }} {{ $p->currency }}</dd></div>
            <div class="flex justify-between font-bold border-t pt-1"><dt>{{ __('ad_packages.total_with_vat') }}</dt><dd>{{ number_format($p->total) }} {{ $p->currency }}</dd></div>
            <div class="flex justify-between text-gray-500"><dt>{{ __('ad_packages.duration') }}</dt><dd>{{ $p->duration_days }} {{ __('ad_packages.days') }}</dd></div>
        </dl>
        <ul class="mt-3 text-sm list-disc ps-5 flex-1">
            @foreach($p->features ?? [] as $f)<li>{{ $f }}</li>@endforeach
        </ul>
        @unless($active)
            <a href="{{ route('marketer.ad-packages.contract', $p->id) }}" class="mt-4 text-center bg-blue-600 text-white rounded-lg py-2">{{ __('ad_packages.subscribe_now') }}</a>
        @endunless
    </div>
@empty
    <p class="col-span-3 text-center text-gray-500 p-10">{{ __('ad_packages.no_packages') }}</p>
@endforelse
</div>
@endsection
