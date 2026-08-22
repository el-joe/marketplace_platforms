@php
    $isAr = session('locale', 'ar') === 'ar';
    $langToggleUrl = route('portal.language', $isAr ? 'en' : 'ar');
    $country = $country ?? 'ae';

    $navSellers = portal_content('nav', 'advertise_nav', 'sellers', 'Sellers', 'البائعين');
    $navBrands = portal_content('nav', 'advertise_nav', 'brands', 'Brands', 'العلامات التجارية');
    $navAdvertisers = portal_content('nav', 'advertise_nav', 'advertisers', 'Advertisers', 'المعلنين');
    $navKnowledgeHub = portal_content('nav', 'advertise_nav', 'knowledge_hub', 'Knowledge Hub', 'مركز المعلومات');
    $navStartNow = portal_content('nav', 'advertise_nav', 'start_now', 'Start now', 'ابدأ الآن');
    $navContactUs = portal_content('nav', 'advertise_nav', 'contact_us', 'Contact us', 'اتصل بنا');
    $navLangShort = $isAr ? 'EN' : 'AR';
@endphp

<header class="sticky top-0 z-50 bg-white border-b border-gray-100" x-data="{ mobileOpen: false }">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-[72px] gap-4">

            {{-- Logo --}}
            <a href="{{ route('portal.sellers', $country) }}" class="flex items-center shrink-0" aria-label="noon ads">
                <img src="https://advertise.noon.com/icons/noon-ads-logo.png" alt="noon ads" width="94" height="29"
                     class="h-7 w-auto">
            </a>

            {{-- Desktop nav links --}}
            <nav class="hidden lg:flex items-center gap-8">
                <a href="{{ route('portal.sellers', $country) }}" class="relative text-[15px] font-bold text-gray-900 py-2">
                    {{ $navSellers }}
                    <span class="absolute -bottom-[1px] inset-x-0 h-[2px] bg-[#feee00] rounded-full"></span>
                </a>
                <a href="{{ route('portal.advertise.brands', $country) }}" class="text-[15px] font-semibold text-gray-500 hover:text-gray-900 py-2">{{ $navBrands }}</a>
                <a href="{{ route('portal.advertise.advertisers', $country) }}" class="text-[15px] font-semibold text-gray-500 hover:text-gray-900 py-2">{{ $navAdvertisers }}</a>
                <a href="{{ route('portal.adsupport.index', $country) }}"
                   class="text-[15px] font-semibold text-gray-500 hover:text-gray-900 py-2">{{ $navKnowledgeHub }}</a>
            </nav>

            <div class="hidden lg:flex items-center gap-5">
                <a href="{{ route('portal.register') }}" target="_blank" rel="noopener"
                   class="bg-[#feee00] hover:bg-[#e5d600] text-black font-black text-sm px-5 py-2.5 rounded-full transition-colors">
                    {{ $navStartNow }}
                </a>
                <a href="{{ route('portal.advertise.request', $country) }}" class="text-[15px] font-semibold text-gray-700 hover:text-gray-900">{{ $navContactUs }}</a>
                <a href="{{ $langToggleUrl }}" class="text-[15px] font-bold text-gray-700 hover:text-gray-900">{{ $navLangShort }}</a>
            </div>

            {{-- Mobile controls --}}
            <div class="flex lg:hidden items-center gap-4">
                <a href="{{ $langToggleUrl }}" class="text-sm font-bold text-gray-700">{{ $navLangShort }}</a>
                <button @click="mobileOpen = !mobileOpen" class="text-gray-900 p-1" aria-label="{{ $isAr ? 'القائمة' : 'Menu' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
                        <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                        <path x-show="mobileOpen" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
         class="lg:hidden bg-white border-t border-gray-100 px-4 py-4 space-y-3">
        <a href="{{ route('portal.sellers', $country) }}" class="block font-bold text-gray-900">{{ $navSellers }}</a>
        <a href="{{ route('portal.advertise.brands', $country) }}" class="block font-semibold text-gray-600">{{ $navBrands }}</a>
        <a href="{{ route('portal.advertise.advertisers', $country) }}" class="block font-semibold text-gray-600">{{ $navAdvertisers }}</a>
        <a href="{{ route('portal.adsupport.index', $country) }}" class="block font-semibold text-gray-600">{{ $navKnowledgeHub }}</a>
        <a href="{{ route('portal.advertise.request', $country) }}" class="block font-semibold text-gray-600">{{ $navContactUs }}</a>
        <a href="https://admanager.noon.partners/en-ae?utm_source=ad_site&utm_medium=header" target="_blank" rel="noopener"
           class="inline-flex bg-[#feee00] text-black font-black text-sm px-5 py-2.5 rounded-full mt-1">
            {{ $navStartNow }}
        </a>
    </div>
</header>
