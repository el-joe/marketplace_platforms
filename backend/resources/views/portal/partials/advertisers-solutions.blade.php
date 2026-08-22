@php
    $isAr = session('locale', 'ar') === 'ar';
    $locale = $isAr ? 'ar' : 'en';
    $country = $country ?? 'ae';

    $solutions = [
        [
            'title' => portal_content('advertise-advertisers', 'solutions_item_1', 'title', 'Managed Display Ads', 'إدارة العرض'),
            'desc' => portal_content('advertise-advertisers', 'solutions_item_1', 'description', 'Obtain additional support from noon ads specialists to access premium onsite placements and reach wider audiences with the support of our specialist.', 'احصل على دعم إضافي من متخصصي إعلانات نون للوصول إلى مواضع متميزة في الموقع والوصول إلى جماهير أوسع بدعم من متخصصينا'),
            'link' => portal_link('advertise-advertisers', 'solutions_item_1', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', route('portal.advertise.request', $country)),
            'internal' => true,
            'image' => portal_image('advertise-advertisers', 'solutions_item_1', 'image', 'https://advertise.noon.com/images/managedDisplayAds_ar.png', 'Managed Display Ads', 'إدارة العرض'),
        ],
        [
            'title' => portal_content('advertise-advertisers', 'solutions_item_2', 'title', 'Offsite Solutions', 'حلول خارج الموقع'),
            'desc' => portal_content('advertise-advertisers', 'solutions_item_2', 'description', 'Attract millions of eyes with high impact OOH solutions strategically placed in high-traffic locations tailored to your ideal audience or go straight to their doorsteps with personalized and targeted BTL marketing.', 'قم بجذب الملايين من المتسوقين من خلال حلول الإعلان الخارجي ذات التأثير العالي والتي تم وضعها بشكل استراتيجي في مواقع ذات حركة مرور كبيرة، وهي مصممة خصيصًا لجمهورك المستهدف. أو يمكنك أن تذهب مباشرة إلىيهم عبر تسويق تحت الخط (BTL) الشخصي والمستهدف.'),
            'link' => portal_link('advertise-advertisers', 'solutions_item_2', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', route('portal.advertise.request', $country)),
            'internal' => true,
            'image' => portal_image('advertise-advertisers', 'solutions_item_2', 'image', 'https://advertise.noon.com/images/holdingImage2.png', 'Offsite Solutions', 'حلول خارج الموقع'),
        ],
        [
            'title' => portal_content('advertise-advertisers', 'solutions_item_3', 'title', 'Sponsorship Opportunities', 'فرص الرعاية'),
            'desc' => portal_content('advertise-advertisers', 'solutions_item_3', 'description', 'Take part in anticipated events and offerings to benefit from exponential traffic giving your brand extra visibility both online and offline. Partner with us and position your brand at the forefront of platform-wide/ category events and impactful collaborations.', 'شارك في الأحداث والعروض المرتقبة للاستفادة من البيانات مما يمنح علامتك التجارية رؤية إضافية سواء عبر الإنترنت أو خارجه. كن شريكًا معنا وضع علامتك التجارية في طليعة الأحداث على مستوى النظام الأساسي/الفئة وعمليات التعاون المؤثرة.'),
            'link' => portal_link('advertise-advertisers', 'solutions_item_3', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', route('portal.advertise.request', $country)),
            'internal' => true,
            'image' => portal_image('advertise-advertisers', 'solutions_item_3', 'image', 'https://advertise.noon.com/images/sponsored.png', 'Sponsorship Opportunities', 'فرص الرعاية'),
        ],
        [
            'title' => portal_content('advertise-advertisers', 'solutions_item_4', 'title', 'CRM and Social Media', 'إدارة العلاقات مع العملاء ووسائل التواصل الاجتماعي'),
            'desc' => portal_content('advertise-advertisers', 'solutions_item_4', 'description', 'Engage your audience with targeted push notifications and social media campaigns leveraging our wide network of marketers and influencers.', 'تفاعل مع جمهورك من خلال إشعارات الدفع المستهدفة وحملات وسائل التواصل الاجتماعي باستخدام شبكتنا الواسعة من المسوقين والمؤثرين.'),
            'link' => portal_link('advertise-advertisers', 'solutions_item_4', 'link', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد', route('portal.advertise.request', $country)),
            'internal' => true,
            'image' => portal_image('advertise-advertisers', 'solutions_item_4', 'image', 'https://advertise.noon.com/images/crm.png', 'CRM and Social Media', 'إدارة العلاقات مع العملاء ووسائل التواصل الاجتماعي'),
        ],
    ];
@endphp

<section class="bg-white">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 pb-12 lg:pb-16">
        <div class="grid md:grid-cols-2 gap-6 lg:gap-8">
            @foreach($solutions as $item)
                <div class="rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-6 lg:p-8
                            flex flex-col sm:flex-row items-center gap-6">
                    <div class="flex-1 {{ $isAr ? 'text-center sm:text-right' : 'text-center sm:text-left' }} order-2 sm:order-1">
                        <h2 class="text-lg lg:text-xl font-extrabold text-gray-900 mb-2 text-pretty">
                            {{ $item['title'] }}
                        </h2>
                        <p class="text-sm text-gray-600 leading-relaxed">
                            {{ $item['desc'] }}
                        </p>
                        <a href="{{ $item['link']['url'] }}"
                           @if(empty($item['internal'])) target="_blank" rel="noopener" @endif
                           class="mt-4 inline-flex items-center gap-2 text-[#1677ff] font-bold text-sm hover:underline">
                            {{ $item['link']['label'] }}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" class="shrink-0 {{ $isAr ? '-scale-x-100' : '' }}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                            </svg>
                        </a>
                    </div>
                    <div class="shrink-0 order-1 sm:order-2 w-2/3 sm:w-[160px] lg:w-[200px]">
                        <img src="{{ $item['image']['src'] }}" alt="{{ $item['image']['alt'] }}"
                             loading="lazy" class="w-full h-auto object-contain">
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
