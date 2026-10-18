@props([
    'heroTitle',
    'heroSubtitle' => null,
    'cards' => [],
    'fieldName' => 'section',
    'selected' => null,
    'action',
    'submit',
    'selectHint' => null,
    'logo' => null,
    'logoAria' => 'Nawy',
    'countries' => null,
    'currentCountry' => null,
    'countryId' => null,
    'backUrl' => null,
    'backLabel' => null,
    'revealValue' => null,
    'revealTitle' => null,
    'canContinue' => 'selected',
    'noScroll' => false,
    'alpine' => [],
])

@php
    $isAr = session('locale', 'ar') === 'ar';
    $alpineState = array_merge(['selected' => $selected], $alpine);
    $logo = $logo ?? portal_image('nav', 'logo', 'image', asset('images/nawy_logo_transparent.png'), 'Nawy', 'ناوي');
    $hasReveal = $slot->isNotEmpty();
    $gridCols = count($cards) > 2 ? 'lg:grid-cols-3' : 'lg:grid-cols-2';
    $cardsGridClass = $noScroll
        ? "choice-cards grid grid-cols-1 gap-5 sm:gap-6 {$gridCols}"
        : "choice-cards grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-6 {$gridCols}";
@endphp

@if ($noScroll)
@push('head')
    <style>
        /* No-scroll wizard layout for desktop/laptop only. Mobile & tablet keep normal scrolling. */
        @media (min-width: 1024px) {
            #choice-page {
                height: 100vh;
                min-height: 0;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            #choice-page .choice-content {
                flex: 1 1 auto;
                min-height: 0;
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                padding-top: 1.25rem;
                padding-bottom: 1.25rem;
            }

            #choice-page .choice-header > div {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }

            #choice-page .choice-hero h1 {
                font-size: 2rem;
                line-height: 1.2;
            }

            #choice-page .choice-hero p {
                margin-top: 0.75rem;
                font-size: 0.95rem;
                line-height: 1.4;
            }

            #choice-page .choice-alert {
                margin-top: 0.75rem;
            }

            /* Promote the form's children (cards + button) to direct flex items so
               the title, cards and button can be spaced independently. */
            #choice-page .choice-form {
                display: contents;
            }

            #choice-page .choice-cards {
                gap: 1rem;
                margin-top: auto;
            }

            #choice-page .choice-card-media {
                height: 200px;
            }

            #choice-page .choice-card-media img {
                height: 100%;
                max-height: 200px;
                object-fit: cover;
                object-position: center;
            }

            #choice-page .choice-card-body {
                gap: 0.5rem;
                padding: 1.25rem;
            }

            #choice-page .choice-card-body h2 {
                font-size: 1rem;
                line-height: 1.3;
            }

            #choice-page .choice-card-body p {
                font-size: 0.8125rem;
                line-height: 1.4;
            }

            #choice-page .choice-actions {
                margin-top: auto;
                gap: 0.5rem;
            }

            #choice-page .choice-actions > button {
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            #choice-page footer > div {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
        }
    </style>
@endpush
@endif

<div id="choice-page" class="bg-gray-950 min-h-screen flex flex-col" dir="{{ $isAr ? 'rtl' : 'ltr' }}"
    x-data="{{ Js::from($alpineState) }}">

    <header class="choice-header relative z-40 w-full bg-gray-950 border-b border-white/10">
        <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
            <a href="{{ route('portal.home') }}" class="shrink-0" aria-label="{{ $logoAria }}">
                <img src="{{ $logo['src'] }}" alt="{{ $logo['alt'] }}" width="808" height="780" decoding="async"
                    class="h-[44px] sm:h-[48px] w-auto object-contain">
            </a>
            @include('portal.partials.locale-switcher', [
                'countries' => $countries ?? collect(),
                'currentCountry' => $currentCountry,
            ])
        </div>
    </header>

    <div class="choice-content flex-1 w-full max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8 pt-8 sm:pt-10 pb-16 lg:pb-20">

        @if ($backUrl)
            <a href="{{ $backUrl }}"
                class="inline-flex items-center gap-2 text-sm font-bold text-gray-400 hover:text-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" width="18" height="18" class="{{ $isAr ? '-scale-x-100' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ $backLabel }}
            </a>
        @endif

        <div class="choice-hero text-center max-w-3xl mx-auto {{ $backUrl ? 'mt-6' : '' }}">
            <h1 class="text-white font-extrabold text-3xl sm:text-4xl lg:text-[44px] leading-tight">
                {{ $heroTitle }}
            </h1>
            @if ($heroSubtitle)
                <p class="mt-4 text-gray-400 text-base sm:text-lg">{{ $heroSubtitle }}</p>
            @endif
        </div>

        @if (session('join_status'))
            <div class="choice-alert mt-8 max-w-2xl mx-auto rounded-xl border border-[#feee00]/40 bg-[#feee00]/10 px-5 py-4 text-sm font-medium text-[#feee00]">
                {{ session('join_status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="choice-alert mt-8 max-w-2xl mx-auto rounded-xl border border-red-500/40 bg-red-500/10 px-5 py-4 text-sm text-red-300">
                <ul class="space-y-1 {{ $isAr ? 'list-disc pr-5' : 'list-disc pl-5' }}">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="choice-form mt-10 sm:mt-12">
            @csrf
            <input type="hidden" name="{{ $fieldName }}" :value="selected">
            @if ($countryId)
                <input type="hidden" name="country_id" value="{{ $countryId }}">
            @endif

            <div class="{{ $cardsGridClass }}"
                role="group" aria-label="{{ $heroTitle }}">
                @foreach ($cards as $card)
                    <button type="button" @click="selected = '{{ $card['value'] }}'"
                        :aria-pressed="selected === '{{ $card['value'] }}' ? 'true' : 'false'"
                        class="group relative flex h-full w-full flex-col overflow-hidden rounded-3xl border bg-gray-900 text-start transition-all duration-300 hover:-translate-y-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#feee00] focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950"
                        :style="selected === '{{ $card['value'] }}' ? 'border-color: #feee00' : 'border-color: #1f2937'">

                        <div class="choice-card-media relative w-full overflow-hidden bg-gray-800">
                            <img src="{{ $card['image']['src'] }}" alt="{{ $card['image']['alt'] }}" loading="lazy"
                                class="h-48 w-full object-cover transition-transform duration-700 group-hover:scale-105 sm:h-52 lg:h-40">
                        </div>

                        <div class="choice-card-body flex flex-1 flex-col gap-3 p-6">
                            <h2 class="text-white font-extrabold text-lg sm:text-xl leading-snug">{{ $card['title'] }}</h2>
                            <p class="text-sm text-gray-400 leading-relaxed">{{ $card['description'] }}</p>
                        </div>

                        <span x-show="selected === '{{ $card['value'] }}'" x-cloak
                            class="pointer-events-none absolute end-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#feee00] text-gray-950 shadow-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3"
                                stroke="currentColor" width="18" height="18">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </span>
                    </button>
                @endforeach
            </div>

            @if ($hasReveal)
                <div x-show="selected === '{{ $revealValue }}'" x-cloak x-transition.opacity.duration.200ms
                    class="choice-reveal mt-8 sm:mt-10">
                    @if ($revealTitle)
                        <h3 class="text-white font-bold text-lg mb-4">{{ $revealTitle }}</h3>
                    @endif
                    {{ $slot }}
                </div>
            @endif

            <div class="choice-actions mt-9 flex flex-col items-center gap-3">
                <button type="submit" :disabled="!({{ $canContinue }})" @if ($selected === null) disabled @endif
                    class="inline-flex min-w-[200px] items-center justify-center gap-2 rounded-full px-8 py-4 text-base font-extrabold transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#feee00] focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950 disabled:cursor-not-allowed"
                    :class="({{ $canContinue }})
                        ? 'bg-[#feee00] text-gray-950 hover:bg-[#e6d700] active:scale-[0.98]'
                        : 'bg-gray-800 text-gray-500'">
                    {{ $submit }}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                        stroke="currentColor" width="18" height="18" class="{{ $isAr ? '-scale-x-100' : '' }}">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                    </svg>
                </button>
                @if ($selectHint)
                    <p x-show="!({{ $canContinue }})" x-cloak class="text-xs text-gray-500">{{ $selectHint }}</p>
                @endif
            </div>
        </form>
    </div>

    @include('portal.partials.portal-footer')
</div>
