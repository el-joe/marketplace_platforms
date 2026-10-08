@php
    $isAr = session('locale', 'ar') === 'ar';
    $langToggleUrl = route('portal.language', $isAr ? 'en' : 'ar');

    $navLinks = [
        ['block' => 'link_home', 'label_ar' => 'الصفحة الرئيسية', 'label_en' => 'Home', 'route' => 'portal.home'],
        [
            'block' => 'link_how_it_works',
            'label_ar' => 'البدء',
            'label_en' => 'Getting Started',
            'route' => 'portal.how-it-works',
            'submenu' => [
                [
                    'label_ar' => 'إعداد حسابك',
                    'label_en' => 'Setting up your account',
                    'route' => route('portal.how-it-works') . '#registering',
                ],
                [
                    'label_ar' => 'إدراج منتجاتك',
                    'label_en' => 'Listing your products',
                    'route' => route('portal.how-it-works') . '#listings',
                ],
                [
                    'label_ar' => 'اختيار نموذج الشحن الخاص بك',
                    'label_en' => 'Choosing your fulfilment model',
                    'route' => route('portal.how-it-works') . '#fulfilment',
                ],
            ],
        ],
        [
            'block' => 'link_fulfillment',
            'label_ar' => 'الشحن والتوصيل',
            'label_en' => 'Shipping & Fulfilment',
            'route' => 'portal.fulfillment',
            'submenu' => [
                [
                    'label_ar' => 'مشحون من ناوي (FBN)',
                    'label_en' => 'Fulfilled by Nawy (FBN)',
                    'route' => route('portal.fulfillment') . '#fbn',
                ],
                [
                    'label_ar' => 'مشحون من الشريك (FBP)',
                    'label_en' => 'Fulfilled by Partner (FBP)',
                    'route' => route('portal.fulfillment') . '#fbp',
                ],
            ],
        ],
        [
            'block' => 'link_smart_tools',
            'label_ar' => 'نمّي بذكاء',
            'label_en' => 'Grow Smarter',
            'route' => 'portal.smart-tools',
            'submenu' => [
                [
                    'label_ar' => 'الإعلان على ناوي',
                    'label_en' => 'Advertising on Nawy',
                    'route' => route('portal.smart-tools') . '#ads',
                ],
                [
                    'label_ar' => 'هيكل رسوم ناوي',
                    'label_en' => 'Nawy\'s Fee Structure',
                    'route' => route('portal.smart-tools') . '#fees',
                ],
                [
                    'label_ar' => 'النمو باستخدام التحليلات',
                    'label_en' => 'Scale with Insights',
                    'route' => route('portal.smart-tools') . '#insights',
                ],
            ],
        ],
        ['block' => 'link_blog', 'label_ar' => 'المدونة', 'label_en' => 'Blog', 'route' => 'portal.blog.index'],
    ];
    foreach ($navLinks as $i => $l) {
        $navLinks[$i]['label'] = portal_content('nav', $l['block'], 'label', $l['label_en'], $l['label_ar']);
    }
@endphp

<div x-data="{ mobileOpen: false, scrolled: false }"
    x-effect="document.body.style.overflow = mobileOpen ? 'hidden' : ''"
    @resize.window="window.innerWidth >= 1024 ? mobileOpen = false : null">

<header class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
    @scroll.window="scrolled = (window.pageYOffset > 20)"
    :class="scrolled ? 'bg-gray-900/80 backdrop-blur-lg shadow-lg' : 'bg-gray-900/50 backdrop-blur-md'">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-[72px] gap-6">

            <div class="flex items-center gap-[48px] h-full">
                {{-- Logo --}}
                <a href="{{ route('portal.home') }}" class="flex items-center shrink-0"
                    aria-label="{{ $isAr ? 'الصفحة الرئيسية' : 'Home' }}">
                    <img src="{{ asset('images/nawy_logo_transparent.png') }}" alt="Nawy" class="h-[40px] w-auto">
                </a>

                {{-- Desktop nav links --}}
                <nav class="hidden lg:flex items-center gap-[32px] h-full">
                    @foreach ($navLinks as $link)
                        <div class="relative group h-full flex items-center" x-data="{ open: false }"
                            @mouseenter="open = true" @mouseleave="open = false">
                            <a href="{{ route($link['route']) }}"
                                class="relative flex items-center py-2 text-[15px] font-bold transition-all duration-300 text-white hover:opacity-80">
                                {{ $link['label'] }}
                                @if (request()->routeIs($link['route']))
                                    <span class="absolute bottom-0 inset-x-0 h-[2px] bg-white rounded-full"></span>
                                @endif
                                <span
                                    class="absolute bottom-0 inset-x-0 h-[2px] bg-white rounded-full scale-x-0 group-hover:scale-x-100 transition-transform duration-300 {{ $isAr ? 'origin-right' : 'origin-left' }}"
                                    x-show="!{{ request()->routeIs($link['route']) ? 'true' : 'false' }}"></span>
                            </a>

                            @if (isset($link['submenu']))
                                <div x-show="open" x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 translate-y-4"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 translate-y-4"
                                    class="absolute {{ $isAr ? 'right-0' : 'left-0' }} top-full w-[280px] z-50" x-cloak>
                                    <div
                                        class="bg-[#1c1c1c] border border-white/5 shadow-2xl rounded-xl py-2 overflow-hidden mt-1">
                                        @foreach ($link['submenu'] as $sub)
                                            <a href="{{ $sub['route'] }}" @click="open = false"
                                                class="relative block px-5 py-3.5 text-[15px] font-bold text-gray-300 hover:text-white transition-all duration-200 flex items-center group/sub hover:bg-white/5">
                                                <div
                                                    class="w-[3px] h-0 bg-[#0F807E] absolute {{ $isAr ? 'right-0' : 'left-0' }} top-1/2 -translate-y-1/2 transition-all duration-300 group-hover/sub:h-[70%] {{ $isAr ? 'rounded-l-full' : 'rounded-r-full' }}">
                                                </div>
                                                <span
                                                    class="transition-transform duration-300 group-hover/sub:{{ $isAr ? '-translate-x-2' : 'translate-x-2' }}">{{ $isAr ? $sub['label_ar'] : $sub['label_en'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </nav>
            </div>

            <div class="hidden lg:flex items-center gap-[32px] h-full">
                <div class="w-px h-[24px] bg-white/20 self-center"></div>

                {{-- Country --}}
                <div class="relative group h-full flex items-center" x-data="{ open: false }" @mouseenter="open = true"
                    @mouseleave="open = false">
                    <button type="button"
                        class="flex items-center gap-[8px] text-[15px] font-bold text-white hover:opacity-80 transition-opacity h-full">
                        @if ($currentCountry)
                            <img src="https://f.nooncdn.com/s/app/com/common/images/flags/{{ strtolower($currentCountry->iso_code_2) }}.svg"
                                alt="{{ $isAr ? $currentCountry->name_ar : $currentCountry->name_en }}" width="20"
                                height="20" class="rounded-sm">
                            <span>{{ $isAr ? $currentCountry->name_ar : $currentCountry->name_en }}</span>
                        @else
                            <span>{{ portal_content('nav', 'country_toggle', 'label', 'Country', 'الدولة') }}</span>
                        @endif
                    </button>

                    <div x-show="open" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-4"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-4"
                        class="absolute {{ $isAr ? 'start-0' : 'end-0' }} top-full w-48 pt-6 -mt-6 z-50" x-cloak>
                        <div class="bg-[#1c1c1c] border border-white/5 shadow-2xl rounded-xl py-2 overflow-hidden mt-1">
                            @foreach ($countries as $country)
                                <a href="{{ route('portal.country', $country->site_code) }}"
                                    class="relative block px-5 py-3 text-[14px] font-bold transition-all duration-200 flex items-center gap-3 group/sub hover:bg-white/5 
                                          {{ $currentCountry && $currentCountry->id === $country->id ? 'text-[#0F807E]' : 'text-gray-300 hover:text-white' }}">
                                    <div
                                        class="w-[3px] h-0 bg-[#0F807E] absolute {{ $isAr ? 'right-0' : 'left-0' }} top-1/2 -translate-y-1/2 transition-all duration-300 group-hover/sub:h-[70%] {{ $isAr ? 'rounded-l-full' : 'rounded-r-full' }}">
                                    </div>
                                    <span
                                        class="flex items-center gap-3 transition-transform duration-300 group-hover/sub:{{ $isAr ? '-translate-x-2' : 'translate-x-2' }}">
                                        <img src="https://f.nooncdn.com/s/app/com/common/images/flags/{{ strtolower($country->iso_code_2) }}.svg"
                                            alt="{{ $isAr ? $country->name_ar : $country->name_en }}" width="18"
                                            height="18" class="rounded-sm">
                                        <span>{{ $isAr ? $country->name_ar : $country->name_en }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Language --}}
                <div class="relative group h-full flex items-center" x-data="{ open: false }" @mouseenter="open = true"
                    @mouseleave="open = false">
                    <button type="button"
                        class="flex items-center gap-[8px] text-[15px] font-bold text-white hover:opacity-80 transition-opacity h-full">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802" />
                        </svg>
                        <span>{{ portal_content('nav', 'language_toggle', 'label', 'English', 'العربية') }}</span>
                    </button>

                    <div x-show="open" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-4"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-4"
                        class="absolute {{ $isAr ? 'start-0' : 'end-0' }} top-full w-40 pt-6 -mt-6 z-50" x-cloak>
                        <div
                            class="bg-[#1c1c1c] border border-white/5 shadow-2xl rounded-xl py-2 overflow-hidden mt-1">
                            <a href="{{ route('portal.language', 'en') }}"
                                class="relative block px-5 py-3 text-[14px] font-bold transition-all duration-200 flex items-center group/sub hover:bg-white/5 {{ !$isAr ? 'text-[#0F807E]' : 'text-gray-300 hover:text-white' }}">
                                <div
                                    class="w-[3px] h-0 bg-[#0F807E] absolute {{ $isAr ? 'right-0' : 'left-0' }} top-1/2 -translate-y-1/2 transition-all duration-300 group-hover/sub:h-[70%] {{ $isAr ? 'rounded-l-full' : 'rounded-r-full' }}">
                                </div>
                                <span
                                    class="transition-transform duration-300 group-hover/sub:{{ $isAr ? '-translate-x-2' : 'translate-x-2' }}">{{ portal_content('nav', 'lang_option_en', 'label', 'English', 'English') }}</span>
                            </a>
                            <a href="{{ route('portal.language', 'ar') }}"
                                class="relative block px-5 py-3 text-[14px] font-bold transition-all duration-200 flex items-center group/sub hover:bg-white/5 {{ $isAr ? 'text-[#0F807E]' : 'text-gray-300 hover:text-white' }}">
                                <div
                                    class="w-[3px] h-0 bg-[#0F807E] absolute {{ $isAr ? 'right-0' : 'left-0' }} top-1/2 -translate-y-1/2 transition-all duration-300 group-hover/sub:h-[70%] {{ $isAr ? 'rounded-l-full' : 'rounded-r-full' }}">
                                </div>
                                <span
                                    class="transition-transform duration-300 group-hover/sub:{{ $isAr ? '-translate-x-2' : 'translate-x-2' }}">{{ portal_content('nav', 'lang_option_ar', 'label', 'العربية', 'العربية') }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mobile controls --}}
            <div class="flex lg:hidden items-center gap-4">
                <div class="relative" x-data="{ mobileCountryOpen: false }" @click.outside="mobileCountryOpen = false">
                    <button type="button" @click="mobileCountryOpen = !mobileCountryOpen"
                        aria-label="{{ portal_content('nav', 'country_toggle', 'mobile_aria_label', 'Select country', 'اختر الدولة') }}">
                        @if ($currentCountry)
                            <img src="https://f.nooncdn.com/s/app/com/common/images/flags/{{ strtolower($currentCountry->iso_code_2) }}.svg"
                                alt="{{ $isAr ? $currentCountry->name_ar : $currentCountry->name_en }}"
                                width="20" height="20" class="rounded-sm">
                        @endif
                    </button>

                    <div x-show="mobileCountryOpen" x-cloak x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="absolute {{ $isAr ? 'start-0' : 'end-0' }} top-full mt-3 w-48 rounded-xl bg-[#1c1c1c] border border-white/10 shadow-2xl py-2 z-50">
                        @foreach ($countries as $country)
                            <a href="{{ route('portal.country', $country->site_code) }}"
                                class="flex items-center gap-[10px] px-4 py-2 text-[14px] font-bold transition-colors
                                      {{ $currentCountry && $currentCountry->id === $country->id ? 'text-[#0F807E]' : 'text-gray-300 hover:text-white hover:bg-white/5' }}">
                                <img src="https://f.nooncdn.com/s/app/com/common/images/flags/{{ strtolower($country->iso_code_2) }}.svg"
                                    alt="{{ $isAr ? $country->name_ar : $country->name_en }}" width="18"
                                    height="18" class="rounded-sm">
                                <span>{{ $isAr ? $country->name_ar : $country->name_en }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
                <a href="{{ $langToggleUrl }}" class="text-white hover:opacity-80 transition-opacity">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802" />
                    </svg>
                </a>
                <button @click="mobileOpen = true" class="text-white p-1"
                    aria-label="{{ $isAr ? 'القائمة' : 'Menu' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" class="fixed inset-0 z-[100] bg-gray-900 lg:hidden flex flex-col">

        <div class="px-4 sm:px-6">
            <div class="flex items-center justify-end h-[72px] gap-6">
                <button @click="mobileOpen = false" class="text-white p-1 focus:outline-none"
                    aria-label="{{ $isAr ? 'إغلاق القائمة' : 'Close menu' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" width="28" height="28">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="px-8 mt-8 flex flex-col space-y-10">
            @foreach ($navLinks as $link)
                <a href="{{ route($link['route']) }}"
                    class="block font-bold text-[22px] tracking-wide transition-colors {{ request()->routeIs($link['route']) ? 'text-[#808080]' : 'text-white' }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</header>

{{-- Mobile drawer — outside <header> so z-[100] is not clipped by z-50 stacking context --}}
<div x-show="mobileOpen"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="{{ $isAr ? 'translate-x-[-100%]' : 'translate-x-full' }} opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-x-0 opacity-100"
    x-transition:leave-end="{{ $isAr ? 'translate-x-[-100%]' : 'translate-x-full' }} opacity-0"
    class="fixed inset-0 z-[100] bg-gray-900 lg:hidden flex flex-col overflow-y-auto">

    {{-- Drawer top bar --}}
    <div class="px-4 sm:px-6 shrink-0">
        <div class="flex items-center justify-between h-[72px]">
            <a href="{{ route('portal.home') }}" @click="mobileOpen = false" class="flex items-center shrink-0">
                <img src="{{ asset('images/nawy_logo_transparent.png') }}" alt="Nawy" class="h-[36px] w-auto">
            </a>
            <button @click="mobileOpen = false" class="text-white p-1 focus:outline-none"
                aria-label="{{ $isAr ? 'إغلاق القائمة' : 'Close menu' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" width="28" height="28">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div class="w-full h-px bg-white/10 shrink-0"></div>

    {{-- Nav links with accordion submenus --}}
    <nav class="px-6 mt-6 flex flex-col gap-1 pb-10">
        @foreach ($navLinks as $link)
            @if (isset($link['submenu']))
                <div x-data="{ subOpen: false }">
                    <button @click="subOpen = !subOpen"
                        class="w-full flex items-center justify-between py-4 font-bold text-[20px] tracking-wide transition-colors
                               {{ request()->routeIs($link['route']) ? 'text-[#0F807E]' : 'text-white' }}">
                        <span>{{ $link['label'] }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" width="18" height="18"
                            :class="subOpen ? 'rotate-90' : '{{ $isAr ? 'rotate-180' : 'rotate-0' }}'"
                            class="transition-transform duration-200">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div x-show="subOpen" x-collapse x-cloak class="pl-4 {{ $isAr ? 'pr-4 pl-0' : '' }} flex flex-col gap-1 pb-2">
                        @foreach ($link['submenu'] as $sub)
                            <a href="{{ $sub['route'] }}" @click="mobileOpen = false"
                                class="flex items-center gap-3 py-3 text-[16px] font-semibold text-gray-300 hover:text-white transition-colors">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#0F807E] shrink-0"></span>
                                {{ $isAr ? $sub['label_ar'] : $sub['label_en'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <a href="{{ route($link['route']) }}" @click="mobileOpen = false"
                    class="block py-4 font-bold text-[20px] tracking-wide transition-colors
                           {{ request()->routeIs($link['route']) ? 'text-[#0F807E]' : 'text-white' }}">
                    {{ $link['label'] }}
                </a>
            @endif
        @endforeach
    </nav>

    {{-- Bottom CTA --}}
    <div class="mt-auto px-6 pb-10 shrink-0">
        <div class="w-full h-px bg-white/10 mb-6"></div>
        <a href="{{ route('portal.register') }}"
            class="flex items-center justify-center w-full bg-[#0F807E] hover:bg-[#0c6665] text-white
                   font-bold text-base py-3 rounded-full transition-colors">
            {{ $isAr ? 'سجل الآن' : 'Sign Up Now' }}
        </a>
    </div>
</div>

</div>{{-- /root Alpine wrapper --}}
