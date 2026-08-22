@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<section class="relative overflow-hidden bg-black">
    {{-- Mobile: near full-viewport hero --}}
    <div class="h-[calc(100svh_-_72px)] md:hidden relative">
        @php($heroImgMobile = portal_image('how-it-works', 'hero', 'photo', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-hero.jpg', 'noon employees working', 'موظفو نون في العمل'))
        <img src="{{ $heroImgMobile['src'] }}"
             alt="{{ $heroImgMobile['alt'] }}"
             class="absolute inset-0 w-full h-full object-cover {{ $isAr ? '-scale-x-100' : '' }}">
    </div>

    {{-- Desktop: fixed-height hero --}}
    <div class="hidden md:block h-[444px] relative">
        @php($heroImgDesktop = portal_image('how-it-works', 'hero', 'photo', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-hero.jpg', 'noon employees working', 'موظفو نون في العمل'))
        <img src="{{ $heroImgDesktop['src'] }}"
             alt="{{ $heroImgDesktop['alt'] }}"
             class="absolute inset-0 w-full h-full object-cover object-[right_center] lg:object-center {{ $isAr ? '-scale-x-100' : '' }}">
    </div>

    <div class="hidden md:block absolute inset-0 {{ $isAr ? 'bg-gradient-to-l' : 'bg-gradient-to-r' }} from-black via-black/85 to-transparent lg:via-black/70"></div>
    <div class="md:hidden absolute inset-x-0 bottom-0 h-[70%] bg-gradient-to-t from-black via-black/80 to-transparent"></div>
    <div class="hidden md:block absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black to-transparent"></div>

    <div class="absolute inset-x-0 inset-y-0 flex items-center md:items-center">
        <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="max-w-[560px] {{ $isAr ? 'text-right' : 'text-left' }}">
                <h1 class="text-white font-black leading-[1.1] text-[40px] sm:text-[44px] md:text-[50px]">
                    {{ portal_content('how-it-works', 'hero', 'title_line1', 'Welcome to', 'مرحبا بك في') }}
                    <span class="block text-[#feee00]">{{ portal_content('how-it-works', 'hero', 'title_line2', 'selling on noon!', 'البيع على نون!') }}</span>
                </h1>
                <p class="mt-3 text-gray-200 font-semibold text-[16px] md:text-[18px] max-w-[500px] leading-relaxed">
                    {{ portal_content('how-it-works', 'hero', 'subtitle',
                        'Turn clicks into cash. Start strong by listing your products the right way — and watch your sales take off.',
                        'حول النقرات إلى أموال. ابدأ بقوة من خلال إدراج منتجاتك بالطريقة الصحيحة — وشاهد مبيعاتك تنطلق.') }}
                </p>
            </div>
        </div>
    </div>
</section>
