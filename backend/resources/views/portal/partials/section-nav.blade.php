@php
    $isAr = session('locale', 'ar') === 'ar';
    $langToggleUrl = route('portal.language', $isAr ? 'en' : 'ar');
    $navLogo = portal_image('nav', 'logo', 'image', asset('images/nawy_logo_transparent.png'), 'Nawy', 'Nawy');
    $navLogoAria = portal_content('nav', 'logo', 'aria_label', 'Home', 'الصفحة الرئيسية');
    $navMenuAria = portal_content('nav', 'mobile', 'menu_aria', 'Menu', 'القائمة');
    $navCloseAria = portal_content('nav', 'mobile', 'close_aria', 'Close menu', 'إغلاق القائمة');
    $backLabel = portal_content('nav', 'section_back', 'label', 'Join options', 'خيارات الانضمام');

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
                        <img src="{{ $navLogo['src'] }}" alt="{{ $navLogo['alt'] }}" class="h-[40px] w-auto">
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

                <div class="hidden lg:flex items-center gap-4 xl:gap-[24px]">
                    @include('portal.partials.country-selector')
                    <a href="{{ $langToggleUrl }}"
                        class="text-[15px] font-bold text-white hover:opacity-80 transition-opacity">
                        {{ portal_content('nav', 'language_toggle', 'label', 'English', 'العربية') }}
                    </a>
                </div>

                <div class="flex lg:hidden items-center gap-4">
                    @include('portal.partials.country-selector')
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
                    <img src="{{ $navLogo['src'] }}" alt="{{ $navLogo['alt'] }}" class="h-[36px] w-auto">
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

            <div class="mt-2">
                @include('portal.partials.country-selector')
            </div>
        </nav>

        <div class="mt-auto px-6 pb-10 shrink-0">
            <div class="w-full h-px bg-white/10 mb-6"></div>
            <a href="{{ route('portal.join') }}"
                class="flex items-center justify-center w-full bg-[#0F807E] hover:bg-[#0c6665] text-white font-bold text-base py-3 rounded-full transition-colors">
                {{ $backLabel }}
            </a>
        </div>
    </div>
</div>
