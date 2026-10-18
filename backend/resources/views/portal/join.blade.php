@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';

    $cards = [
        'product_vendor' => [
            'title' => portal_content('join', 'card_product_vendor', 'title', 'Product Seller', 'بائع منتجات'),
            'desc' => portal_content('join', 'card_product_vendor', 'description', 'Sell physical or digital products and manage your own store on Nawy.', 'بِع منتجاتك المادية أو الرقمية وأدر متجرك الخاص على ناوي.'),
            'image' => portal_image('join', 'card_product_vendor', 'image', asset('images/join/seller.svg'), 'Product seller', 'بائع منتجات'),
        ],
        'classified_vendor' => [
            'title' => portal_content('join', 'card_classified_vendor', 'title', 'Specialized Companies & Brokers', 'شركات تخصصية ووسطاء'),
            'desc' => portal_content('join', 'card_classified_vendor', 'description', 'Join as a specialized company or an intermediary across real estate, cars, travel, chalets and more.', 'انضم كشركة تخصصية أو وسيط في العقارات والسيارات والسفر والشاليهات وغيرها.'),
            'image' => portal_image('join', 'card_classified_vendor', 'image', asset('images/join/companies.svg'), 'Specialized companies and brokers', 'شركات تخصصية ووسطاء'),
        ],
        'marketer' => [
            'title' => portal_content('join', 'card_marketer', 'title', 'Influencers, Creators & Affiliates', 'مشاهير ومؤثرون ومسوقون بالعمولة'),
            'desc' => portal_content('join', 'card_marketer', 'description', 'Promote products and listings as an influencer, creator or commission-based marketer.', 'روّج للمنتجات والإعلانات كمشهور أو مؤثر أو مسوق بالعمولة.'),
            'image' => portal_image('join', 'card_marketer', 'image', asset('images/join/marketer.svg'), 'Influencers, creators and affiliates', 'مشاهير ومؤثرون ومسوقون بالعمولة'),
        ],
    ];

    // Re-select the section on validation round-trips, ignoring stale/invalid values.
    $selectedSection = old('section', $selection['section'] ?? null);
    if ($selectedSection !== null && ! array_key_exists($selectedSection, $sections)) {
        $selectedSection = null;
    }
@endphp

@section('title', portal_content('join', 'meta', 'title', 'Join Nawy', 'انضم إلى ناوي'))
@section('hide_nav', true)

@push('head')
    <style>
        /* No-scroll wizard layout for desktop/laptop only. Mobile & tablet keep normal scrolling. */
        @media (min-width: 1024px) {
            #join-page {
                height: 100vh;
                min-height: 0;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            #join-page .join-content {
                flex: 1 1 auto;
                min-height: 0;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding-top: 1rem;
                padding-bottom: 0.25rem;
            }

            #join-page .join-brand {
                gap: 0.75rem;
                margin-bottom: 0.75rem;
            }

            #join-page .join-brand > a > img {
                height: 36px;
            }

            #join-page .join-hero h1 {
                font-size: 2rem;
                line-height: 1.2;
            }

            #join-page .join-hero p {
                margin-top: 0.5rem;
                font-size: 0.95rem;
                line-height: 1.4;
            }

            #join-page .join-alert {
                margin-top: 0.75rem;
            }

            #join-page .join-form {
                margin-top: 1rem;
            }

            #join-page .join-cards {
                gap: 1rem;
            }

            #join-page .join-card-media {
                height: 150px;
            }

            #join-page .join-card-media img {
                height: 100%;
                max-height: 150px;
                object-fit: cover;
            }

            #join-page .join-card-body {
                gap: 0.5rem;
                padding: 1rem;
            }

            #join-page .join-card-body h2 {
                font-size: 1rem;
                line-height: 1.3;
            }

            #join-page .join-card-body p {
                font-size: 0.8125rem;
                line-height: 1.4;
            }

            #join-page .join-actions {
                margin-top: 0.75rem;
                gap: 0.5rem;
            }

            #join-page .join-actions > button {
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            #join-page footer > div {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
                gap: 0.75rem;
            }

            #join-page footer img {
                height: 26px;
            }
        }
    </style>
@endpush

@section('content')
    <div id="join-page" class="bg-gray-950 min-h-screen flex flex-col" dir="{{ $isAr ? 'rtl' : 'ltr' }}"
        x-data="{ selected: @js($selectedSection) }">
        <div class="join-content flex-1 w-full max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8 pt-10 sm:pt-14 pb-16 lg:pb-24">

            @php($joinLogo = portal_image('join', 'logo', 'image', asset('images/nawy_logo_transparent.png'), 'Nawy', 'ناوي'))
            <div class="join-brand flex flex-col items-center gap-6 mb-10">
                <a href="{{ route('portal.home') }}" aria-label="{{ portal_content('join', 'logo', 'aria_label', 'Nawy', 'ناوي') }}">
                    <img src="{{ $joinLogo['src'] }}" alt="{{ $joinLogo['alt'] }}" class="h-[48px] sm:h-[56px] w-auto">
                </a>
                @include('portal.partials.country-selector', [
                    'countries' => $countries,
                    'currentCountry' => $currentCountry,
                    'showLanguage' => true,
                ])
            </div>

            <div class="join-hero text-center max-w-3xl mx-auto">
                <h1 class="text-white font-extrabold text-3xl sm:text-4xl lg:text-[44px] leading-tight">
                    {{ portal_content('join', 'hero', 'title', 'Join Nawy', 'انضم إلى ناوي') }}
                </h1>
                <p class="mt-4 text-gray-400 text-base sm:text-lg">
                    {{ portal_content('join', 'hero', 'subtitle', 'Choose the account type that fits you, then continue to the registration experience made for it.', 'اختر نوع الحساب المناسب لك، ثم تابع إلى تجربة التسجيل المخصصة له.') }}
                </p>
            </div>

            @if (session('join_status'))
                <div class="join-alert mt-8 max-w-2xl mx-auto rounded-xl border border-[#feee00]/40 bg-[#feee00]/10 px-5 py-4 text-sm font-medium text-[#feee00]">
                    {{ session('join_status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="join-alert mt-8 max-w-2xl mx-auto rounded-xl border border-red-500/40 bg-red-500/10 px-5 py-4 text-sm text-red-300">
                    <ul class="space-y-1 {{ $isAr ? 'list-disc pr-5' : 'list-disc pl-5' }}">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('portal.join.select') }}" class="join-form mt-10 sm:mt-12">
                @csrf
                <input type="hidden" name="section" :value="selected">

                <div class="join-cards grid grid-cols-1 gap-5 sm:gap-6 lg:grid-cols-3" role="group"
                    aria-label="{{ portal_content('join', 'hero', 'title', 'Join Nawy', 'انضم إلى ناوي') }}">
                    @foreach ($sections as $value => $label)
                        @php($card = $cards[$value] ?? null)
                        @if ($card)
                            <button type="button"
                                @click="selected = '{{ $value }}'"
                                :aria-pressed="selected === '{{ $value }}' ? 'true' : 'false'"
                                class="group relative flex h-full w-full flex-col overflow-hidden rounded-3xl border bg-gray-900 text-start transition-all duration-300 hover:-translate-y-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#feee00] focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950"
                                :style="selected === '{{ $value }}' ? 'border-color: #feee00' : 'border-color: #1f2937'">

                                {{-- Image (top half) --}}
                                <div class="join-card-media relative w-full overflow-hidden bg-gray-800">
                                    <img src="{{ $card['image']['src'] }}" alt="{{ $card['image']['alt'] }}" loading="lazy"
                                        class="h-48 w-full object-cover transition-transform duration-700 group-hover:scale-105 sm:h-52 lg:h-44">
                                </div>

                                {{-- Title + description (bottom half) --}}
                                <div class="join-card-body flex flex-1 flex-col gap-3 p-6">
                                    <h2 class="text-white font-extrabold text-lg sm:text-xl leading-snug">{{ $card['title'] ?? $label }}</h2>
                                    <p class="text-sm text-gray-400 leading-relaxed">{{ $card['desc'] }}</p>
                                </div>

                                {{-- Selected check icon --}}
                                <span x-show="selected === '{{ $value }}'" x-cloak
                                    class="pointer-events-none absolute end-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#feee00] text-gray-950 shadow-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" width="18" height="18">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>

                <div class="join-actions mt-9 flex flex-col items-center gap-3">
                    <button type="submit" :disabled="!selected" disabled
                        class="inline-flex min-w-[200px] items-center justify-center gap-2 rounded-full px-8 py-4 text-base font-extrabold transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#feee00] focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950 disabled:cursor-not-allowed"
                        :class="selected
                            ? 'bg-[#feee00] text-gray-950 hover:bg-[#e6d700] active:scale-[0.98]'
                            : 'bg-gray-800 text-gray-500'">
                        {{ portal_content('join', 'actions', 'continue', 'Continue', 'متابعة') }}
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="18" height="18"
                            class="{{ $isAr ? '-scale-x-100' : '' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                        </svg>
                    </button>
                    <p x-show="!selected" x-cloak class="text-xs text-gray-500">
                        {{ portal_content('join', 'actions', 'select_hint', 'Select an option to continue', 'اختر أحد الخيارات للمتابعة') }}
                    </p>
                </div>
            </form>
        </div>
        @include('portal.partials.portal-footer')
    </div>
@endsection
