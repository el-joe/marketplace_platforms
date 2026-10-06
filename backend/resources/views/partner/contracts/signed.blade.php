@extends('layouts.partner')
@section('title', $contract->signer_name)
@section('page-title', __('partner.contracts.signed_title'))

@section('content')
<div class="mx-auto max-w-3xl space-y-4 rounded-2xl bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ $contract->contractTemplate?->name ?? __('partner.contracts.signed_title') }}</h2>
            <p class="text-xs text-gray-500">
                v{{ $contract->template_version }} · {{ __('partner.contracts.scope_' . $contract->category_scope) }}
                · {{ __('partner.contracts.signed_on', ['date' => $contract->signed_at?->format('d/m/Y H:i')]) }}
                · <span class="uppercase">{{ $contract->language_signed }}</span>
            </p>
        </div>
        <button type="button" onclick="window.print()" class="rounded bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ __('partner.contracts.print') }}</button>
    </div>
    <div dir="{{ $contract->language_signed === 'ar' ? 'rtl' : 'ltr' }}" class="whitespace-pre-line text-sm leading-relaxed text-gray-800">{!! $contract->rendered_content !!}</div>
    <p class="border-t pt-3 text-xs text-gray-500">{{ __('partner.contracts.signed_by', ['name' => $contract->signer_name, 'ip' => $contract->signed_ip ?? '—']) }}</p>
</div>
@endsection
