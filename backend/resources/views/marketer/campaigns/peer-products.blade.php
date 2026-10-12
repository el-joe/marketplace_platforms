@extends('layouts.marketer')
@section('title', __('marketer.campaigns.peer.browse_title'))
@section('page-title', __('marketer.campaigns.peer.browse_title'))

@section('content')
<div class="space-y-4">
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-sm text-gray-600">{{ __('marketer.campaigns.peer.browse_hint') }}</p>
        <form method="GET" action="{{ route('marketer.campaigns.peer-products') }}" class="mt-3 flex gap-2">
            <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('marketer.campaigns.peer.search_placeholder') }}"
                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <button class="px-4 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm">{{ __('marketer.campaigns.peer.search') }}</button>
        </form>
    </div>

    @forelse($listings as $listing)
        @php
            $product = $listing->productVariant?->product;
            $alreadyThisMonth = in_array($listing->marketer_id, $promotedOwnerIds, true);
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h4 class="font-bold text-gray-900 truncate">{{ app()->getLocale() === 'ar' ? ($product?->name_ar ?? $product?->name_en) : ($product?->name_en ?? $product?->name_ar) }}</h4>
                <div class="text-sm text-gray-500 mt-0.5">
                    {{ __('marketer.campaigns.peer.owner') }}: {{ $listing->marketer?->name }}
                    • {{ __('marketer.campaigns.peer.price') }}: {{ number_format((int) $listing->price) }} {{ $listing->currency }}
                    • {{ __('marketer.campaigns.peer.stock') }}: {{ (int) $listing->stock_available }}
                </div>
            </div>
            @if($alreadyThisMonth)
                <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs font-semibold rounded">{{ __('marketer.campaigns.peer.already_this_month') }}</span>
            @else
                <form method="POST" action="{{ route('marketer.campaigns.request') }}">
                    @csrf
                    <input type="hidden" name="listing_id" value="{{ $listing->id }}">
                    <input type="hidden" name="listing_type" value="marketer_listing">
                    <button class="px-4 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm">{{ __('marketer.campaigns.peer.promote') }}</button>
                </form>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">{{ __('marketer.campaigns.peer.none') }}</div>
    @endforelse

    <div>{{ $listings->links() }}</div>
</div>
@endsection
