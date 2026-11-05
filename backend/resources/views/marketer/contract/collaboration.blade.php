@extends('layouts.marketer')
@section('title', $contract->template?->name)
@section('page-title', $contract->template?->name)

@section('content')
<div class="space-y-4 max-w-3xl">
    <a href="{{ route('marketer.contracts.index') }}" class="text-xs text-blue-600">{{ __('marketer.back') }}</a>
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <dl class="grid gap-3 sm:grid-cols-2 text-sm">
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_seller') }}</dt><dd>{{ $contract->vendor?->store_name }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_campaign') }}</dt><dd>{{ $contract->campaign?->title ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_signed_at') }}</dt><dd>{{ $contract->signed_at?->format('Y-m-d') }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('admin.contracts.col_version') }}</dt><dd>v{{ $contract->template_version }}</dd></div>
        </dl>
        <div class="border-t pt-4">
            @if ($contract->hasSignedFile())
                <a class="text-blue-600 text-sm" href="{{ route('marketer.contracts.collaboration-file', $contract->id) }}">{{ $contract->signed_file_name }}</a>
            @else
                <div class="prose max-w-none text-sm" dir="{{ $contract->language_signed === 'ar' ? 'rtl' : 'ltr' }}">{!! $contract->rendered_content !!}</div>
            @endif
        </div>
    </div>
</div>
@endsection
