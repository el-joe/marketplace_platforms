@extends('layouts.marketer')
@section('title', __('marketer.inquiry_from'))
@section('page-title', __('marketer.inquiry_from').' '.$inquiry->customer?->name)

@section('content')
<div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm max-w-2xl">
    <div><b>{{ __('marketer.classified_listings') }}:</b> {{ $inquiry->listing?->title }}</div>
    <div><b>{{ __('marketer.customer') }}:</b> {{ $inquiry->customer?->name }}</div>
    <div><b>{{ __('marketer.phone') }}:</b> {{ $inquiry->contact_phone }}</div>
    <div><b>{{ __('marketer.status') }}:</b> {{ __('marketer.status_'.$inquiry->status->value) }}</div>
    <p class="text-gray-700"><b>{{ __('marketer.message') }}:</b> {{ $inquiry->message }}</p>
    @if($inquiry->status->value !== 'closed')
    <form method="POST" action="{{ route('marketer.classified-inquiries.close', $inquiry->id) }}">@csrf @method('PATCH')
        <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-xs">{{ __('marketer.close_inquiry') }}</button></form>
    @endif
    <a href="{{ route('marketer.classified-inquiries.index') }}" class="text-blue-600 text-xs">{{ __('marketer.back') }}</a>
</div>
@endsection
