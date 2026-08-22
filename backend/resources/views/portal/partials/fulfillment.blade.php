@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<section class="max-w-[1280px] w-full mx-auto px-4 sm:px-6 lg:px-8">
    <div class="rounded-2xl overflow-hidden bg-[#1c1c1c] grid md:grid-cols-[1.5fr_2fr]">
        <div class="relative aspect-[4/3] md:aspect-auto">
            @php($homeFulfillmentImg = portal_image('home', 'fulfillment_teaser', 'photo', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-fbn.jpg', 'Shipping and Fulfilment', 'الشحن والتوصيل'))
            <img src="{{ $homeFulfillmentImg['src'] }}"
                 alt="{{ $homeFulfillmentImg['alt'] }}"
                 class="absolute inset-0 w-full h-full object-cover">
        </div>
        <div class="pt-8 px-6 pb-8 md:pt-10 md:px-10 md:pb-10">
            <h2 class="text-[#feee00] font-bold text-[28px] lg:text-[36px] mb-2">{{ portal_content('home', 'fulfillment_teaser', 'title', 'Shipping and Fulfilment', 'الشحن والتوصيل') }}</h2>
            <h3 class="text-white font-bold text-[22px] lg:text-[26px] leading-tight mb-8 lg:mb-10">
                {{ portal_content('home', 'fulfillment_teaser', 'subtitle', 'Flexible fulfilment options that work for you', 'خيارات تنفيذ مرنة تناسبك') }}
            </h3>
            <p class="text-gray-300 text-[15px] font-medium mb-6">
                {{ portal_content('home', 'fulfillment_teaser', 'description', 'Pick the model that fits your business today, and scale with confidence', 'اختر النموذج الذي يناسب عملك اليوم، وتوسع بثقة') }}
            </p>
            <ul class="space-y-3 mb-8">
                @foreach([
                    [portal_content('home', 'fulfillment_teaser', 'item_1', 'Fulfilled by noon — Built for speed', 'التنفيذ من قبل نون — مصمم للسرعة')],
                    [portal_content('home', 'fulfillment_teaser', 'item_2', 'Fulfilled by Partner — Built for flexibility', 'التنفيذ من قبل الشريك — مصمم للمرونة')],
                ] as $item)
                    <li class="flex items-start gap-3 text-gray-200 text-[15px]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#feee00" width="18" class="shrink-0 mt-1 {{ $isAr ? '-scale-x-100' : '' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                        <span>{{ $item[0] }}</span>
                    </li>
                @endforeach
            </ul>
            @php($homeFulfillmentLearnMore = portal_link('home', 'fulfillment_teaser', 'learn_more_button', 'Learn more', 'اعرف أكثر', route('portal.fulfillment')))
            <a href="{{ $homeFulfillmentLearnMore['url'] }}" class="inline-flex items-center gap-2 text-[#feee00] font-bold text-[15px] hover:text-[#e5d600] transition-colors">
                {{ $homeFulfillmentLearnMore['label'] }}
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" class="{{ $isAr ? '-scale-x-100' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                </svg>
            </a>
        </div>
    </div>
</section>
