@extends('layouts.marketer')
@section('title', $listing->title)
@section('page-title', $listing->title)

@section('content')
<div class="space-y-4">
    @include('marketer.classified-listings._tabs')
    @if(session('success'))<div class="p-3 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>@endif
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        <div class="flex justify-between">
            <div class="text-sm text-gray-500">{{ $listing->listing_number }} · {{ $listing->classifiedCategory?->name }} · {{ $listing->country?->name_ar ?? '' }}</div>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100">{{ __('marketer.status_'.$listing->status->value) }}</span>
        </div>
        <div class="font-bold text-lg">{{ number_format($listing->price) }} {{ $listing->currency }}
            @if($listing->price_negotiable)<span class="text-xs text-gray-500">({{ __('marketer.price_negotiable') }})</span>@endif</div>
        <div class="text-sm text-gray-600">{{ __('marketer.listing_purpose_'.$listing->listing_purpose) }}</div>
        <p class="text-sm text-gray-700">{{ app()->getLocale() === 'ar' ? $listing->description_ar : $listing->description_en }}</p>
        @if($listing->attributes)
            <dl class="grid grid-cols-2 gap-2 text-sm">
                @foreach($listing->attributes as $k => $v)<div><dt class="text-gray-500 inline">{{ $k }}:</dt> <dd class="inline">{{ is_array($v) ? implode(', ', $v) : $v }}</dd></div>@endforeach
            </dl>
        @endif
        <div class="flex gap-2 flex-wrap">
            @foreach($listing->images as $img)<img src="{{ Storage::url($img->file_path) }}" alt="" class="h-24 rounded-lg">@endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-bold mb-3">{{ __('marketer.received_inquiries') }} ({{ $listing->inquiries->count() }})</h3>
        @forelse($listing->inquiries as $inq)
            <a href="{{ route('marketer.classified-inquiries.show', $inq->id) }}" class="block border-t py-2 text-sm">
                <span class="font-semibold">{{ __('marketer.inquiry_from') }} {{ $inq->customer?->name }}</span>
                <span class="text-xs text-gray-500">— {{ __('marketer.status_'.$inq->status->value) }}</span>
                <div class="text-gray-600">{{ \Illuminate\Support\Str::limit($inq->message, 120) }}</div>
            </a>
        @empty
            <div class="text-sm text-gray-500">{{ __('marketer.classified_no_inquiries') }}</div>
        @endforelse
    </div>
</div>
@endsection
