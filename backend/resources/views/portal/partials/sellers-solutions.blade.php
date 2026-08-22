@php
    $isAr = session('locale', 'ar') === 'ar';
    $locale = $isAr ? 'ar' : 'en';
    $country = $country ?? 'ae';

    $solutions = [
        [
            'title' => portal_content('sellers', 'solution_product_ads', 'title', 'Product Ads', 'إعلانات المنتجات'),
            'desc' => portal_content('sellers', 'solution_product_ads', 'description',
                "Amplify your products visibility on the lower funnel with targeted ads that reach a larger customer base and enable growth. This feature will significantly increase product visibility, customer reach and conversion potential.",
                'قم بتعزيز رؤية منتجاتك في الأسفل باستخدام الإعلانات المستهدفة التي تصل إلى قاعدة عملاء أكبر وتمكّن النمو. سيعمل هذا المنتج على زيادة ظهور منتجاتك بشكل كبير، والوصول إلى العملاء، وإمكانات التحويل.'),
            'link_label' => portal_content('sellers', 'solution_product_ads', 'link_label', 'Learn More', 'اعرف أكثر'),
            'link' => route('portal.advertise.product', $country),
            'internal' => true,
            'image' => 'https://advertise.noon.com/images/productAds_ar.png',
        ],
        [
            'title' => portal_content('sellers', 'solution_display_ads', 'title', 'Display Ads', 'إعلانات العرض'),
            'desc' => portal_content('sellers', 'solution_display_ads', 'description',
                'Highlight specific campaigns you want to push, including new launches, clearance items, or seasonal offerings to target audiences likely to be interested.',
                'قم بتسليط الضوء على الحملات المحددة التي ترغب في الترويج لها، بما في ذلك عمليات الإطلاق الجديدة أو منتجات التصفية أو العروض الموسمية، لاستهداف الجماهير التي من المحتمل أن تكون مهتمة.'),
            'link_label' => portal_content('sellers', 'solution_display_ads', 'link_label', 'Learn More', 'اعرف أكثر'),
            'link' => route('portal.advertise.display', $country),
            'internal' => true,
            'image' => 'https://advertise.noon.com/images/productPhone_ar.png',
        ],
        [
            'title' => portal_content('sellers', 'solution_crm_social', 'title', 'CRM and Social Media', 'إدارة العلاقات مع العملاء ووسائل التواصل الاجتماعي'),
            'desc' => portal_content('sellers', 'solution_crm_social', 'description',
                'Engage your audience with targeted push notifications and social media campaigns leveraging our wide network of marketers and influencers.',
                'تواصل مع جمهورك من خلال إشعارات الدفع المستهدفة وحملات وسائل التواصل الاجتماعي باستخدام شبكتنا الواسعة من المسوقين والمؤثرين.'),
            'link_label' => portal_content('sellers', 'solution_crm_social', 'link_label', 'Contact us to learn more', 'اتصل بنا لمعرفة المزيد'),
            'link' => route('portal.advertise.request', $country),
            'internal' => true,
            'image' => 'https://advertise.noon.com/images/crm.png',
        ],
        [
            'title' => portal_content('sellers', 'solution_brand_ads', 'title', 'Brand Ads', 'إعلانات العلامة التجارية'),
            'desc' => portal_content('sellers', 'solution_brand_ads', 'description',
                "Promote your products and brand in a visually appealing and prominent way within noon's browse, search, and relevant product detail pages. Display multiple products within one ad, showcasing a wider range of offerings and potentially attracting a broader audience.",
                'قم بالترويج لمنتجاتك وعلامتك التجارية بطريقة بصرية جذابة وبارزة داخل صفحات نون للتصفح، البحث، وصفحات تفاصيل المنتج ذات الصلة. بإمكانك أيضا عرض منتجات متعددة داخل إعلان واحد، وإبراز مجموعة واسعة من منتجاتك وجذب جمهور أكبر.'),
            'link_label' => portal_content('sellers', 'solution_brand_ads', 'link_label', 'Learn More', 'اعرف أكثر'),
            'link' => route('portal.advertise.brands', $country),
            'internal' => true,
            'image' => 'https://advertise.noon.com/images/brandproduct_ar.png',
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
                        <a href="{{ $item['link'] }}"
                           @if(empty($item['internal'])) target="_blank" rel="noopener" @endif
                           class="mt-4 inline-flex items-center gap-2 text-[#1677ff] font-bold text-sm hover:underline">
                            {{ $item['link_label'] }}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" class="shrink-0 {{ $isAr ? '-scale-x-100' : '' }}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                            </svg>
                        </a>
                    </div>
                    <div class="shrink-0 order-1 sm:order-2 w-2/3 sm:w-[160px] lg:w-[200px]">
                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}"
                             loading="lazy" class="w-full h-auto object-contain">
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
