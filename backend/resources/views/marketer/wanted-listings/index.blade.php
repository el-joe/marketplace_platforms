@extends('layouts.marketer')
@section('title', __('marketer.wanted_listings'))
@section('page-title', __('marketer.wanted_listings'))

@section('content')
<div class="space-y-4">
    @include('marketer.classified-listings._tabs')
    @if(session('success'))<div class="p-3 bg-green-50 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>@endif
    @forelse($wanted as $w)
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-sm">
            <div class="flex justify-between">
                <span class="font-bold">{{ app()->getLocale() === 'ar' ? $w->title_ar : ($w->title_en ?: $w->title_ar) }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100">{{ __('marketer.status_'.$w->status) }}</span>
            </div>
            <div class="text-gray-500">{{ $w->classifiedCategory?->name }}</div>
            <div>{{ __('marketer.budget_range') }}: {{ $w->budget_min !== null ? number_format($w->budget_min) : '—' }} - {{ $w->budget_max !== null ? number_format($w->budget_max) : '—' }} {{ $w->currency }}</div>
            @if($w->status === 'active')
            <form method="POST" action="{{ route('marketer.wanted-listings.destroy', $w->id) }}" class="mt-2" onsubmit="return confirm(@js(__('marketer.confirm_delete')))">@csrf @method('DELETE')
                <button class="text-xs text-red-600">{{ __('marketer.cancel') }}</button></form>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">{{ __('marketer.classified_no_wanted') }}</div>
    @endforelse
    {{ $wanted->links() }}
</div>
@endsection
