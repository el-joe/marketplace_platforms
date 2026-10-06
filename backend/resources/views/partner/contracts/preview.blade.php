@extends('layouts.partner')
@section('title', $template->name)
@section('page-title', __('partner.contracts.preview_title'))

@section('content')
<div class="mx-auto max-w-3xl space-y-4 rounded-2xl bg-white p-6 shadow-sm" x-data="{ lang: 'en' }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ $template->name }}</h2>
            <p class="text-xs text-gray-500">
                v{{ $template->version }} · {{ __('partner.contracts.scope_' . $template->category_scope) }}
                @if ($category) · {{ $category->name_en }} @endif
            </p>
        </div>
        <div class="flex gap-2">
            <button type="button" @click="lang = 'en'" :class="lang === 'en' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded px-3 py-1 text-xs font-medium">EN</button>
            <button type="button" @click="lang = 'ar'" :class="lang === 'ar' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded px-3 py-1 text-xs font-medium">AR</button>
            <button type="button" onclick="window.print()" class="rounded bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ __('partner.contracts.print') }}</button>
        </div>
    </div>

    <div x-show="lang === 'en'" dir="ltr" class="whitespace-pre-line text-sm leading-relaxed text-gray-800">{!! $resolvedEn !!}</div>
    <div x-show="lang === 'ar'" x-cloak dir="rtl" class="whitespace-pre-line text-sm leading-relaxed text-gray-800">{!! $resolvedAr !!}</div>
</div>
@endsection
