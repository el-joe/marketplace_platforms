@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<div class="bg-[#151515] pt-8 pb-10 lg:pt-10 lg:pb-14">
    <section class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-[#feee00] font-bold text-[28px] lg:text-[36px] mb-2">{{ portal_content('home', 'smart_tools_teaser', 'title', 'Grow smarter', 'نمِّ أعمالك بذكاء') }}</h2>
        <h3 class="text-white font-bold text-[22px] lg:text-[26px] leading-tight mb-8 lg:mb-10">
            {{ portal_content('home', 'smart_tools_teaser', 'subtitle', 'Everything you need to scale and stay ahead', 'كل ما تحتاجه للتوسع والبقاء في الصدارة') }}
        </h3>

        <div class="grid gap-4 md:grid-cols-3 md:gap-4 lg:gap-6">
            @php
                $cards = [
                    [
                        'image' => 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-join-01.jpg',
                        'title' => portal_content('home', 'smart_tools_teaser_item_1', 'title', 'Ads that deliver results', 'إعلانات تحقق نتائج'),
                        'desc' => portal_content('home', 'smart_tools_teaser_item_1', 'description', 'Use noon Ads, our in-house advertising suite, to put your products in front of more customers.', 'استفد من إعلانات نون، مجموعتنا الإعلانية الداخلية لعرض منتجاتك أمام المزيد من العملاء.'),
                    ],
                    [
                        'image' => 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-fbp.jpg',
                        'title' => portal_content('home', 'smart_tools_teaser_item_2', 'title', 'Know your costs', 'اعرف تكاليفك'),
                        'desc' => portal_content('home', 'smart_tools_teaser_item_2', 'description', 'With noon\'s competitive, transparent fee structure, you\'ll always know what you\'ll earn - no surprises, just growth', 'مع هيكل الرسوم التنافسي والشفاف من نون، ستعرف دائمًا ما ستكسبه - لا مفاجآت، فقط نمو'),
                    ],
                    [
                        'image' => 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-hero-092025.jpg',
                        'title' => portal_content('home', 'smart_tools_teaser_item_3', 'title', 'Scale with insights', 'توسع مع الرؤى'),
                        'desc' => portal_content('home', 'smart_tools_teaser_item_3', 'description', 'Turn data into smarter decisions with our powerful reporting and insights tools', 'حوّل البيانات إلى قرارات أذكى باستخدام أدوات التقارير والرؤى القوية لدينا'),
                    ],
                ];
            @endphp

            @foreach($cards as $card)
                <div class="relative rounded-xl overflow-hidden flex flex-col justify-center sm:justify-end min-h-[140px] sm:min-h-0 sm:aspect-square md:aspect-[4/3] lg:aspect-[16/9]">
                    <img src="{{ $card['image'] }}" alt="{{ $card['title'] }}"
                         class="absolute inset-0 w-full h-full object-cover object-bottom">
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/80 to-transparent"></div>
                    <div class="relative px-4 py-4 sm:pb-5 lg:px-5 lg:pb-6">
                        <h4 class="text-white font-black text-base lg:text-lg text-pretty leading-tight">
                            {{ $card['title'] }}
                        </h4>
                        <p class="mt-1.5 text-gray-300 text-xs sm:text-sm font-medium text-pretty leading-snug">
                            {{ $card['desc'] }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex justify-end">
            @php($smartToolsTeaserCta = portal_link('home', 'smart_tools_teaser', 'learn_more_button', 'Learn more', 'اعرف أكثر', route('portal.smart-tools')))
            <a href="{{ $smartToolsTeaserCta['url'] }}" class="inline-flex items-center gap-2 text-[#feee00] font-bold text-[15px] hover:text-[#e5d600] transition-colors">
                {{ $smartToolsTeaserCta['label'] }}
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" class="{{ $isAr ? '-scale-x-100' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                </svg>
            </a>
        </div>
    </section>
</div>
