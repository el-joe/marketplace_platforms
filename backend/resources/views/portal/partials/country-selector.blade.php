@php
    $isAr = session('locale', 'ar') === 'ar';
    $selectorCountries = $countries ?? collect();
    $selectorCurrent = $currentCountry ?? $selectorCountries->first();
    $selectorLabel = portal_content('nav', 'country_toggle', 'selector_label', 'Your country', 'دولتك');
    $selectorChoose = portal_content('nav', 'country_toggle', 'choose', 'Choose your country', 'اختر دولتك');
    $showLanguage = $showLanguage ?? false;
    $languageChoose = portal_content('nav', 'language_toggle', 'choose', 'Choose your language', 'اختر لغتك');
    $flagUrl = fn ($country) => filled($country?->iso_code_2)
        ? 'https://f.nooncdn.com/s/app/com/common/images/flags/'.strtolower($country->iso_code_2).'.svg'
        : null;
@endphp

@if ($selectorCountries->isNotEmpty())
    <div class="relative inline-block text-start" x-data="{ open: false }" @click.outside="open = false">
        <button type="button" @click="open = ! open"
            class="group inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-3.5 py-2 backdrop-blur-md transition-all duration-200 hover:border-white/25 hover:bg-white/10">
            @if ($selectorCurrent)
                @if ($flagUrl($selectorCurrent))
                    <img src="{{ $flagUrl($selectorCurrent) }}"
                        alt="{{ $isAr ? $selectorCurrent->name_ar : $selectorCurrent->name_en }}"
                        width="24" height="24" class="rounded-full ring-1 ring-white/20">
                @elseif ($selectorCurrent->flag_emoji)
                    <span class="text-xl leading-none">{{ $selectorCurrent->flag_emoji }}</span>
                @endif
                <span class="flex flex-col items-start leading-tight">
                    <span class="flex items-center gap-1.5 text-[13px] font-bold text-white">
                        {{ $isAr ? $selectorCurrent->name_ar : ($selectorCurrent->name_en ?? $selectorCurrent->name_ar) }}
                    </span>
                    <span class="text-[11px] text-gray-400">{{ $selectorLabel }}</span>
                </span>
            @else
                <span class="text-[13px] font-bold text-white">{{ $selectorChoose }}</span>
            @endif
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                width="16" height="16" class="text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
            class="absolute z-50 mt-2 w-64 {{ $isAr ? 'left-0' : 'right-0' }}">
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#1c1c1c]/95 shadow-2xl backdrop-blur-xl">
                <p class="px-4 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wide text-gray-500">
                    {{ $selectorChoose }}
                </p>
                <div class="max-h-80 overflow-y-auto py-1">
                    @foreach ($selectorCountries as $country)
                        <a href="{{ route('portal.country', $country->site_code) }}"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold transition-colors {{ $selectorCurrent && $selectorCurrent->id === $country->id ? 'text-[#0F807E]' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                            @if ($flagUrl($country))
                                <img src="{{ $flagUrl($country) }}" alt="{{ $isAr ? $country->name_ar : $country->name_en }}"
                                    width="20" height="20" class="rounded-full ring-1 ring-white/10">
                            @elseif ($country->flag_emoji)
                                <span class="text-lg leading-none">{{ $country->flag_emoji }}</span>
                            @endif
                            <span class="flex-1">{{ $isAr ? $country->name_ar : ($country->name_en ?? $country->name_ar) }}</span>
                            @if ($selectorCurrent && $selectorCurrent->id === $country->id)
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                    stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            @endif
                        </a>
                    @endforeach
                </div>
                @if ($showLanguage)
                    <div class="border-t border-white/10"></div>
                    <p class="px-4 pb-1 pt-3 text-[11px] font-bold uppercase tracking-wide text-gray-500">
                        {{ $languageChoose }}
                    </p>
                    <div class="py-1">
                        <a href="{{ route('portal.language', 'ar') }}"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold transition-colors {{ $isAr ? 'text-[#0F807E]' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                                stroke="currentColor" width="20" height="20" class="shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.949 8.949 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <span class="flex-1">{{ portal_content('nav', 'lang_option_ar', 'label', 'العربية', 'العربية') }}</span>
                            @if ($isAr)
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                    stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            @endif
                        </a>
                        <a href="{{ route('portal.language', 'en') }}"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold transition-colors {{ ! $isAr ? 'text-[#0F807E]' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                                stroke="currentColor" width="20" height="20" class="shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.949 8.949 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <span class="flex-1">{{ portal_content('nav', 'lang_option_en', 'label', 'English', 'English') }}</span>
                            @if (! $isAr)
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                                    stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            @endif
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
