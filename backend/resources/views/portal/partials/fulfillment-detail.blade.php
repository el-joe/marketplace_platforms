@php $isAr = session('locale', 'ar') === 'ar'; @endphp

@push('head')
<style>
    @keyframes wavyText {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
    }
    .animate-wavy {
        animation: wavyText 0.6s ease-in-out forwards;
    }
</style>
@endpush

{{-- Fulfilled by noon --}}
<section id="fbn" class="bg-[#1c1c1c] pt-8 pb-10 lg:pb-12 md:py-10 lg:py-12">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-[32px] font-extrabold mb-6 lg:mb-8">
            <span class="text-[#feee00]">{{ portal_content('fulfillment', 'fbn', 'title_prefix', 'Fulfilled by noon: ', 'التنفيذ من قبل نون: ') }}</span>
            @php($fbnHighlight = portal_content('fulfillment', 'fbn', 'title_highlight', 'Built for Speed', 'مصمم للسرعة'))
            <span class="relative inline-block"
                  x-data="{
                      text: @js($fbnHighlight),
                      displayed: '',
                      typewriter() {
                          let i = 0;
                          let interval = setInterval(() => {
                              if (i < this.text.length) {
                                  this.displayed += this.text.charAt(i);
                              } else {
                                  clearInterval(interval);
                              }
                              i++;
                          }, 50);
                      }
                  }"
                  x-init="setTimeout(() => typewriter(), 300)">
                <span class="opacity-0">{{ $fbnHighlight }}</span>
                <span class="absolute top-0 {{ $isAr ? 'right-0' : 'left-0' }} text-white whitespace-nowrap" x-text="displayed"></span>
            </span>
        </h2>

        <div class="md:grid md:grid-cols-[1.5fr_2fr] md:gap-10 lg:gap-14">
            <div class="relative aspect-[4/3] sm:aspect-[2/1] md:aspect-auto rounded-2xl overflow-hidden">
                @php($fbnImg = portal_image('fulfillment', 'fbn', 'photo', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-fbn.jpg', 'A noon employee working in the warehouse', 'موظف نون يعمل في المستودع'))
                <img src="{{ $fbnImg['src'] }}"
                     alt="{{ $fbnImg['alt'] }}"
                     class="absolute inset-0 w-full h-full object-cover">
            </div>
            <div class="pt-7 pb-10 md:px-0 md:py-6">
                <h3 class="text-white font-black text-lg lg:text-xl">
                    {{ portal_content('fulfillment', 'fbn', 'headline', 'Customers love fast delivery — and noon express delivers it.', 'العملاء يفضلون التوصيل السريع — ونون إكسبريس يقدمه.') }}
                </h3>
                <p class="mt-4 text-gray-300 font-medium">
                    {{ portal_content('fulfillment', 'fbn', 'description', 'Store your products in our fulfillment centers, and watch orders take off.', 'خزن منتجاتك في مراكز تنفيذ الطلبات لدينا، وشاهد الطلبات تنطلق.') }}
                </p>

                <p class="mt-5 text-[#feee00] font-black text-xs uppercase tracking-wider">{{ portal_content('fulfillment', 'fbn', 'included_label', "What's included:", 'الإضافة:') }}</p>
                <p class="mt-1 text-gray-300 font-medium">
                    {{ portal_content('fulfillment', 'fbn', 'included_text',
                        'Fast inbound shipping, monthly storage in state-of-the-art facilities, product removal services, and returns processing.',
                        'شحن وارد سريع، تخزين شهري في منشآت متطورة، خدمات إزالة المنتجات، ومعالجة المرتجعات.') }}
                </p>

                <p class="mt-4 text-[#feee00] font-black text-xs uppercase tracking-wider">{{ portal_content('fulfillment', 'fbn', 'ideal_label', 'Ideal for:', 'مثالي لـ:') }}</p>
                <p class="mt-1 text-gray-300 font-medium">
                    {{ portal_content('fulfillment', 'fbn', 'ideal_text', 'Best-selling products, new products, and any seller ready to grow.', 'المنتجات الأكثر مبيعا، المنتجات الجديدة، وكل بائع جاهز للنمو.') }}
                </p>

                @php($fbnCta = portal_link('fulfillment', 'fbn', 'cta_button', 'Get started with Fulfilled by noon', 'ابدأ مع التنفيذ من قبل نون', route('portal.register')))
                <a href="{{ $fbnCta['url'] }}"
                   class="inline-flex items-center mt-8 bg-[#feee00] hover:bg-[#e5d600] text-black
                          font-black text-sm px-6 py-3 rounded-full transition-colors">
                    {{ $fbnCta['label'] }}
                </a>
            </div>
        </div>
    </div>
</section>

{{-- FBN testimonial --}}
<div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 mt-[24px] md:mt-[48px]">
    <section class="rounded-2xl border-2 border-[#1c1c1c] overflow-hidden md:flex md:flex-row-reverse md:items-center md:gap-10 md:p-10">
        <div class="mb-6 md:flex-1 md:mb-0">
            <div class="aspect-video rounded-xl overflow-hidden">
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/soM79P27Hm4" title="{{ $isAr ? 'مشغل فيديو يوتيوب' : 'YouTube video player' }}"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
        </div>
        <div class="px-6 pb-8 md:flex-1 md:p-0">
            <h2 class="text-white font-black text-lg lg:text-xl">{{ portal_content('fulfillment', 'fbn_testimonial', 'title', 'Seller success story', 'قصة نجاح بائع') }}</h2>
            <p class="mt-4 text-gray-300 text-sm font-medium">
                {{ portal_content('fulfillment', 'fbn_testimonial', 'quote_1',
                    "noon itself has been a huge achievement — I store with them and it's saved me so much at this point",
                    'نون بالذات عملت إنجاز كبير أنا أخزن عندها وفرت علي أشياء كثيرة بهذه النقطة') }}
            </p>
            <p class="mt-4 text-gray-300 text-sm font-medium">
                {{ portal_content('fulfillment', 'fbn_testimonial', 'quote_2',
                    "They took on this cost and eased the burden on me — by God's grace, they played a huge role in the success of my project and my work",
                    'هي تحملت التكلفة هذه وخففت العبء عني لعبت دور كبير بفضل الله في أنها نجحت مشروعي ونجحتني في عملي') }}
            </p>
            <p class="mt-4 text-xs font-bold text-[#feee00]">- {{ portal_content('fulfillment', 'fbn_testimonial', 'author', 'Aflak Al Thurayya', 'أفلاك الثريا') }}</p>
        </div>
    </section>
</div>

{{-- Fulfilled by partner --}}
<section id="fbp" class="bg-[#1c1c1c] py-10 lg:py-12 mt-[24px] md:mt-[48px]">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
        @php($fbpHighlight = portal_content('fulfillment', 'fbp', 'title_highlight', 'Built for Flexibility', 'مصمم للمرونة'))
        <h2 class="text-2xl sm:text-[32px] font-extrabold mb-6 lg:mb-8"
             x-data="{
                 text: @js($fbpHighlight),
                 play: true,
                 triggerAnimation() {
                     this.play = false;
                     setTimeout(() => this.play = true, 50);
                 }
             }"
             @hashchange.window="if (location.hash === '#fbp') triggerAnimation()">
            <span class="text-[#feee00]">{{ portal_content('fulfillment', 'fbp', 'title_prefix', 'Fulfilled by partner: ', 'التنفيذ من قبل الشريك: ') }}</span>
            <span class="inline-block">
                <template x-for="(char, index) in text.split('')" :key="index">
                    <span class="inline-block text-white"
                          :class="play ? 'animate-wavy' : ''"
                          :style="play ? `animation-delay: ${index * 0.05}s;` : ''"
                          x-text="char === ' ' ? '\u00A0' : char"></span>
                </template>
            </span>
        </h2>

        <div class="md:grid md:grid-cols-[1.5fr_2fr] md:gap-10 lg:gap-14">
            <div class="relative aspect-[4/3] sm:aspect-[2/1] md:aspect-auto rounded-2xl overflow-hidden">
                @php($fbpImg = portal_image('fulfillment', 'fbp', 'photo', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/03-fbp.jpg', 'noon boxes', 'صناديق نون'))
                <img src="{{ $fbpImg['src'] }}"
                     alt="{{ $fbpImg['alt'] }}"
                     class="absolute inset-0 w-full h-full object-cover">
            </div>
            <div class="pt-7 pb-10 md:px-0 md:py-6">
                <h3 class="text-white font-black text-lg lg:text-xl mb-4">
                    {{ portal_content('fulfillment', 'fbp', 'headline',
                        "Some products need a different plan. noon's FBP models give you options:",
                        'بعض المنتجات تحتاج إلى خطة مختلفة. نماذج FBP من نون تمنحك خيارات:') }}
                </h3>
                <ul class="space-y-3">
                    @foreach([
                        [portal_content('fulfillment', 'fbp', 'item_1', 'Direct shipping: You pack, we deliver.', 'الشحن المباشر: أنت تغلّف، ونحن نوصّل.')],
                        [portal_content('fulfillment', 'fbp', 'item_2', 'Direct delivery: Best for high-value products or those that need installation.', 'التوصيل المباشر: الأنسب للمنتجات ذات القيمة العالية أو اللي تحتاج تركيب.')],
                    ] as $item)
                        <li class="flex items-start gap-3 text-gray-300 font-medium text-[15px]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#feee00" width="16" class="shrink-0 mt-1 {{ $isAr ? '-scale-x-100' : '' }}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                            <span>{{ $item[0] }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-5 text-[#feee00] font-black text-xs uppercase tracking-wider">{{ portal_content('fulfillment', 'fbp', 'ideal_label', 'Ideal for:', 'مثالي لـ:') }}</p>
                <p class="mt-1 text-gray-300 font-medium">
                    {{ portal_content('fulfillment', 'fbp', 'ideal_text', 'Heavy, bulky, or specialized products that require a personal touch.', 'المنتجات الثقيلة، الكبيرة، أو المتخصصة التي تتطلب لمسة شخصية.') }}
                </p>

                @php($fbpCta = portal_link('fulfillment', 'fbp', 'cta_button', 'Get started with Fulfilled by partner', 'ابدأ مع التنفيذ من قبل الشريك', route('portal.register')))
                <a href="{{ $fbpCta['url'] }}"
                   class="inline-flex items-center mt-8 bg-[#feee00] hover:bg-[#e5d600] text-black
                          font-black text-sm px-6 py-3 rounded-full transition-colors">
                    {{ $fbpCta['label'] }}
                </a>
            </div>
        </div>
    </div>
</section>

{{-- FBP testimonial --}}
<div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 mt-[24px] md:mt-[48px] mb-[96px]">
    <section class="rounded-2xl border-2 border-[#1c1c1c] overflow-hidden md:flex md:flex-row-reverse md:items-center md:gap-10 md:p-10">
        <div class="mb-6 md:flex-1 md:mb-0">
            <div class="aspect-video rounded-xl overflow-hidden">
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/8W9HHs8WcH0" title="{{ $isAr ? 'مشغل فيديو يوتيوب' : 'YouTube video player' }}"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
        </div>
        <div class="px-6 pb-8 md:flex-1 md:p-0">
            <h2 class="text-white font-black text-lg lg:text-xl">{{ portal_content('fulfillment', 'fbp_testimonial', 'title', 'Seller success story', 'قصة نجاح بائع') }}</h2>
            <p class="mt-4 text-gray-300 text-sm font-medium">
                {{ portal_content('fulfillment', 'fbp_testimonial', 'quote',
                    'The noon team handles every step – from receiving products, to neatly packaging them, to delivering them to the customer. All you have to do as a seller is focus on your business and manage your store on the platform with ease!',
                    'فريق نون بيتكفّل بكل الخطوات – من استلام المنتجات، لتغليفها بشكل مرتب، وتوصيلها للعميل. كل اللي عليك كَبائع هو تركّز على شغلك وتدير متجرك على المنصة بكل سهولة!') }}
            </p>
            <p class="mt-4 text-xs font-bold text-[#feee00]">- {{ portal_content('fulfillment', 'fbp_testimonial', 'author', 'Hekayat Sahab', 'حكايات سحاب') }}</p>
        </div>
    </section>
</div>
