@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';
    $registerUrl = route('marketer.register', ['type' => $marketerType]);

    $icons = [
        'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
        'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
    ];

    $copy = [
        'influencer' => [
            'eyebrow' => ['Influencers', 'المؤثرون'],
            'title' => ['Turn your audience into income', 'حوّل جمهورك إلى دخل'],
            'subtitle' => ['Promote the products and listings you love and earn a fixed amount for every campaign you complete.', 'روّج للمنتجات والإعلانات التي تحبها واربح مبلغًا ثابتًا عن كل حملة تكملها.'],
            'benefits' => [
                ['title' => ['Campaigns that fit you', 'حملات تناسبك'], 'desc' => ['Pick the brands and offers that match your audience and style.', 'اختر العلامات والعروض التي تناسب جمهورك وأسلوبك.']],
                ['title' => ['Fixed pay per campaign', 'أجر ثابت لكل حملة'], 'desc' => ['Know exactly what you earn before you start.', 'اعرف بالضبط ما ستكسبه قبل أن تبدأ.']],
                ['title' => ['Track your results', 'تابع نتائجك'], 'desc' => ['See reach, engagement and payouts from one dashboard.', 'شاهد الوصول والتفاعل والمدفوعات من لوحة واحدة.']],
            ],
        ],
        'affiliate' => [
            'eyebrow' => ['Affiliates', 'المسوقون بالعمولة'],
            'title' => ['Earn from every sale you drive', 'اربح من كل عملية بيع تحققها'],
            'subtitle' => ['Share affiliate links and earn a percentage of every sale — no campaigns to manage.', 'شارك روابط التسويق واربح نسبة من كل عملية بيع — دون إدارة حملات.'],
            'benefits' => [
                ['title' => ['Unlimited earning', 'أرباح بلا حدود'], 'desc' => ['The more sales you drive, the more you earn.', 'كلما زادت مبيعاتك، زادت أرباحك.']],
                ['title' => ['Real-time links', 'روابط فورية'], 'desc' => ['Generate tracking links for any product or listing in seconds.', 'أنشئ روابط تتبع لأي منتج أو إعلان في ثوانٍ.']],
                ['title' => ['Transparent reporting', 'تقارير شفافة'], 'desc' => ['Follow clicks, conversions and commissions live.', 'تابع النقرات والتحويلات والعمولات لحظيًا.']],
            ],
        ],
        'broker' => [
            'eyebrow' => ['Brokers', 'الوسطاء'],
            'title' => ['Connect deals and get paid', 'اربط الصفقات واحصل على أجرك'],
            'subtitle' => ['Bring buyers and sellers together and earn a fixed amount, a percentage, or both.', 'اجمع المشترين بالبائعين واربح مبلغًا ثابتًا أو نسبة أو كليهما.'],
            'benefits' => [
                ['title' => ['Flexible commission', 'عمولة مرنة'], 'desc' => ['Choose a fixed amount, a percentage, or a mix of both.', 'اختر مبلغًا ثابتًا أو نسبة أو مزيجًا منهما.']],
                ['title' => ['Wider reach', 'وصول أوسع'], 'desc' => ['Access listings and buyers across Nawy.', 'الوصول إلى الإعلانات والمشترين في ناوي.']],
                ['title' => ['Deal tracking', 'متابعة الصفقات'], 'desc' => ['Manage every connection and payout in one place.', 'أدر كل تواصل ومدفوعات في مكان واحد.']],
            ],
        ],
    ];

    $c = $copy[$marketerType] ?? $copy['affiliate'];

    $benefits = [];
    foreach ($c['benefits'] as $i => $benefit) {
        $benefits[] = [
            'icon' => $icons[$i] ?? $icons[0],
            'title' => portal_content('marketer-type', $marketerType, 'benefit_'.($i + 1).'_title', $benefit['title'][0], $benefit['title'][1]),
            'desc' => portal_content('marketer-type', $marketerType, 'benefit_'.($i + 1).'_desc', $benefit['desc'][0], $benefit['desc'][1]),
        ];
    }
@endphp

@section('title', portal_content('marketer-type', $marketerType, 'meta_title', $c['title'][0].' — Nawy', $c['title'][1].' — ناوي'))

@php($marketerNav = [
    ['key' => 'benefits', 'href' => '#benefits', 'label_en' => 'Why join', 'label_ar' => 'لماذا الانضمام'],
    ['key' => 'steps', 'href' => '#steps', 'label_en' => 'How it works', 'label_ar' => 'كيف تبدأ'],
    ['key' => 'register', 'href' => $registerUrl, 'label_en' => 'Register now', 'label_ar' => 'سجّل الآن'],
])

@section('nav')
    @include('portal.partials.section-nav', [
        'section' => 'marketer',
        'backUrl' => route('portal.marketers'),
        'backLabel' => $isAr ? 'كل طرق الربح' : 'All ways to earn',
        'links' => $marketerNav,
    ])
@endsection

@section('content')
    <x-portal.section-landing
        :hero="[
            'eyebrow' => portal_content('marketer-type', $marketerType, 'hero_eyebrow', $c['eyebrow'][0], $c['eyebrow'][1]),
            'title' => portal_content('marketer-type', $marketerType, 'hero_title', $c['title'][0], $c['title'][1]),
            'subtitle' => portal_content('marketer-type', $marketerType, 'hero_subtitle', $c['subtitle'][0], $c['subtitle'][1]),
            'image' => portal_image('marketers', 'card_'.$marketerType, 'image', asset('images/join/marketer-type.jpg'), $typeLabel, $typeLabel),
            'cta' => portal_link('marketer-type', $marketerType, 'hero_cta', 'Register now', 'سجّل الآن', $registerUrl),
        ]"
        :benefits="$benefits"
        :benefits-title="portal_content('marketer-type', $marketerType, 'benefits_title', 'Why this fits you', 'لماذا هذا مناسب لك')"
        :steps="[
            ['number' => '1', 'title' => portal_content('marketer-type', $marketerType, 'step_1_title', 'Create your account', 'أنشئ حسابك'), 'desc' => portal_content('marketer-type', $marketerType, 'step_1_text', 'Sign up with your details and confirm your type.', 'سجّل بياناتك وأكّد نوعك.')],
            ['number' => '2', 'title' => portal_content('marketer-type', $marketerType, 'step_2_title', 'Get approved', 'احصل على الموافقة'), 'desc' => portal_content('marketer-type', $marketerType, 'step_2_text', 'Our team reviews your profile and activates your account.', 'يراجع فريقنا ملفك ويُفعّل حسابك.')],
            ['number' => '3', 'title' => portal_content('marketer-type', $marketerType, 'step_3_title', 'Start earning', 'ابدأ الربح'), 'desc' => portal_content('marketer-type', $marketerType, 'step_3_text', 'Browse campaigns or share links and get paid.', 'تصفّح الحملات أو شارك الروابط واحصل على أرباحك.')],
        ]"
        :steps-title="portal_content('marketer-type', $marketerType, 'steps_title', 'Getting started is simple', 'البدء بسيط')"
        :cta="[
            'title' => portal_content('marketer-type', $marketerType, 'cta_title', 'Ready to start earning?', 'جاهز لبدء الربح؟'),
            'subtitle' => portal_content('marketer-type', $marketerType, 'cta_subtitle', 'Complete the registration in a few minutes and start earning on your terms.', 'أكمل التسجيل في بضع دقائق وابدأ الربح وفق شروطك.'),
            'button' => portal_link('marketer-type', $marketerType, 'cta_button', 'Start registration', 'ابدأ التسجيل', $registerUrl),
        ]" />

    @include('portal.partials.portal-footer')
@endsection
