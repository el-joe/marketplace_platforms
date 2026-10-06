@extends('layouts.partner')
@section('title', __('partner.contracts.pending_title'))
@section('page-title', __('partner.contracts.pending_title'))

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($pending->isEmpty())
        <div class="rounded-2xl bg-white p-8 text-center shadow-sm">
            <p class="text-base font-semibold text-gray-900">{{ __('partner.contracts.none_pending') }}</p>
            <a href="{{ route('partner.dashboard') }}" class="mt-4 inline-block text-sm text-blue-600 underline">{{ __('partner.contracts.back_to_dashboard') }}</a>
        </div>
    @else
        <p class="text-sm text-gray-600">{{ __('partner.contracts.pending_intro') }}</p>

        @foreach ($pending as $item)
            <div x-data="{ lang: 'en' }" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ $item['template']->name }} · v{{ $item['template']->version }}</h2>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $item['category']->name_en }} / {{ $item['category']->name_ar }}
                            · {{ __('partner.contracts.scope_' . $item['scope']) }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="lang = 'en'" :class="lang === 'en' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded px-3 py-1 text-xs font-medium">EN</button>
                        <button type="button" @click="lang = 'ar'" :class="lang === 'ar' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded px-3 py-1 text-xs font-medium">AR</button>
                    </div>
                </div>

                <div x-show="lang === 'en'" dir="ltr" class="max-h-64 overflow-y-auto whitespace-pre-line rounded-lg border p-4 text-sm leading-relaxed text-gray-700">{!! $item['resolved_content_en'] !!}</div>
                <div x-show="lang === 'ar'" x-cloak dir="rtl" class="max-h-64 overflow-y-auto whitespace-pre-line rounded-lg border p-4 text-sm leading-relaxed text-gray-700">{!! $item['resolved_content_ar'] !!}</div>

                <form method="POST" action="{{ route('partner.contracts.accept', $item['template']->id) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="language" :value="lang">
                    <input type="text" name="signature_name" required maxlength="255"
                           placeholder="{{ __('partner.contracts.full_name_label') }}"
                           class="w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="agreed" value="1" required class="mt-1">
                        <span>{{ __('partner.contracts.agree_label') }}</span>
                    </label>
                    <button type="submit" class="w-full rounded-lg bg-blue-600 py-2.5 font-semibold text-white transition hover:bg-blue-700">
                        {{ __('partner.contracts.accept_button') }}
                    </button>
                </form>
            </div>
        @endforeach
    @endif
</div>
@endsection
