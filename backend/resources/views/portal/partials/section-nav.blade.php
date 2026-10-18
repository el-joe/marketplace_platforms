@php
    $isAr = session('locale', 'ar') === 'ar';
    $langToggleUrl = route('portal.language', $isAr ? 'en' : 'ar');
    $navLogo = portal_image('nav', 'logo', 'image', asset('images/nawy_logo_transparent.png'), 'Nawy', 'Nawy');
    $navLogoAria = portal_content('nav', 'logo', 'aria_label', 'Home', 'الصفحة الرئيسية');
    $navMenuAria = portal_content('nav', 'mobile', 'menu_aria', 'Menu', 'القائمة');
    $navCloseAria = portal_content('nav', 'mobile', 'close_aria', 'Close menu', 'إغلاق القائمة');
    $backUrl = $backUrl ?? route('portal.join');
    $backLabel = $backLabel ?? portal_content('nav', 'section_back', 'label', 'Join options', 'خيارات الانضمام');

    $sectionNavLinks = collect($links ?? [])->map(fn (array $link) => [
        'href' => $link['href'],
        'label' => portal_content('nav', 'section_' . ($section ?? 'generic'), $link['key'], $link['label_en'] ?? '', $link['label_ar'] ?? ''),
    ])->all();
@endphp

<div x-data="{ mobileOpen: false, scrolled: false }"
    x-effect="document.body.style.overflow = mobileOpen ? 'hidden' : ''"
    @resize.window="window.innerWidth >= 1024 ? mobileOpen = false : null">

    <header class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
        @scroll.window="scrolled = (window.pageYOffset > 20)"
        :class="scrolled ? 'bg-gray-900/80 backdrop-blur-lg shadow-lg' : 'bg-gray-900/50 backdrop-blur-md'">
        <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-[72px] gap-6">

                <div class="flex items-center gap-6 xl:gap-[48px] h-full">
                    <a href="{{ route('portal.home') }}" class="flex items-center shrink-0" aria-label="{{ $navLogoAria }}">
                        <img src="{{ $navLogo['src'] }}" alt="{{ $navLogo['alt'] }}" width="808" height="780"
                            decoding="async" class="h-[44px] w-auto object-contain">
                    </a>

                    <nav class="hidden lg:flex items-center gap-4 xl:gap-[32px] h-full">
                        @foreach ($sectionNavLinks as $link)
                            <a href="{{ $link['href'] }}"
                                class="relative flex items-center py-2 text-[15px] font-bold text-white transition-all duration-300 hover:opacity-80">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                @include('portal.partials.header-account-controls')

                <div class="flex lg:hidden items-center gap-4">
                    <div class="relative" x-data="{ mobileCountryOpen: false }" @click.outside="mobileCountryOpen = false">
                        <button type="button" @click="mobileCountryOpen = ! mobileCountryOpen"
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
                            class="absolute end-0 top-full mt-3 w-48 rounded-xl bg-[#1c1c1c] border border-white/10 shadow-2xl py-2 z-50">
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
                    <button @click="mobileOpen = true" class="text-white p-1" aria-label="{{ $navMenuAria }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div x-show="mobileOpen" x-cloak
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] bg-gray-900 lg:hidden flex flex-col overflow-y-auto">

        <div class="px-4 sm:px-6 shrink-0">
            <div class="flex items-center justify-between h-[72px]">
                <a href="{{ route('portal.home') }}" @click="mobileOpen = false" class="flex items-center shrink-0">
                    <img src="{{ $navLogo['src'] }}" alt="{{ $navLogo['alt'] }}" width="808" height="780"
                        decoding="async" class="h-[40px] w-auto object-contain">
                </a>
                <button @click="mobileOpen = false" class="text-white p-1 focus:outline-none" aria-label="{{ $navCloseAria }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" width="28" height="28">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="w-full h-px bg-white/10 shrink-0"></div>

        <nav class="px-6 mt-6 flex flex-col gap-1 pb-10">
            @foreach ($sectionNavLinks as $link)
                <a href="{{ $link['href'] }}" @click="mobileOpen = false"
                    class="block py-4 font-bold text-[20px] tracking-wide text-white hover:text-[#0F807E] transition-colors">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="mt-auto px-6 pb-10 shrink-0">
            <div class="w-full h-px bg-white/10 mb-6"></div>
            <a href="{{ $backUrl }}"
                class="flex items-center justify-center w-full bg-[#0F807E] hover:bg-[#0c6665] text-white font-bold text-base py-3 rounded-full transition-colors">
                {{ $backLabel }}
            </a>
        </div>
    </div>
</div>
