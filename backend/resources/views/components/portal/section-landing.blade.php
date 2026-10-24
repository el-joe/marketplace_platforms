{{--
    Shared public section landing layout (travel-agency, marketer types, …).
    Mirrors the product-seller /vendors landing: a full-bleed hero, a benefits
    grid, caller-supplied content via the slot, a numbered "how it works" grid
    and a closing call to action.

    Props:
      hero          => ['eyebrow','title','subtitle','image'=>['src','alt'],'cta'=>['label','url']]
      benefits      => list<['icon','title','desc']>
      benefitsTitle => ?string
      steps         => list<['number','title','desc']>
      stepsTitle    => ?string
      cta           => ['title','subtitle','button'=>['label','url']]
--}}
@props([
    'hero' => [],
    'benefits' => [],
    'benefitsTitle' => null,
    'steps' => [],
    'stepsTitle' => null,
    'cta' => [],
])

@php
    $isAr = session('locale', 'ar') === 'ar';
@endphp

{{-- Hero --}}
<section data-portal-section="hero" class="relative overflow-hidden bg-gray-900">
    <div class="h-[520px] sm:h-[560px] lg:h-[440px] relative">
        <img src="{{ $hero['image']['src'] ?? '' }}" alt="{{ $hero['image']['alt'] ?? '' }}"
            class="absolute inset-0 w-full h-full object-cover object-center">

        <div class="absolute inset-0 {{ $isAr ? 'bg-gradient-to-l' : 'bg-gradient-to-r' }} from-black via-black/90 to-black/40 lg:to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black to-transparent lg:hidden"></div>

        <div class="absolute inset-0 flex items-center">
            <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 w-full">
                <div class="max-w-[560px] {{ $isAr ? 'text-right' : 'text-left' }}">
                    <p class="text-[#feee00] font-black text-xs sm:text-sm uppercase tracking-wider mb-3">
                        {{ $hero['eyebrow'] ?? '' }}
                    </p>
                    <h1 class="text-white font-extrabold leading-[1.15] text-3xl sm:text-4xl lg:text-[46px]">
                        {{ $hero['title'] ?? '' }}
                    </h1>
                    <p class="mt-4 text-gray-300 text-base sm:text-lg font-medium">
                        {{ $hero['subtitle'] ?? '' }}
                    </p>
                    @if (!empty($hero['cta']['url']))
                        <a href="{{ $hero['cta']['url'] }}"
                            class="mt-7 inline-flex items-center justify-center gap-3 bg-[#0F807E] hover:bg-[#0c6665] text-white font-bold text-base min-w-[240px] py-3 rounded-full transition-colors">
                            {{ $hero['cta']['label'] ?? '' }}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" width="20" height="20" class="{{ $isAr ? '-scale-x-100' : '' }}">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

<div class="flex flex-col gap-y-[48px] lg:gap-y-[64px] py-[48px] lg:py-[64px]">

    {{-- Benefits --}}
    @if (!empty($benefits))
        <section data-portal-section="benefits" id="benefits" class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 scroll-mt-[72px]">
            @if ($benefitsTitle)
                <h2 class="text-[#0F807E] font-bold text-[28px] lg:text-[36px] mb-6 lg:mb-8">
                    {{ $benefitsTitle }}
                </h2>
            @endif

            <div class="grid gap-4 md:grid-cols-3 lg:gap-6">
                @foreach ($benefits as $benefit)
                    <div class="rounded-2xl border border-white/10 bg-[#151515] p-6">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[#0F807E]/20 text-[#0F807E]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" width="24" height="24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $benefit['icon'] }}" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-white font-black text-lg">{{ $benefit['title'] }}</h3>
                        <p class="mt-2 text-gray-400 text-sm leading-relaxed">{{ $benefit['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Section-specific content (categories, types, …) --}}
    {{ $slot }}

    {{-- How it works --}}
    @if (!empty($steps))
        <section data-portal-section="steps" id="steps" class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 scroll-mt-[72px]">
            @if ($stepsTitle)
                <h2 class="text-[#0F807E] font-bold text-[28px] lg:text-[36px] mb-6 lg:mb-8">
                    {{ $stepsTitle }}
                </h2>
            @endif

            <div class="grid gap-5 md:grid-cols-3">
                @foreach ($steps as $step)
                    <div class="relative rounded-2xl border border-white/10 bg-[#151515] p-6">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#feee00] text-gray-950 font-black">
                            {{ $step['number'] }}
                        </span>
                        <h3 class="mt-4 text-white font-black text-lg">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-gray-400 text-sm leading-relaxed">{{ $step['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Closing call to action --}}
    @if (!empty($cta))
        <section data-portal-section="cta" class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-[#151515] px-6 py-10 sm:px-10 text-center">
                <h2 class="text-white font-extrabold text-2xl sm:text-3xl">
                    {{ $cta['title'] ?? '' }}
                </h2>
                <p class="mt-3 text-gray-400 text-sm sm:text-base max-w-[60ch] mx-auto">
                    {{ $cta['subtitle'] ?? '' }}
                </p>
                @if (!empty($cta['button']['url']))
                    <a href="{{ $cta['button']['url'] }}"
                        class="mt-7 inline-flex items-center justify-center gap-3 bg-[#0F807E] hover:bg-[#0c6665] text-white font-bold text-base min-w-[240px] py-3 rounded-full transition-colors">
                        {{ $cta['button']['label'] ?? '' }}
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" width="20" height="20" class="{{ $isAr ? '-scale-x-100' : '' }}">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                        </svg>
                    </a>
                @endif
            </div>
        </section>
    @endif
</div>
