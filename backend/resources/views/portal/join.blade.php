@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';

    $cards = [
        'product_vendor' => [
            'accent' => '#0F807E',
            'icon' => 'sellers',
            'title' => portal_content('join', 'card_product_vendor', 'title', 'Product Seller', 'بائع منتجات'),
            'desc' => portal_content('join', 'card_product_vendor', 'description', 'Sell physical or digital products and manage your own store on Nawy.', 'بِع منتجاتك المادية أو الرقمية وأدر متجرك الخاص على ناوي.'),
            'image' => portal_image('join', 'card_product_vendor', 'image', asset('images/join/seller.svg'), 'Product seller', 'بائع منتجات'),
        ],
        'classified_vendor' => [
            'accent' => '#feee00',
            'icon' => 'companies',
            'title' => portal_content('join', 'card_classified_vendor', 'title', 'Specialized Companies & Brokers', 'شركات تخصصية ووسطاء'),
            'desc' => portal_content('join', 'card_classified_vendor', 'description', 'Join as a specialized company or an intermediary across real estate, cars, travel, chalets and more.', 'انضم كشركة تخصصية أو وسيط في العقارات والسيارات والسفر والشاليهات وغيرها.'),
            'image' => portal_image('join', 'card_classified_vendor', 'image', asset('images/join/companies.svg'), 'Specialized companies and brokers', 'شركات تخصصية ووسطاء'),
        ],
        'marketer' => [
            'accent' => '#7C5CFF',
            'icon' => 'marketers',
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

@section('content')
    <div class="bg-gray-950 min-h-screen flex flex-col" dir="{{ $isAr ? 'rtl' : 'ltr' }}"
        x-data="{ selected: @js($selectedSection) }">
        <div class="flex-1 w-full max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8 pt-10 sm:pt-14 pb-16 lg:pb-24">

            @php($joinLogo = portal_image('join', 'logo', 'image', asset('images/nawy_logo_transparent.png'), 'Nawy', 'ناوي'))
            <div class="flex flex-col items-center gap-6 mb-10">
                <a href="{{ route('portal.home') }}" aria-label="{{ portal_content('join', 'logo', 'aria_label', 'Nawy', 'ناوي') }}">
                    <img src="{{ $joinLogo['src'] }}" alt="{{ $joinLogo['alt'] }}" class="h-[48px] sm:h-[56px] w-auto">
                </a>
                @include('portal.partials.country-selector', [
                    'countries' => $countries,
                    'currentCountry' => $currentCountry,
                    'showLanguage' => true,
                ])
            </div>

            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-white font-extrabold text-3xl sm:text-4xl lg:text-[44px] leading-tight">
                    {{ portal_content('join', 'hero', 'title', 'Join Nawy', 'انضم إلى ناوي') }}
                </h1>
                <p class="mt-4 text-gray-400 text-base sm:text-lg">
                    {{ portal_content('join', 'hero', 'subtitle', 'Choose the account type that fits you, then continue to the registration experience made for it.', 'اختر نوع الحساب المناسب لك، ثم تابع إلى تجربة التسجيل المخصصة له.') }}
                </p>
            </div>

            @if (session('join_status'))
                <div class="mt-8 max-w-2xl mx-auto rounded-xl border border-[#feee00]/40 bg-[#feee00]/10 px-5 py-4 text-sm font-medium text-[#feee00]">
                    {{ session('join_status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-8 max-w-2xl mx-auto rounded-xl border border-red-500/40 bg-red-500/10 px-5 py-4 text-sm text-red-300">
                    <ul class="space-y-1 {{ $isAr ? 'list-disc pr-5' : 'list-disc pl-5' }}">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('portal.join.select') }}" class="mt-10 sm:mt-12">
                @csrf
                <input type="hidden" name="section" :value="selected">

                <div class="grid gap-5 sm:gap-6" role="group"
                    aria-label="{{ portal_content('join', 'hero', 'title', 'Join Nawy', 'انضم إلى ناوي') }}">
                    @foreach ($sections as $value => $label)
                        @php($card = $cards[$value] ?? null)
                        @if ($card)
                            <button type="button"
                                @click="selected = '{{ $value }}'"
                                :aria-pressed="selected === '{{ $value }}' ? 'true' : 'false'"
                                class="group relative flex w-full flex-col-reverse overflow-hidden rounded-3xl border bg-gray-900 text-start transition-all duration-300 hover:-translate-y-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#feee00] focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950 md:flex-row"
                                :style="selected === '{{ $value }}' ? 'border-color: #feee00' : 'border-color: #1f2937'">

                                {{-- Text half --}}
                                <div class="flex w-full flex-col justify-center gap-5 p-6 sm:p-8 md:w-1/2">
                                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl text-gray-950"
                                        style="background-color: {{ $card['accent'] }}">
                                        @if ($card['icon'] === 'sellers')
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="26" height="26">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
                                            </svg>
                                        @elseif ($card['icon'] === 'companies')
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="26" height="26">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                            </svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="26" height="26">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                                            </svg>
                                        @endif
                                    </span>

                                    <div>
                                        <h2 class="text-white font-extrabold text-xl sm:text-2xl leading-snug">{{ $card['title'] ?? $label }}</h2>
                                        <p class="mt-3 text-sm sm:text-base text-gray-400 leading-relaxed">{{ $card['desc'] }}</p>
                                    </div>
                                </div>

                                {{-- Image half --}}
                                <div class="relative w-full overflow-hidden bg-gray-800 md:w-1/2">
                                    <img src="{{ $card['image']['src'] }}" alt="{{ $card['image']['alt'] }}" loading="lazy"
                                        class="h-52 w-full object-cover transition-transform duration-700 group-hover:scale-105 sm:h-64 md:absolute md:inset-0 md:h-full">
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

                <div class="mt-9 flex flex-col items-center gap-3">
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
