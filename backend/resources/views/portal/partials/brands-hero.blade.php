@php
    $isAr = session('locale', 'ar') === 'ar';
    $brandsHeroImg = portal_image('advertise-brands', 'hero', 'image', 'https://advertise.noon.com/images/brands-home.png', 'Brands', 'العلامات التجارية');
    $brandsHeroCta = portal_link('advertise-brands', 'hero', 'cta_button', 'Contact us', 'اتصل بنا', route('portal.advertise.request', $country ?? 'ae'));
@endphp

<section class="bg-white">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 pt-8 sm:pt-12 lg:pt-16 pb-8 lg:pb-12">
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-16 items-center">
            <div class="order-2 lg:order-1 {{ $isAr ? 'text-center lg:text-right' : 'text-center lg:text-left' }}">
                <p class="text-yellow-500 font-black text-xs sm:text-sm uppercase tracking-wider mb-3">
                    {{ portal_content('advertise-brands', 'hero', 'eyebrow', 'Brands', 'العلامات التجارية') }}
                </p>
                <p class="text-gray-600 font-medium text-base sm:text-lg max-w-[52ch] mx-auto lg:mx-0">
                    {{ portal_content('advertise-brands', 'hero', 'subtitle', 'Boost your brand awareness, reach large audiences, and connect with customers by leveraging noon ads strategic products.', 'قم بتعزيز الوعي بعلامتك التجارية، والوصول إلى عملاء أكثر، والتواصل مع العملاء من خلال الاستفادة من المنتجات الإستراتيجية لإعلانات نون') }}
                </p>
                <p class="text-gray-600 font-medium text-base sm:text-lg max-w-[52ch] mx-auto lg:mx-0 mt-3">
                    {{ portal_content('advertise-brands', 'hero', 'subtitle_2', 'Video Ads are now available on noon ads! Take your advertising to the next level by enhancing user browsing experience with a 6 to 45 second video.', 'الإعلانات الفيديوية متاحة الآن على إعلانات العلامة التجارية! ارتق بإعلاناتك إلى المستوى التالي من خلال تحسين تصفح المستخدمين بفيديو يتراوح مدته بين 6 إلى 45 ثانية.') }}
                </p>
                <a href="{{ $brandsHeroCta['url'] }}"
                   class="mt-6 inline-flex items-center justify-center bg-[#feee00] hover:bg-[#e5d600] text-black
                          font-black text-sm sm:text-base px-6 sm:px-8 py-3 rounded-full transition-colors">
                    {{ $brandsHeroCta['label'] }}
                </a>
            </div>
            <div class="order-1 lg:order-2">
                <img src="{{ $brandsHeroImg['src'] }}"
                     alt="{{ $brandsHeroImg['alt'] }}" loading="eager"
                     class="w-full max-w-[420px] sm:max-w-[480px] mx-auto">
            </div>
        </div>

        <h1 class="text-2xl sm:text-3xl lg:text-[34px] font-extrabold text-gray-900 text-center mt-6 lg:mt-10">
            {{ portal_content('advertise-brands', 'hero', 'solutions_title', 'Popular Ad Solutions', 'حلول الإعلانات ذات الشعبية') }}
        </h1>
    </div>
</section>
