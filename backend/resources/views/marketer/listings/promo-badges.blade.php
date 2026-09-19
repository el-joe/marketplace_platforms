@extends('layouts.marketer')
@section('title', __('marketer.promo_badges_title'))
@section('page-title', __('marketer.promo_badges_title'))

@section('content')
<div class="space-y-4 max-w-4xl">
    <a href="{{ route('marketer.listings.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('marketer.back_to_listings') }}</a>
    <p class="text-sm font-medium text-gray-800">{{ $listing->productVariant->product->name_ar ?: $listing->productVariant->product->name_en }}</p>

    <form method="POST" action="{{ route('marketer.listings.promo-badges.update', $listing) }}"
          class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
        @csrf
        @method('PUT')
        @include('shared.promo-badges-editor', [
            'badges' => $listing->promoBadges,
            'title' => __('marketer.promo_badges_title'),
            'hint' => __('marketer.promo_badges_hint'),
        ])
        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium">{{ __('marketer.promo_badges_save') }}</button>
    </form>
</div>
@endsection
