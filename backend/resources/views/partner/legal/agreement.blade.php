@extends('layouts.partner')
@section('title', __('partner.legal.gate_title'))
@section('page-title', __('partner.legal.gate_title'))

@section('content')
<div x-data="{ lang: '{{ app()->getLocale() === 'ar' ? 'ar' : 'en' }}' }" class="mx-auto max-w-3xl space-y-5 rounded-2xl bg-white p-6 shadow-sm">
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        {{ $isUpdate ? __('partner.legal.gate_update_intro', ['version' => $template->version]) : __('partner.legal.gate_first_intro') }}
    </div>

    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-semibold text-gray-900">{{ $template->name }} · v{{ $template->version }}</h2>
            @if ($template->change_summary)
                <p class="mt-1 whitespace-pre-line text-xs text-gray-500">{{ $template->change_summary }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <button type="button" @click="lang = 'en'" :class="lang === 'en' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded px-3 py-1 text-xs font-medium">EN</button>
            <button type="button" @click="lang = 'ar'" :class="lang === 'ar' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded px-3 py-1 text-xs font-medium">AR</button>
        </div>
    </div>

    @include('partner.contracts.partials.document', ['doc' => $doc, 'height' => 'h-[28rem]'])

    <form method="POST" action="{{ route('partner.legal.agreement.accept') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="language" :value="lang">
        <input type="text" name="signature_name" required maxlength="150" value="{{ old('signature_name') }}"
               placeholder="{{ __('partner.contracts.full_name_label') }}"
               class="w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="checkbox" name="agreed" value="1" required class="mt-1">
            <span>{{ __('partner.legal.agree_label') }}</span>
        </label>
        <button type="submit" class="w-full rounded-lg bg-blue-600 py-2.5 font-semibold text-white transition hover:bg-blue-700">{{ __('partner.contracts.accept_button') }}</button>
    </form>
</div>
@endsection
