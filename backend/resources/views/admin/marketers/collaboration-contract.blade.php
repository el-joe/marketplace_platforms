@extends('layouts.admin')
@section('title', $contract->template?->name)

@section('content')
<div class="mx-auto max-w-4xl space-y-5 p-6">
    <a href="{{ route('admin.marketers.show', $marketer) }}#contracts" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('common.back') }}</a>
    <div class="rounded-xl border bg-white p-6 space-y-4">
        <h1 class="text-xl font-bold text-gray-900">{{ $contract->template?->name }} <span class="text-sm font-normal text-gray-500">v{{ $contract->template_version }}</span></h1>
        <dl class="grid gap-3 sm:grid-cols-2 text-sm">
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_seller') }}</dt><dd>{{ $contract->vendor?->store_name }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_marketer') }}</dt><dd>{{ $marketer->name }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_campaign') }}</dt><dd>{{ $contract->campaign?->title ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_signed_at') }}</dt><dd>{{ admin_tz($contract->signed_at)?->format('Y-m-d H:i') }} · {{ $contract->signer_name }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.signed_ip') }}</dt><dd>{{ $contract->signed_ip }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.hash') }}</dt><dd class="break-all font-mono text-xs">{{ $contract->rendered_content_hash }}</dd></div>
        </dl>
        <div class="border-t pt-4">
            @if ($contract->hasSignedFile())
                <a class="text-primary-600 hover:underline" href="{{ route('admin.marketers.collaboration-contracts.file', [$marketer, $contract]) }}">{{ $contract->signed_file_name }}</a>
            @else
                <div class="prose max-w-none text-sm" dir="{{ $contract->language_signed === 'ar' ? 'rtl' : 'ltr' }}">{!! $contract->rendered_content !!}</div>
            @endif
        </div>
    </div>
</div>
@endsection
