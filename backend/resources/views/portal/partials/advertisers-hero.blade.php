@php
    $isAr = session('locale', 'ar') === 'ar';
    $advertisersHeroImg = portal_image('advertise-advertisers', 'hero', 'image', 'https://advertise.noon.com/images/advertiser-home-ar.png', 'Advertisers', 'المعلنين');
    $advertisersHeroCta = portal_link('advertise-advertisers', 'hero', 'cta_button', 'Contact us', 'اتصل بنا', route('portal.advertise.request', $country ?? 'ae'));
@endphp

<section class="bg-white">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 pt-8 sm:pt-12 lg:pt-16 pb-8 lg:pb-12">
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-16 items-center">
            <div class="order-2 lg:order-1 {{ $isAr ? 'text-center lg:text-right' : 'text-center lg:text-left' }}">
                <p class="text-yellow-500 font-black text-xs sm:text-sm uppercase tracking-wider mb-3">
                    {{ portal_content('advertise-advertisers', 'hero', 'eyebrow', 'Advertisers', 'المعلنين') }}
                </p>
                <p class="text-gray-600 font-medium text-base sm:text-lg max-w-[52ch] mx-auto lg:mx-0">
                    {{ portal_content('advertise-advertisers', 'hero', 'subtitle', 'Not selling on noon, but looking to boost your visibility to large audiences across geographies? Want to increase awareness of specific events/ launches? Planning on sharing specific offers to targeted customers?', "'لا تبيع على نون، ولكنك تتطلع إلى تعزيز ظهورك لجماهير فئة كبيرة عبر المناطق الجغرافية؟ هل ترغب في زيادة الوعي بأحداث/إطلاقات محددة؟ هل تخطط لمشاركة عروض محددة للعملاء المستهدفين؟'") }}
                </p>
                <a href="{{ $advertisersHeroCta['url'] }}"
                   class="mt-6 inline-flex items-center justify-center bg-[#feee00] hover:bg-[#e5d600] text-black
                          font-black text-sm sm:text-base px-6 sm:px-8 py-3 rounded-full transition-colors">
                    {{ $advertisersHeroCta['label'] }}
                </a>
            </div>
            <div class="order-1 lg:order-2">
                <img src="{{ $advertisersHeroImg['src'] }}"
                     alt="{{ $advertisersHeroImg['alt'] }}" loading="eager"
                     class="w-full max-w-[420px] sm:max-w-[480px] mx-auto">
            </div>
        </div>

        <h1 class="text-2xl sm:text-3xl lg:text-[34px] font-extrabold text-gray-900 text-center mt-6 lg:mt-10">
            {{ portal_content('advertise-advertisers', 'hero', 'solutions_title', 'Popular Ad Solutions', 'حلول الإعلانات ذات الشعبية') }}
        </h1>
    </div>
</section>
