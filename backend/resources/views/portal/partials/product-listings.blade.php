@php $isAr = session('locale', 'ar') === 'ar'; @endphp

@push('head')
<style>
    @keyframes blink-four-times {
        0%, 100% { opacity: 1; }
        50% { opacity: 0; }
    }
    .animate-blink-4 {
        animation: blink-four-times 0.6s ease-in-out 4 forwards;
    }
</style>
@endpush

@php
    $listingsHeadingAr = portal_content('advertise-product', 'listings', 'heading', null, 'جهز قوائم منتجاتك');
    $listingsHeadingEnPlain = portal_content('advertise-product', 'listings', 'heading_en_plain', 'Get Your', null);
    $listingsHeadingEnHighlight = portal_content('advertise-product', 'listings', 'heading_en_highlight', 'Listings Ready', null);
    $listingsImg1 = portal_image('advertise-product', 'listings', 'catalog_image', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-listings-ready.jpg', 'A noon delivery agent scanning a package', 'مندوب توصيل نون');
    $listingsBrandImg = portal_image('advertise-product', 'listings', 'brand_details_image', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-brand-details.jpg', 'Toys on top of a cabinet', 'ألعاب على رف');
    $listingsCategoryImg = portal_image('advertise-product', 'listings', 'category_info_image', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-category-info.jpg', 'A noon employee walking through the warehouse', 'موظف نون في المستودع');

    $uploadOptions = [
        portal_content('advertise-product', 'listings', 'upload_option_1', 'Single SKU — for curated catalogues', 'منتج واحد — للكتالوجات المنسقة'),
        portal_content('advertise-product', 'listings', 'upload_option_2', 'Bulk upload — for larger catalogues', 'التحميل الجماعي — للكتالوجات الكبيرة'),
        portal_content('advertise-product', 'listings', 'upload_option_3', 'API — for automated systems', 'API — للأنظمة المؤتمتة'),
    ];

    $brandDocs = [
        portal_content('advertise-product', 'listings', 'brand_doc_1', 'Brand authorisation letter', 'خطاب تفويض العلامة التجارية'),
        portal_content('advertise-product', 'listings', 'brand_doc_2', 'Manufacturer licence', 'ترخيص المصنع'),
        portal_content('advertise-product', 'listings', 'brand_doc_3', 'Product or brand registration documents', 'مستندات تسجيل المنتج أو العلامة التجارية'),
    ];

    $tutorialsCta = portal_link('advertise-product', 'listings', 'tutorials_link', 'Watch The Tutorials', 'شاهد فيديوهات الشرح', 'https://www.youtube.com/@noonsellerlab7442/videos');
    $brandRegistryCta = portal_link('advertise-product', 'listings', 'brand_registry_link', 'Read More On Brand Registry', 'اقرأ المزيد عن سجل العلامات التجارية', route('portal.faq'));
    $categoryGuideCta = portal_link('advertise-product', 'listings', 'category_guide_link', 'Read The Guide', 'اقرأ الدليل', route('portal.faq'));
@endphp

<section id="listings" class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="text-2xl sm:text-3xl font-black text-white mb-6 lg:mb-8">
        @if($isAr)
            {{ $listingsHeadingAr }}
        @else
            {{ $listingsHeadingEnPlain }} <span class="text-[#feee00] animate-blink-4 inline-block">{{ $listingsHeadingEnHighlight }}</span>
        @endif
    </h2>

    <div class="rounded-2xl overflow-hidden bg-[#1c1c1c] lg:grid lg:grid-cols-[1.5fr_2fr]">
        <div class="relative aspect-[4/3] lg:aspect-auto">
            <img src="{{ $listingsImg1['src'] }}"
                 alt="{{ $listingsImg1['alt'] }}"
                 class="absolute inset-0 w-full h-full object-cover">
        </div>
        <div class="pt-8 px-6 pb-8 md:pt-10 md:px-10 md:pb-10">
            <h3 class="text-white font-black text-xl lg:text-2xl text-pretty leading-tight">
                {{ portal_content('advertise-product', 'listings', 'catalog_title', "Listing your products on noon is easy with our powerful 'My catalog' tool.", 'إدراج منتجاتك على نون سهل جداً مع أداة "كتالوجي" القوية.') }}
            </h3>
            <p class="mt-4 text-gray-300 text-[14px] font-medium">
                {{ portal_content('advertise-product', 'listings', 'catalog_subtitle', 'Enriching your catalog boosts discovery and conversion of your products.', 'إثراء الكتالوج الخاص بك يعزز اكتشاف منتجاتك ويزيد من نسبة التحويل.') }}
            </p>

            <h4 class="text-[#feee00] font-black text-sm mt-6 mb-3">{{ portal_content('advertise-product', 'listings', 'upload_options_title', 'Upload options:', 'خيارات التحميل:') }}</h4>
            <ul class="space-y-3 mb-6">
                @foreach($uploadOptions as $item)
                    <li class="flex items-start gap-3 text-gray-200 text-[15px] font-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#feee00" width="16" class="shrink-0 mt-1 {{ $isAr ? '-scale-x-100' : '' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-gray-400 text-[14px] font-medium mb-8">
                {{ portal_content('advertise-product', 'listings', 'catalog_footnote', 'You can also set pricing and update stock directly from My Catalogue — all in one place', 'يمكنك أيضًا تحديد الأسعار وتحديث المخزون مباشرة من كتالوجي — كل ذلك في مكان واحد') }}
            </p>

            <a target="_blank" rel="noopener" title="{{ portal_content('advertise-product', 'listings', 'new_window_title', 'Opens in a new window', 'يفتح في نافذة جديدة') }}"
               href="{{ $tutorialsCta['url'] }}"
               class="inline-block border border-[#feee00] text-[#feee00] hover:bg-[#feee00] hover:text-black text-sm font-bold px-6 py-2 rounded-full transition-colors">
                {{ $tutorialsCta['label'] }}
            </a>
        </div>
    </div>

    <h3 class="text-white font-black text-lg lg:text-xl mt-8 lg:mt-10 mb-4">
        {{ portal_content('advertise-product', 'listings', 'brand_details_title', 'Brand details', 'تفاصيل العلامة التجارية') }}
    </h3>

    <div class="grid gap-6 lg:gap-8 lg:grid-cols-[2fr_1fr] lg:items-start">
        {{-- Brand ownership --}}
        <div class="rounded-2xl overflow-hidden bg-[#1c1c1c] lg:grid lg:grid-cols-[1.5fr_2fr] h-full">
            <div class="relative aspect-[4/3] lg:aspect-auto">
                <img src="{{ $listingsBrandImg['src'] }}"
                     alt="{{ $listingsBrandImg['alt'] }}"
                     class="absolute inset-0 w-full h-full object-cover">
            </div>
            <div class="pt-8 px-6 pb-8 md:pt-10 md:px-10 md:pb-10">
                <p class="text-[#feee00] font-black text-xs uppercase tracking-wider">
                    {{ portal_content('advertise-product', 'listings', 'brand_own_eyebrow', 'Own a brand or represent one?', 'تملك علامة تجارية أو تمثل واحدة؟') }}
                </p>
                <h4 class="text-white font-black text-lg mt-1 mb-6 text-pretty">
                    {{ portal_content('advertise-product', 'listings', 'brand_registry_intro', 'noon\'s brand registry gives you the tools to protect your intellectual property rights and build trust with customers.', 'يمنحك سجل العلامات التجارية في نون الأدوات لحماية حقوق الملكية الفكرية وبناء الثقة مع العملاء.') }}
                </h4>

                <h5 class="text-[#feee00] font-black text-sm mb-2">{{ portal_content('advertise-product', 'listings', 'authorised_agent_title', 'Authorised agent?', 'وكيل معتمد؟') }}</h5>
                <p class="text-gray-300 text-[14px] font-medium mb-2">{{ portal_content('advertise-product', 'listings', 'authorised_agent_subtitle', 'Send us the right documents to get started:', 'أرسل لنا المستندات الصحيحة للبدء:') }}</p>
                <ul class="space-y-2 mb-6">
                    @foreach($brandDocs as $doc)
                        <li class="flex items-start gap-3 text-gray-200 text-[14px]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#feee00" width="16" class="shrink-0 mt-1 {{ $isAr ? '-scale-x-100' : '' }}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                            <span>{{ $doc }}</span>
                        </li>
                    @endforeach
                </ul>

                <h5 class="text-[#feee00] font-black text-sm mb-2">{{ portal_content('advertise-product', 'listings', 'distributor_title', 'Distributor?', 'موزع؟') }}</h5>
                <p class="text-gray-300 text-[14px] font-medium mb-6">
                    {{ portal_content('advertise-product', 'listings', 'distributor_subtitle', 'Skip ahead to product and category information.', 'انتقل مباشرة إلى معلومات المنتج والفئة.') }}
                </p>

                <h5 class="text-[#feee00] font-black text-sm mb-2">{{ portal_content('advertise-product', 'listings', 'already_applied_title', 'Already applied?', 'قدمت طلبك؟') }}</h5>
                <p class="text-gray-300 text-[14px] font-medium mb-8">
                    {{ portal_content('advertise-product', 'listings', 'already_applied_subtitle', "Sit tight — wait until your brand registration is complete before adding products, to make sure they're listed under the right brand.", 'خلّك مستعد — انتظر حتى يتم الانتهاء من تسجيل علامتك التجارية قبل البدء في إضافة المنتجات لضمان عرضها تحت العلامة التجارية الصحيحة.') }}
                </p>

                <a href="{{ $brandRegistryCta['url'] }}"
                   class="inline-block w-full sm:w-auto text-center bg-[#feee00] hover:bg-[#e5d600] text-black font-bold text-[15px] px-8 py-2 rounded-full transition-colors">
                    {{ $brandRegistryCta['label'] }}
                </a>
            </div>
        </div>

        {{-- Category info --}}
        <div class="rounded-2xl overflow-hidden border-2 border-[#1c1c1c] h-full">
            <div class="aspect-[4/3]">
                <img src="{{ $listingsCategoryImg['src'] }}"
                     alt="{{ $listingsCategoryImg['alt'] }}"
                     class="w-full h-full object-cover">
            </div>
            <div class="pt-7 px-6 pb-8">
                <h4 class="text-white font-black text-lg">{{ portal_content('advertise-product', 'listings', 'category_info_title', 'Product and category information', 'معلومات المنتج والفئة') }}</h4>
                <p class="mt-4 text-gray-300 text-[14px] font-medium">
                    {{ portal_content('advertise-product', 'listings', 'category_info_subtitle', "Prepare your product and category information before you start adding products — it'll save you time and help you avoid mistakes.", 'حضر معلومات المنتج والفئة قبل ما تبدأ في إضافة المنتجات — سيوفر لك الوقت ويجنبك الأخطاء.') }}
                </p>
                <p class="text-[#feee00] font-black text-xs uppercase tracking-wider mt-5">{{ portal_content('advertise-product', 'listings', 'heads_up_title', 'Heads up:', 'تنبيه:') }}</p>
                <p class="text-gray-300 text-[14px] font-medium">
                    {{ portal_content('advertise-product', 'listings', 'heads_up_subtitle', 'Some categories require approval first, including document checks and seller performance review.', 'بعض الفئات تتطلب الموافقة أولًا، بما في ذلك التحقق من المستندات وأداء البائع.') }}
                </p>
                <a href="{{ $categoryGuideCta['url'] }}"
                   class="inline-block mt-8 w-full sm:w-auto text-center bg-[#feee00] hover:bg-[#e5d600] text-black font-bold text-[15px] px-8 py-2 rounded-full transition-colors">
                    {{ $categoryGuideCta['label'] }}
                </a>
            </div>
        </div>
    </div>
</section>
