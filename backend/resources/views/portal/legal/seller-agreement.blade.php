@extends('layouts.portal')

@section('title', __('portal.legal.title'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-12 text-gray-200" x-data="{ lang: '{{ app()->getLocale() === 'ar' ? 'ar' : 'en' }}' }">
    <h1 id="privacy" class="text-2xl font-bold text-white">{{ __('portal.legal.title') }}</h1>
    <p class="mt-2 text-sm text-gray-400">{{ __('portal.legal.intro') }}</p>

    @if ($countries->count() > 1)
        <form method="GET" class="mt-4 flex items-center gap-2 text-sm">
            <label for="legal-country" class="text-gray-400">{{ __('contracts.portal.country_label') }}</label>
            <select id="legal-country" name="country" onchange="this.form.submit()" class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-sm text-gray-200">
                @foreach ($countries as $option)
                    <option value="{{ $option->site_code }}" @selected($country && $country->id === $option->id)>{{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}</option>
                @endforeach
            </select>
        </form>
    @endif

    @if ($notConfigured)
        <div class="mt-4 rounded-xl border border-amber-700 bg-amber-900/30 p-4 text-sm text-amber-200">
            {{ $country ? __('contracts.api.not_configured', ['country' => app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en]) : __('contracts.api.not_configured_no_country') }}
        </div>
    @endif

    @if ($upcoming)
        <div class="mt-4 rounded-xl border border-blue-700 bg-blue-900/30 p-4 text-sm text-blue-200">
            <p class="font-semibold">{{ __('portal.legal.upcoming', ['version' => $upcoming->version, 'date' => $upcoming->effective_at->format('d/m/Y')]) }}</p>
            @if ($upcoming->change_summary)
                <p class="mt-1 whitespace-pre-line text-xs">{{ $upcoming->change_summary }}</p>
            @endif
            @foreach ($upcomingDoc['files'] as $language => $file)
                <a href="{{ $file['url'] }}?download=1" class="mt-2 inline-block text-xs underline">{{ __('portal.legal.download_upcoming') }} ({{ strtoupper($language) }})</a>
            @endforeach
        </div>
    @endif

    @if ($inForce)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-400">{{ $inForce->name }} · v{{ $inForce->version }}
                @if ($inForce->effective_at) · {{ __('portal.legal.effective', ['date' => $inForce->effective_at->format('d/m/Y')]) }} @endif
            </p>
            <div class="flex gap-2">
                <button type="button" @click="lang = 'en'" :class="lang === 'en' ? 'bg-[#0F807E] text-gray-900' : 'bg-gray-800 text-gray-300'" class="rounded px-3 py-1 text-xs font-medium">EN</button>
                <button type="button" @click="lang = 'ar'" :class="lang === 'ar' ? 'bg-[#0F807E] text-gray-900' : 'bg-gray-800 text-gray-300'" class="rounded px-3 py-1 text-xs font-medium">AR</button>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 text-gray-800">
            @if ($inForceDoc['content_mode'] === 'file')
                @foreach (['en', 'ar'] as $language)
                    @php($file = $inForceDoc['files'][$language] ?? ($inForceDoc['files']['ar'] ?? $inForceDoc['files']['en'] ?? null))
                    <div x-show="lang === '{{ $language }}'" @if ($language !== 'en') x-cloak @endif>
                        @if ($file)
                            @if ($file['inline'])
                                <iframe src="{{ $file['url'] }}" title="{{ $file['name'] }}" class="h-[40rem] w-full rounded-lg border"></iframe>
                            @endif
                            <a href="{{ $file['url'] }}?download=1" class="mt-3 inline-block text-sm font-medium text-[#0F807E] hover:underline">{{ __('portal.legal.download') }} · {{ $file['name'] }}</a>
                        @endif
                    </div>
                @endforeach
            @else
                <div x-show="lang === 'en'" dir="ltr" class="whitespace-pre-line text-sm leading-relaxed">{!! $inForceDoc['content_en'] !!}</div>
                <div x-show="lang === 'ar'" x-cloak dir="rtl" class="whitespace-pre-line text-sm leading-relaxed">{!! $inForceDoc['content_ar'] !!}</div>
            @endif
        </div>
    @elseif (! $notConfigured)
        <p class="mt-6 text-sm text-gray-400">{{ __('portal.legal.none') }}</p>
    @endif
</div>
@endsection
