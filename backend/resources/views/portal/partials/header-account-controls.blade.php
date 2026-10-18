{{-- Desktop country + language switchers shared by the main portal nav and the
     section navs, so every portal header matches the /vendors header exactly. --}}
<div class="hidden lg:flex items-center gap-4 xl:gap-[32px] h-full">
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
            class="absolute end-0 top-full w-48 pt-6 -mt-6 z-50" x-cloak>
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
            class="absolute end-0 top-full w-40 pt-6 -mt-6 z-50" x-cloak>
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
