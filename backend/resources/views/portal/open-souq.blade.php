@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';
    // Classifieds (formerly Open Souq) reuses the /vendors/register wizard; ?account=classified makes it
    // create a classified-ads vendor account instead of a product seller. A section landing also passes
    // its category, so the wizard shows and collects that section's contract.
    $selectedCategory = $selectedCategory ?? null;
    $categoryName = fn ($category) => $isAr ? $category->name_ar : ($category->name_en ?: $category->name_ar);
    $registerUrl = route('portal.register', array_filter(['account' => 'classified', 'category' => $selectedCategory?->slug]));
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

@section('title', portal_content('open-souq', 'meta', 'title', 'List on Classifieds — Nawy', 'انشر في الإعلانات المبوبة — ناوي'))
@section('meta_description', portal_content('open-souq', 'meta', 'description', 'List in our classified sections — real estate, cars, travel & tourism and more — and reach buyers across the region.', 'انشر إعلاناتك في أقسام الإعلانات المبوبة — العقارات والسيارات والسفر والسياحة وغيرها — وصِل إلى المشترين في جميع أنحاء المنطقة.'))

@php($souqNav = [
    ['key' => 'benefits', 'href' => '#benefits', 'label_en' => 'Why list on Classifieds', 'label_ar' => 'لماذا تنشر في الإعلانات المبوبة'],
    ['key' => 'publish', 'href' => '#publish', 'label_en' => 'What you can list', 'label_ar' => 'ما يمكنك إدراجه'],
    ['key' => 'steps', 'href' => '#steps', 'label_en' => 'How it works', 'label_ar' => 'كيف تبدأ'],
    ['key' => 'register', 'href' => $registerUrl, 'label_en' => 'Register now', 'label_ar' => 'سجّل الآن'],
])

@section('nav')
    @include('portal.partials.section-nav', [
        'section' => 'open_souq',
        'backUrl' => route('portal.join'),
        'links' => $souqNav,
    ])
@endsection

@section('content')
    <x-portal.section-landing
        :hero="[
            'eyebrow' => portal_content('open-souq', 'hero', 'eyebrow', 'Classifieds', 'الإعلانات المبوبة').($selectedCategory ? ' · '.$categoryName($selectedCategory) : ''),
            'title' => portal_content('open-souq', 'hero', 'title', 'List anything, reach everyone', 'أدرج أي شيء، وصِل إلى الجميع'),
            'subtitle' => portal_content('open-souq', 'hero', 'subtitle', 'Classifieds is where individuals and businesses list across our sections — real estate, cars, travel & tourism and more.', 'الإعلانات المبوبة هي المكان الذي ينشر فيه الأفراد والشركات إعلاناتهم في أقسامنا — العقارات والسيارات والسفر والسياحة وغيرها.'),
            'image' => portal_image('open-souq', 'hero', 'photo', asset('images/join/open-souq.jpg'), 'A seller publishing classified ads', 'بائع ينشر إعلاناته المبوبة'),
            'cta' => $registerCta,
        ]"
        :benefits="$benefits"
        :benefits-title="portal_content('open-souq', 'benefits', 'title', 'Why list on Classifieds?', 'لماذا تنشر في الإعلانات المبوبة؟')"
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
            <section data-portal-section="categories" id="publish" class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 scroll-mt-[72px]">
                <h2 class="text-[#0F807E] font-bold text-[28px] lg:text-[36px] mb-6 lg:mb-8">
                    {{ portal_content('open-souq', 'categories', 'title', 'What you can list', 'ما يمكنك إدراجه') }}
                </h2>
                <div class="flex flex-wrap gap-3">
                    @foreach ($categories as $category)
                        @php($isCurrent = $selectedCategory?->is($category))
                        <a href="{{ route('portal.companies.open-souq.category', $category->slug) }}"
                            @if ($isCurrent) aria-current="page" @endif
                            class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors {{ $isCurrent ? 'border-[#feee00] bg-[#feee00]/10 text-[#feee00]' : 'border-white/10 bg-[#151515] text-white hover:border-white/30' }}">
                            <span class="h-2 w-2 rounded-full bg-[#feee00]"></span>
                            {{ $categoryName($category) }}
                        </a>
                    @endforeach
                    @if ($travelFolded ?? false)
                        <a href="{{ route('portal.companies.travel-agency') }}"
                            class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-[#151515] px-4 py-2 text-sm font-semibold text-white transition-colors hover:border-white/30">
                            <span class="h-2 w-2 rounded-full bg-[#feee00]"></span>
                            {{ __('portal.join.travel_option_title') }}
                        </a>
                    @endif
                </div>
            </section>
        @endif
    </x-portal.section-landing>

    @include('portal.partials.portal-footer')
@endsection
