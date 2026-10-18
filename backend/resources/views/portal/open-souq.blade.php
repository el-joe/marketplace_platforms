@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';
    // Open Souq does not have its own registration wizard yet; it temporarily
    // hands off to the product-seller /vendors/register flow.
    $registerUrl = route('portal.register');
    $registerCta = portal_link('open-souq', 'hero', 'cta_button', 'Start selling', 'ابدأ البيع', $registerUrl);

    $benefits = [
        [
            'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0V3m0 18c4.97 0 9-4.03 9-9',
            'title' => portal_content('open-souq', 'benefits_item_1', 'title', 'Reach more buyers', 'صِل إلى مشترين أكثر'),
            'desc' => portal_content('open-souq', 'benefits_item_1', 'description', 'Put your listings in front of millions of shoppers browsing Nawy every day.', 'اعرض إعلاناتك أمام ملايين المتسوقين الذين يتصفحون ناوي يومياً.'),
        ],
        [
            'icon' => 'M9 6.75V15m6-6.75V15m-9 6.75h12a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 18 4.5H6A2.25 2.25 0 0 0 3.75 6.75v10.5A2.25 2.25 0 0 0 6 19.5Zm3.75-9h4.5m-4.5 3h4.5',
            'title' => portal_content('open-souq', 'benefits_item_2', 'title', 'List in minutes', 'أدرج إعلانك في دقائق'),
            'desc' => portal_content('open-souq', 'benefits_item_2', 'description', 'Create your account, add your listing details and go live from one dashboard.', 'أنشئ حسابك، وأضف تفاصيل إعلانك، وانشره من لوحة تحكم واحدة.'),
        ],
        [
            'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
            'title' => portal_content('open-souq', 'benefits_item_3', 'title', 'Grow with campaigns', 'انمُ عبر الحملات'),
            'desc' => portal_content('open-souq', 'benefits_item_3', 'description', 'Boost your listings through Nawy marketers and reach the right audience faster.', 'عزّز إعلاناتك عبر مسوّقي ناوي وصِل إلى الجمهور المناسب بشكل أسرع.'),
        ],
    ];
@endphp

@section('title', portal_content('open-souq', 'meta', 'title', 'Sell on Open Souq — Nawy', 'بِع على السوق المفتوح — ناوي'))
@section('meta_description', portal_content('open-souq', 'meta', 'description', 'List across our classified categories — real estate, cars, chalets and more — and reach buyers across the region.', 'أدرج إعلاناتك في فئات السوق المفتوح — العقارات والسيارات والشاليهات وغيرها — وصِل إلى المشترين في جميع أنحاء المنطقة.'))

@php($souqNav = [
    ['key' => 'benefits', 'href' => '#benefits', 'label_en' => 'Why sell on Open Souq', 'label_ar' => 'لماذا تبيع على السوق المفتوح'],
    ['key' => 'publish', 'href' => '#publish', 'label_en' => 'What you can list', 'label_ar' => 'ما يمكنك إدراجه'],
    ['key' => 'steps', 'href' => '#steps', 'label_en' => 'How it works', 'label_ar' => 'كيف تبدأ'],
    ['key' => 'register', 'href' => $registerUrl, 'label_en' => 'Register now', 'label_ar' => 'سجّل الآن'],
])

@section('nav')
    @include('portal.partials.section-nav', [
        'section' => 'open_souq',
        'backUrl' => route('portal.companies'),
        'links' => $souqNav,
    ])
@endsection

@section('content')
    <x-portal.section-landing
        :hero="[
            'eyebrow' => portal_content('open-souq', 'hero', 'eyebrow', 'Open Souq', 'السوق المفتوح'),
            'title' => portal_content('open-souq', 'hero', 'title', 'List anything, reach everyone', 'أدرج أي شيء، وصِل إلى الجميع'),
            'subtitle' => portal_content('open-souq', 'hero', 'subtitle', 'Open Souq is where individuals and businesses list across our classified categories — real estate, cars, chalets and more.', 'السوق المفتوح هو المكان الذي يدرج فيه الأفراد والشركات إعلاناتهم في فئاتنا المبوبة — العقارات والسيارات والشاليهات وغيرها.'),
            'image' => portal_image('open-souq', 'hero', 'photo', asset('images/join/open-souq.jpg'), 'A seller listing items on Open Souq', 'بائع يدرج إعلاناته على السوق المفتوح'),
            'cta' => $registerCta,
        ]"
        :benefits="$benefits"
        :benefits-title="portal_content('open-souq', 'benefits', 'title', 'Why sell on Open Souq?', 'لماذا تبيع على السوق المفتوح؟')"
        :steps="[
            ['number' => '1', 'title' => portal_content('open-souq', 'steps', 'step_1_title', 'Create your account', 'أنشئ حسابك'), 'desc' => portal_content('open-souq', 'steps', 'step_1_text', 'Sign up with your business details and pick what you want to list.', 'سجّل بيانات نشاطك واختر ما تريد إدراجه.')],
            ['number' => '2', 'title' => portal_content('open-souq', 'steps', 'step_2_title', 'Get approved', 'احصل على الموافقة'), 'desc' => portal_content('open-souq', 'steps', 'step_2_text', 'Our team reviews your details and activates your account.', 'يراجع فريقنا بياناتك ويُفعّل حسابك.')],
            ['number' => '3', 'title' => portal_content('open-souq', 'steps', 'step_3_title', 'Start listing', 'ابدأ الإدراج'), 'desc' => portal_content('open-souq', 'steps', 'step_3_text', 'Publish your listings, manage inquiries and start selling.', 'انشر إعلاناتك، وأدر الاستفسارات، وابدأ البيع.')],
        ]"
        :steps-title="portal_content('open-souq', 'steps', 'title', 'Getting started is simple', 'البدء بسيط')"
        :cta="[
            'title' => portal_content('open-souq', 'cta', 'title', 'Ready to start listing?', 'جاهز لبدء الإدراج؟'),
            'subtitle' => portal_content('open-souq', 'cta', 'subtitle', 'Create your account in a few minutes and put your first listing live.', 'أنشئ حسابك في بضع دقائق وانشر إعلانك الأول.'),
            'button' => portal_link('open-souq', 'cta', 'button', 'Start registration', 'ابدأ التسجيل', $registerUrl),
        ]">
        {{-- Categories you can list --}}
        @if ($categories->isNotEmpty())
            <section id="publish" class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 scroll-mt-[72px]">
                <h2 class="text-[#0F807E] font-bold text-[28px] lg:text-[36px] mb-6 lg:mb-8">
                    {{ portal_content('open-souq', 'categories', 'title', 'What you can list', 'ما يمكنك إدراجه') }}
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
