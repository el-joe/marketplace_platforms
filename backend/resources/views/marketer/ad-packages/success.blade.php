@extends('layouts.marketer')
@section('title', __('ad_packages.payment_success'))
@section('page-title', __('ad_packages.ad_packages'))

@section('content')
<div class="bg-white rounded-xl border border-gray-200 p-10 text-center max-w-lg mx-auto">
    <div class="text-5xl text-green-500 mb-3">&#10003;</div>
    <h2 class="text-xl font-bold mb-2">{{ $sub->status === 'active' ? __('ad_packages.payment_success') : __('ad_packages.pending_review') }}</h2>
    <p class="text-gray-600 mb-1">{{ $sub->package->name }}</p>
    @if($sub->expires_at)<p class="text-sm text-gray-500">{{ __('ad_packages.package_active') }}: {{ $sub->expires_at->format('Y-m-d') }}</p>@endif
    <a href="{{ route('marketer.classified-listings.index') }}" class="inline-block mt-5 bg-blue-600 text-white rounded-lg px-6 py-2">{{ __('ad_packages.check_listings') }}</a>
</div>
@endsection
