@php
    $isAr = session('locale', 'ar') === 'ar';
    $country = $country ?? 'ae';

    $features = [
        [
            'icon' => 'https://advertise.noon.com/images/diversifiedIcon.png',
            'label' => portal_content('advertise-display', 'hero_feature_1', 'label', 'Diversified Audience', 'جمهور متنوع'),
        ],
        [
            'icon' => 'https://advertise.noon.com/images/premiumDiamondIcon.png',
            'label' => portal_content('advertise-display', 'hero_feature_2', 'label', 'Premium Placements', 'التموضع المميز'),
        ],
        [
            'icon' => 'https://advertise.noon.com/images/incresedVisibilityIcon.png',
            'label' => portal_content('advertise-display', 'hero_feature_3', 'label', 'Increased Visibility', 'زيادة الرؤية'),
        ],
    ];

    $displayHeroImg = portal_image('advertise-display', 'hero', 'image', 'https://advertise.noon.com/images/display-ads-home_ar.png', 'Display Ads', 'إعلانات العرض');
@endphp

<section class="bg-white">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 pt-8 sm:pt-12 lg:pt-16 pb-8 lg:pb-12">
        <div class="grid lg:grid-cols-2 rounded-3xl border border-gray-200 overflow-hidden">

            {{-- Text panel --}}
            <div class="order-2 lg:order-1 bg-gray-50 flex flex-col items-center justify-center text-center px-6 sm:px-10 py-10 lg:py-14">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-gray-900 mb-6 text-pretty">
                    {{ portal_content('advertise-display', 'hero', 'title', 'Display Ads', 'إعلانات العرض') }}
                </h1>

                {{-- Feature badges --}}
                <div class="flex items-stretch justify-center gap-2 sm:gap-4 mb-6 sm:mb-8">
                    @foreach($features as $feature)
                        <div class="flex-1 max-w-[120px] sm:max-w-[140px] flex flex-col items-center gap-2 px-2 sm:px-4
                                    {{ !$loop->first ? ($isAr ? 'border-e border-gray-200' : 'border-s border-gray-200') : '' }}">
                            <img src="{{ $feature['icon'] }}" alt="{{ $feature['label'] }}"
                                 loading="eager" class="w-9 h-9 sm:w-11 sm:h-11 object-contain">
                            <span class="text-xs sm:text-sm font-bold text-gray-800 text-center text-pretty leading-tight">
                                {{ $feature['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <p class="text-gray-600 font-medium text-sm sm:text-base max-w-[46ch] mx-auto">
                    {{ portal_content('advertise-display', 'hero', 'subtitle', 'Highlight specific campaigns you want to push, including new launches, clearance items, or seasonal offerings to target audiences likely to be interested.', 'قم بتسليط الضوء على الحملات المحددة التي ترغب في الترويج لها، بما في ذلك عمليات الإطلاق الجديدة أو منتجات التصفية أو العروض الموسمية لاستهداف الجماهير التي من المحتمل أن تكون مهتمة.') }}
                </p>

                <a href="https://admanager.noon.partners/en-{{ $country }}?utm_source=ad_site&utm_medium=display" target="_blank" rel="noopener"
                   class="mt-6 sm:mt-8 inline-flex items-center justify-center bg-white hover:bg-gray-900 hover:text-white text-gray-900
                          border-2 border-gray-900 font-black text-sm sm:text-base px-8 py-3 rounded-full transition-colors">
                    {{ portal_content('advertise-display', 'hero', 'cta_button', 'Start now', 'ابدأ الآن') }}
                </a>
            </div>

            {{-- Image panel --}}
            <div class="order-1 lg:order-2 bg-gradient-to-br from-orange-200 via-rose-200 to-purple-200
                        flex items-center justify-center p-8 sm:p-10 min-h-[280px] sm:min-h-[360px] lg:min-h-full">
                <img src="{{ $displayHeroImg['src'] }}"
                     alt="{{ $displayHeroImg['alt'] }}" loading="eager"
                     class="w-full max-w-[280px] sm:max-w-[360px] lg:max-w-[420px] h-auto object-contain">
            </div>
        </div>
    </div>
</section>
