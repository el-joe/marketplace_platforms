@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<section class="bg-white">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 pt-8 sm:pt-12 lg:pt-16 pb-8 lg:pb-12">
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-16 items-center">
            <div class="order-2 lg:order-1 {{ $isAr ? 'text-center lg:text-right' : 'text-center lg:text-left' }}">
                <p class="text-yellow-500 font-black text-xs sm:text-sm uppercase tracking-wider mb-3">
                    {{ portal_content('sellers', 'hero', 'eyebrow', 'Sellers', 'البائعين') }}
                </p>
                <p class="text-gray-600 font-medium text-base sm:text-lg max-w-[52ch] mx-auto lg:mx-0">
                    {{ portal_content('sellers', 'hero', 'subtitle',
                        'Join thousands of sellers leveraging our ad solutions to reach their marketing and sales objectives across locations and irrespective of their budgets.',
                        'انضم إلى آلاف البائعين الذين يستفيدون من حلولنا الإعلانية لتحقيق أهدافهم التسويقية والمبيعاتية عبر المواقع بغض النظر عن ميزانياتهم.') }}
                </p>
                @php($sellersHeroCta = portal_link('sellers', 'hero', 'cta_button', 'Start now', 'ابدأ الآن', route('portal.register')))
                <a href="{{ $sellersHeroCta['url'] }}" target="_blank" rel="noopener"
                   class="mt-6 inline-flex items-center justify-center bg-[#feee00] hover:bg-[#e5d600] text-black
                          font-black text-sm sm:text-base px-6 sm:px-8 py-3 rounded-full transition-colors">
                    {{ $sellersHeroCta['label'] }}
                </a>
            </div>
            <div class="order-1 lg:order-2">
                @php($sellersHeroImg = portal_image('sellers', 'hero', 'photo', 'https://advertise.noon.com/images/sellers-home.png', 'Sellers', 'البائعين'))
                <img src="{{ $sellersHeroImg['src'] }}"
                     alt="{{ $sellersHeroImg['alt'] }}" loading="eager"
                     class="w-full max-w-[420px] sm:max-w-[480px] mx-auto">
            </div>
        </div>

        <h1 class="text-2xl sm:text-3xl lg:text-[34px] font-extrabold text-gray-900 text-center mt-6 lg:mt-10">
            {{ portal_content('sellers', 'hero', 'title', 'Popular Ad Solutions', 'حلول الإعلانات ذات الشعبية') }}
        </h1>
    </div>
</section>
