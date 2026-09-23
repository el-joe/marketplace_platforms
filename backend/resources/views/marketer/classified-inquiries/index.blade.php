@extends('layouts.marketer')
@section('title', __('marketer.received_inquiries'))
@section('page-title', __('marketer.received_inquiries'))

@section('content')
<div class="space-y-4">
    @include('marketer.classified-listings._tabs')
    @if(session('success'))<div class="p-3 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>@endif
    @forelse($inquiries as $inq)
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-sm">
            <div class="flex justify-between">
                <a href="{{ route('marketer.classified-inquiries.show', $inq->id) }}" class="font-bold">{{ __('marketer.inquiry_from') }} {{ $inq->customer?->name }}</a>
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100">{{ __('marketer.status_'.$inq->status->value) }}</span>
            </div>
            <div class="text-gray-500">{{ $inq->listing?->title }} · {{ $inq->contact_phone }}</div>
            <p class="mt-1 text-gray-700">{{ $inq->message }}</p>
            @if($inq->status->value !== 'closed')
            <form method="POST" action="{{ route('marketer.classified-inquiries.close', $inq->id) }}" class="mt-2">@csrf @method('PATCH')
                <button class="text-xs text-red-600">{{ __('marketer.close_inquiry') }}</button></form>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">{{ __('marketer.classified_no_inquiries') }}</div>
    @endforelse
    {{ $inquiries->links() }}
</div>
@endsection
