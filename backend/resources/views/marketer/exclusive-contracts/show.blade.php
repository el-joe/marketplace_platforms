@extends('layouts.marketer')
@section('title', __('marketer.exclusive_contracts'))
@section('page-title', __('marketer.exclusive_contracts'))

@section('content')
<div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm max-w-2xl">
    <div><b>{{ __('marketer.listing_or_category') }}:</b> {{ $contract->classifiedListing?->title ?? $contract->classifiedCategory?->name ?? __('marketer.all_categories') }}</div>
    <div><b>{{ __('marketer.starts_at') }}:</b> {{ $contract->starts_at?->format('Y-m-d') }}</div>
    <div><b>{{ __('marketer.ends_at') }}:</b> {{ $contract->ends_at?->format('Y-m-d') }}</div>
    <div><b>{{ __('marketer.status') }}:</b> {{ __('marketer.contract_'.$contract->status) }}</div>
    @if($contract->notes)<p>{{ $contract->notes }}</p>@endif
    @if($contract->contract_file_path)<a class="text-blue-600" href="{{ route('marketer.exclusive-contracts.download', $contract->id) }}">{{ __('marketer.download_contract') }}</a>@endif
    <div><a href="{{ route('marketer.exclusive-contracts.index') }}" class="text-xs text-blue-600">{{ __('marketer.back') }}</a></div>
</div>
@endsection
