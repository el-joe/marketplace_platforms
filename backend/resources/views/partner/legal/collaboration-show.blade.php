@extends('layouts.partner')
@section('title', $contract->template?->name)
@section('page-title', $contract->template?->name)

@section('content')
<div class="mx-auto max-w-4xl space-y-4">
    <a href="{{ route('partner.legal.collaborations') }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('common.back') }}</a>
    <div class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_marketer') }}</dt><dd>{{ $contract->marketer?->name ?? __('contracts.marketer.open_call') }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_campaign') }}</dt><dd>{{ $contract->campaign?->displayTitle() ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('contracts.marketer.col_signed_at') }}</dt><dd>{{ format_tz(vendor_tz($contract->signed_at)) }} · {{ $contract->signer_name }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('admin.contracts.col_version') }}</dt><dd>v{{ $contract->template_version }}</dd></div>
        </dl>
        <div class="border-t pt-4">
            @if ($contract->hasSignedFile())
                <a class="text-blue-600 text-sm" href="{{ route('partner.legal.collaborations.file', $contract->id) }}">{{ $contract->signed_file_name }}</a>
            @else
                <div class="prose max-w-none text-sm" dir="{{ $contract->language_signed === 'ar' ? 'rtl' : 'ltr' }}">{!! $contract->rendered_content !!}</div>
            @endif
        </div>
    </div>
</div>
@endsection
