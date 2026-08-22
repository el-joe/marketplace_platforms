@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<section class="relative overflow-hidden bg-black">
    {{-- Mobile: near full-viewport hero --}}
    <div class="h-[calc(100svh_-_72px)] md:hidden relative">
        @php($smartToolsHeroImgMobile = portal_image('smart-tools', 'hero', 'photo', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/04-hero.jpg', 'A noon warehouse in full motion', 'مستودع نون في حركة كاملة'))
        <img src="{{ $smartToolsHeroImgMobile['src'] }}"
             alt="{{ $smartToolsHeroImgMobile['alt'] }}"
             class="absolute inset-0 w-full h-full object-cover {{ $isAr ? '-scale-x-100' : '' }}">
    </div>

    {{-- Desktop: fixed-height hero, image anchored to one side --}}
    <div class="hidden md:flex md:justify-end h-[444px] relative">
        @php($smartToolsHeroImgDesktop = portal_image('smart-tools', 'hero', 'photo', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/04-hero.jpg', 'A noon warehouse in full motion', 'مستودع نون في حركة كاملة'))
        <img src="{{ $smartToolsHeroImgDesktop['src'] }}"
             alt="{{ $smartToolsHeroImgDesktop['alt'] }}"
             class="h-full w-full max-w-none object-cover object-center lg:w-[80%] xl:w-[70%] xl:object-[100%_25%] {{ $isAr ? '-scale-x-100' : '' }}">
    </div>

    <div class="hidden md:block absolute inset-0 {{ $isAr ? 'bg-gradient-to-l' : 'bg-gradient-to-r' }} from-black via-black/85 to-transparent lg:via-black/70"></div>
    <div class="md:hidden absolute inset-x-0 bottom-0 h-[70%] bg-gradient-to-t from-black via-black/80 to-transparent"></div>
    <div class="hidden md:block absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black to-transparent"></div>

    <div class="absolute inset-x-0 inset-y-0 flex items-center md:items-center">
        <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="max-w-[560px] {{ $isAr ? 'text-right' : 'text-left' }}">
                <h1 class="text-[#feee00] font-black leading-[1.1] text-[40px] sm:text-[44px] md:text-[50px] mb-2">
                    {{ portal_content('smart-tools', 'hero', 'title', 'Grow Smarter', 'نمِّ أعمالك بذكاء') }}
                </h1>
                <h2 class="text-white font-bold text-[24px] sm:text-[28px] md:text-[32px] leading-tight">
                    {{ portal_content('smart-tools', 'hero', 'subtitle', 'Ads, Fees and insights', 'الإعلانات، الرسوم، والرؤى التحليلية') }}
                </h2>
                <p class="mt-3 text-gray-200 font-semibold text-[16px] md:text-[18px] max-w-[500px] leading-relaxed">
                    {{ portal_content('smart-tools', 'hero', 'tagline', 'Spend smarter, scale faster', 'أنفق بذكاء، وتوسّع بسرعة') }}
                </p>
            </div>
        </div>
    </div>
</section>
