@extends('layouts.marketer')
@section('title', __('marketer.campaigns.peer.promoted_title'))
@section('page-title', __('marketer.campaigns.peer.promoted_title'))

@section('content')
<div class="space-y-4">
    <p class="text-sm text-gray-600">{{ __('marketer.campaigns.peer.promoted_hint') }}</p>

    @forelse($campaigns as $campaign)
        @php $product = $campaign->marketerListing?->productVariant?->product; @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-start justify-between gap-4">
            <div>
                <h4 class="font-bold text-gray-900">{{ app()->getLocale() === 'ar' ? ($product?->name_ar ?? $product?->name_en) : ($product?->name_en ?? $product?->name_ar) }}</h4>
                <div class="text-sm text-gray-500 mt-0.5">
                    {{ __('marketer.campaigns.peer.promoter') }}: <strong>{{ $campaign->promoter?->name ?? '—' }}</strong>
                    • {{ $campaign->created_at->format('Y-m-d') }}
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    {{ __('marketer.campaigns.conversions') }}: {{ number_format($campaign->conversions_count) }}
                </div>
            </div>
            <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded">
                {{ __('marketer.campaigns.peer.status.'.$campaign->status) }}
            </span>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">{{ __('marketer.campaigns.peer.promoted_none') }}</div>
    @endforelse

    <div>{{ $campaigns->links() }}</div>
</div>
@endsection
