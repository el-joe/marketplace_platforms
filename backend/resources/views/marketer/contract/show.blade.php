@extends('layouts.marketer')
@section('title', __('contracts.marketer.onboarding_title'))
@section('page-title', __('contracts.marketer.onboarding_title'))

@section('content')
@php($version = $contract?->activeVersion)
<div class="space-y-4 max-w-3xl">
    <a href="{{ route('marketer.contracts.index') }}" class="text-xs text-blue-600">{{ __('marketer.back') }}</a>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        @if (! $version)
            <p class="text-sm text-gray-500">{{ __('contracts.marketer.no_onboarding') }}</p>
        @else
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold text-gray-900">{{ app()->getLocale() === 'ar' ? ($version->title_ar ?: $version->title_en) : ($version->title_en ?: $version->title_ar) }} <span class="text-xs font-normal text-gray-500">v{{ $version->version_number }}</span></h2>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $accepted ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $accepted ? __('contracts.marketer.accepted') : __('contracts.marketer.not_accepted') }}</span>
            </div>

            @if ($version->content_type === 'pdf')
                <a class="inline-block text-blue-600 text-sm" href="{{ route('marketer.contract.download', $version->id) }}">{{ __('marketer.download_contract') }}</a>
            @else
                <div class="prose max-w-none text-sm whitespace-pre-line">{{ $version->text_content }}</div>
            @endif

            @unless ($accepted)
                <form method="POST" action="{{ route('marketer.contract.accept') }}" onsubmit="return confirm(@js(__('contracts.marketer.accept_confirm')))">
                    @csrf
                    <button class="px-4 py-2 bg-yellow-500 text-gray-900 font-semibold rounded-lg text-sm">{{ __('contracts.marketer.accept_button') }}</button>
                </form>
            @endunless
        @endif
    </div>
</div>
@endsection
