@extends('layouts.marketer')
@section('title', __('marketer.classified_listings'))
@section('page-title', __('marketer.classified_listings'))

@section('content')
<div class="space-y-4">
    @include('marketer.classified-listings._tabs')
    @if(session('success'))<div class="p-3 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="p-3 bg-red-50 text-red-700 rounded-lg text-sm">{{ $errors->first() }}</div>@endif

    @if($listings->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">{{ __('marketer.classified_no_listings') }}</div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($listings as $l)
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                @if($l->primary_image_url)<img src="{{ $l->primary_image_url }}" alt="" class="w-full h-40 object-cover">@endif
                <div class="p-4 space-y-2">
                    <div class="flex justify-between gap-2">
                        <a href="{{ route('marketer.classified-listings.show', $l) }}" class="font-bold text-gray-900">{{ $l->title }}</a>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ __('marketer.status_'.$l->status->value) }}</span>
                    </div>
                    <div class="text-sm text-gray-600">{{ number_format($l->price) }} {{ $l->currency }}</div>
                    <div class="text-xs text-gray-500">
                        {{ __('marketer.views') }}: {{ $l->views_count }} · {{ __('marketer.inquiries') }}: {{ $l->inquiries_count }}
                        @if($l->pending_inquiries_count) ({{ __('marketer.pending_inquiries') }}: {{ $l->pending_inquiries_count }}) @endif
                    </div>
                    <div class="flex gap-3 text-xs pt-2">
                        <a href="{{ route('marketer.classified-listings.edit', $l) }}" class="text-blue-600">{{ __('marketer.edit') }}</a>
                        @if(in_array($l->status->value, ['active','paused']))
                        <form method="POST" action="{{ route('marketer.classified-listings.toggle', $l) }}">@csrf
                            <button class="text-yellow-700">{{ __('marketer.pause_resume') }}</button></form>
                        @endif
                        <form method="POST" action="{{ route('marketer.classified-listings.destroy', $l) }}" onsubmit="return confirm(@js(__('marketer.confirm_delete')))">@csrf @method('DELETE')
                            <button class="text-red-600">{{ __('marketer.delete') }}</button></form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        {{ $listings->links() }}
    @endif
</div>
@endsection
