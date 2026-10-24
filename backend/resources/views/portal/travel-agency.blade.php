@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';
    $registerUrl = route('portal.companies.travel-agency.register.show');
    $registerCta = portal_link('travel-agency', 'hero', 'cta_button', 'Register your agency', 'سجّل وكالتك', $registerUrl);

    $benefits = [
        [
            'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0V3m0 18c4.97 0 9-4.03 9-9',
            'title' => portal_content('travel-agency', 'benefits_item_1', 'title', 'Reach more travelers', 'صِل إلى مسافرين أكثر'),
            'desc' => portal_content('travel-agency', 'benefits_item_1', 'description', 'Get your trips in front of millions of shoppers browsing Nawy every day.', 'اعرض رحلاتك أمام ملايين المتسوقين الذين يتصفحون ناوي يومياً.'),
        ],
        [
            'icon' => 'M9 6.75V15m6-6.75V15m-9 6.75h12a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 18 4.5H6A2.25 2.25 0 0 0 3.75 6.75v10.5A2.25 2.25 0 0 0 6 19.5Zm3.75-9h4.5m-4.5 3h4.5',
            'title' => portal_content('travel-agency', 'benefits_item_2', 'title', 'Manage everything in one place', 'أدر كل شيء في مكان واحد'),
            'desc' => portal_content('travel-agency', 'benefits_item_2', 'description', 'Publish packages, track bookings, manage inquiries and your team from one dashboard.', 'انشر الباقات، وتابع الحجوزات، وأدر الاستفسارات وفريقك من لوحة تحكم واحدة.'),
        ],
        [
            'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
            'title' => portal_content('travel-agency', 'benefits_item_3', 'title', 'Grow with campaigns', 'انمُ عبر الحملات'),
            'desc' => portal_content('travel-agency', 'benefits_item_3', 'description', 'Promote your trips through Nawy marketers and pay only on confirmed bookings.', 'روّج رحلاتك عبر مسوّقي ناوي وادفع فقط عند تأكيد الحجوزات.'),
        ],
    ];
@endphp

@section('title', portal_content('travel-agency', 'meta', 'title', 'Register Your Travel Agency — Nawy', 'سجّل وكالة السفر الخاصة بك — ناوي'))
@section('meta_description', portal_content('travel-agency', 'meta', 'description', 'Publish trips, packages and bookings on Nawy and reach travelers across the region.', 'انشر الرحلات والباقات والحجوزات على ناوي وصِل إلى المسافرين في جميع أنحاء المنطقة.'))

@php($travelNav = [
    ['key' => 'benefits', 'href' => '#benefits', 'label_en' => 'Why sell travel', 'label_ar' => 'لماذا تبيع السفر'],
    ['key' => 'publish', 'href' => '#publish', 'label_en' => 'What you can publish', 'label_ar' => 'ما يمكنك نشره'],
    ['key' => 'steps', 'href' => '#steps', 'label_en' => 'How it works', 'label_ar' => 'كيف تبدأ'],
    ['key' => 'register', 'href' => $registerUrl, 'label_en' => 'Register now', 'label_ar' => 'سجّل الآن'],
])

@section('nav')
    @include('portal.partials.section-nav', [
        'section' => 'travel_agency',
        'backUrl' => route('portal.companies'),
        'links' => $travelNav,
    ])
@endsection

@section('content')
    <x-portal.section-landing
        :hero="[
            'eyebrow' => portal_content('travel-agency', 'hero', 'eyebrow', 'Travel Agencies', 'وكالات السفر'),
            'title' => portal_content('travel-agency', 'hero', 'title', 'Put your trips on the map', 'ضع رحلاتك على الخريطة'),
            'subtitle' => portal_content('travel-agency', 'hero', 'subtitle', 'Register your travel agency to publish trips, packages and bookings on Nawy — and reach travelers across the region.', 'سجّل وكالة السفر الخاصة بك لنشر الرحلات والباقات والحجوزات على ناوي — وصِل إلى المسافرين في جميع أنحاء المنطقة.'),
            'image' => portal_image('travel-agency', 'hero', 'photo', asset('images/join/travel-agency.jpg'), 'A travel agency team helping travelers', 'فريق وكالة سفر يساعد المسافرين'),
            'cta' => $registerCta,
        ]"
        :benefits="$benefits"
        :benefits-title="portal_content('travel-agency', 'benefits', 'title', 'Why sell travel on Nawy?', 'لماذا تبيع السفر على ناوي؟')"
        :steps="[
            ['number' => '1', 'title' => portal_content('travel-agency', 'steps', 'step_1_title', 'Create your account', 'أنشئ حسابك'), 'desc' => portal_content('travel-agency', 'steps', 'step_1_text', 'Tell us about your agency and add your license number.', 'أخبرنا عن وكالتك وأضف رقم الترخيص.')],
            ['number' => '2', 'title' => portal_content('travel-agency', 'steps', 'step_2_title', 'Get approved', 'احصل على الموافقة'), 'desc' => portal_content('travel-agency', 'steps', 'step_2_text', 'Our team reviews your details and activates your agency.', 'يراجع فريقنا بياناتك ويُفعّل وكالتك.')],
            ['number' => '3', 'title' => portal_content('travel-agency', 'steps', 'step_3_title', 'Publish your trips', 'انشر رحلاتك'), 'desc' => portal_content('travel-agency', 'steps', 'step_3_text', 'Add packages, manage bookings and start earning.', 'أضف الباقات، وأدر الحجوزات، وابدأ بالربح.')],
        ]"
        :steps-title="portal_content('travel-agency', 'steps', 'title', 'Getting started is simple', 'البدء بسيط')"
        :cta="[
            'title' => portal_content('travel-agency', 'cta', 'title', 'Ready to list your travel agency?', 'جاهز لإدراج وكالة السفر الخاصة بك؟'),
            'subtitle' => portal_content('travel-agency', 'cta', 'subtitle', 'Complete the registration in a few minutes. You can log in after your agency is approved.', 'أكمل التسجيل في بضع دقائق. يمكنك تسجيل الدخول بعد الموافقة على وكالتك.'),
            'button' => portal_link('travel-agency', 'cta', 'button', 'Start registration', 'ابدأ التسجيل', $registerUrl),
        ]">
        {{-- Categories you can publish --}}
        @if ($categories->isNotEmpty())
            <section data-portal-section="categories" id="publish" class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 scroll-mt-[72px]">
                <h2 class="text-[#0F807E] font-bold text-[28px] lg:text-[36px] mb-6 lg:mb-8">
                    {{ portal_content('travel-agency', 'categories', 'title', 'What you can publish', 'ما يمكنك نشره') }}
                </h2>
                <div class="flex flex-wrap gap-3">
                    @foreach ($categories as $category)
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-[#151515] px-4 py-2 text-sm font-semibold text-white">
                            <span class="h-2 w-2 rounded-full bg-[#feee00]"></span>
                            {{ $isAr ? $category->name_ar : ($category->name_en ?? $category->name_ar) }}
                        </span>
                    @endforeach
                </div>
            </section>
        @endif
    </x-portal.section-landing>

    @include('portal.partials.portal-footer')
@endsection
