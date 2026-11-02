{{-- The single portal country switcher. Every portal header (main nav, section navs,
     choice screens, advertise / adsupport / helpcenter) includes this partial; the
     countries and current selection come from the view composer in AppServiceProvider.
     Params: $variant = 'desktop' | 'mobile', $theme = 'dark' | 'light'. --}}
@php
    $isAr = session('locale', 'ar') === 'ar';
    $variant = $variant ?? 'desktop';
    $light = ($theme ?? 'dark') === 'light';
    $flagUrl = fn ($c) => filled($c?->iso_code_2)
        ? 'https://f.nooncdn.com/s/app/com/common/images/flags/'.strtolower($c->iso_code_2).'.svg'
        : null;
    $nameOf = fn ($c) => $isAr ? $c->name_ar : ($c->name_en ?? $c->name_ar);

    $buttonText = $light ? 'text-gray-700' : 'text-white';
    $panel = $light ? 'bg-white border-gray-200 shadow-xl' : 'bg-[#1c1c1c] border-white/5 shadow-2xl';
    $itemIdle = $light ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-300 hover:text-white hover:bg-white/5';
@endphp

@if ($countries->isNotEmpty())
    <div data-portal-section="country-switcher"
        class="relative {{ $variant === 'desktop' ? 'h-full flex items-center' : '' }}"
        x-data="{ open: false }" @click.outside="open = false"
        @if ($variant === 'desktop') @mouseenter="window.innerWidth >= 1024 && (open = true)"
        @mouseleave="window.innerWidth >= 1024 && (open = false)" @endif>
        <button type="button" @click="open = ! open"
            aria-label="{{ portal_content('nav', 'country_toggle', 'mobile_aria_label', 'Select country', 'اختر الدولة') }}"
            class="flex items-center gap-[8px] text-[15px] font-bold {{ $buttonText }} hover:opacity-80 transition-opacity {{ $variant === 'desktop' ? 'h-full' : '' }}">
            @if ($currentCountry && $flagUrl($currentCountry))
                <img src="{{ $flagUrl($currentCountry) }}" alt="{{ $nameOf($currentCountry) }}" width="20" height="20"
                    class="rounded-sm">
            @endif
            @if ($variant === 'desktop')
                <span>{{ $currentCountry ? $nameOf($currentCountry) : portal_content('nav', 'country_toggle', 'label', 'Country', 'الدولة') }}</span>
            @endif
        </button>

        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            class="absolute end-0 top-full z-50 w-48 {{ $variant === 'desktop' ? 'pt-6 -mt-6' : 'mt-3' }}">
            <div class="{{ $panel }} border rounded-xl py-2 overflow-hidden {{ $variant === 'desktop' ? 'mt-1' : '' }}">
                @foreach ($countries as $option)
                    <a href="{{ route('portal.country', $option->site_code) }}"
                        class="flex items-center gap-3 px-4 py-2.5 text-[14px] font-bold transition-colors {{ $currentCountry && $currentCountry->id === $option->id ? 'text-[#0F807E]' : $itemIdle }}">
                        @if ($flagUrl($option))
                            <img src="{{ $flagUrl($option) }}" alt="{{ $nameOf($option) }}" width="18" height="18"
                                class="rounded-sm">
                        @endif
                        <span>{{ $nameOf($option) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
