@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';

    $benefits = [
        [
            'icon' => 'M12 2.25c-3.728 0-6.75 3.022-6.75 6.75 0 4.5 6.75 12 6.75 12s6.75-7.5 6.75-12c0-3.728-3.022-6.75-6.75-6.75Zm0 9a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z',
            'title' => portal_content('marketers', 'benefits_item_1', 'title', 'Earn your way', 'اربح بطريقتك'),
            'desc' => portal_content('marketers', 'benefits_item_1', 'description', 'Fixed amounts, percentages or both — choose what fits how you work.', 'مبالغ ثابتة أو نسب أو كليهما — اختر ما يناسب طريقة عملك.'),
        ],
        [
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'title' => portal_content('marketers', 'benefits_item_2', 'title', 'Promote what you love', 'روّج لما تحبه'),
            'desc' => portal_content('marketers', 'benefits_item_2', 'description', 'Pick products, listings and campaigns that match your audience.', 'اختر المنتجات والإعلانات والحملات التي تناسب جمهورك.'),
        ],
        [
            'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
            'title' => portal_content('marketers', 'benefits_item_3', 'title', 'Track every riyal', 'تابع كل ريال'),
            'desc' => portal_content('marketers', 'benefits_item_3', 'description', 'See your clicks, conversions and payouts from one dashboard.', 'شاهد نقراتك وتحويلاتك ومدفوعاتك من لوحة واحدة.'),
        ],
    ];
@endphp

@section('title', portal_content('marketers', 'meta', 'title', 'Influencers, Creators & Affiliates', 'المشاهير والمؤثرون والمسوقون بالعمولة'))

@php($marketerNav = [
    ['key' => 'benefits', 'href' => '#benefits', 'label_en' => 'Why join', 'label_ar' => 'لماذا الانضمام'],
    ['key' => 'types', 'href' => '#types', 'label_en' => 'Ways to earn', 'label_ar' => 'طرق الربح'],
    ['key' => 'steps', 'href' => '#steps', 'label_en' => 'How it works', 'label_ar' => 'كيف تبدأ'],
])

@section('nav')
    @include('portal.partials.section-nav', [
        'section' => 'marketer',
        'backUrl' => route('portal.join'),
        'links' => $marketerNav,
    ])
@endsection

@section('content')
    <x-portal.section-landing
        :hero="[
            'eyebrow' => portal_content('marketers', 'hero', 'eyebrow', 'Marketers', 'المسوّقون'),
            'title' => portal_content('marketers', 'hero', 'title', 'Turn your reach into income', 'حوّل جمهورك إلى دخل'),
            'subtitle' => portal_content('marketers', 'hero', 'subtitle', 'Promote products and listings your way — as an influencer, an affiliate or a broker — and earn on your terms.', 'روّج للمنتجات والإعلانات بطريقتك — كمؤثر أو مسوق بالعمولة أو وسيط — واربح وفق شروطك.'),
            'image' => portal_image('marketers', 'hero', 'photo', asset('images/join/marketer-type.jpg'), 'A marketer creating content', 'مسوّق ينشئ محتوى'),
            'cta' => portal_link('marketers', 'hero', 'cta_button', 'Choose your path', 'اختر مسارك', '#types'),
        ]"
        :benefits="$benefits"
        :benefits-title="portal_content('marketers', 'benefits', 'title', 'Why earn with Nawy?', 'لماذا تربح مع ناوي؟')"
        :steps="[
            ['number' => '1', 'title' => portal_content('marketers', 'steps', 'step_1_title', 'Create your account', 'أنشئ حسابك'), 'desc' => portal_content('marketers', 'steps', 'step_1_text', 'Sign up with your details and pick your marketer type.', 'سجّل بياناتك واختر نوع التسويق.')],
            ['number' => '2', 'title' => portal_content('marketers', 'steps', 'step_2_title', 'Get approved', 'احصل على الموافقة'), 'desc' => portal_content('marketers', 'steps', 'step_2_text', 'Our team reviews your profile and activates your account.', 'يراجع فريقنا ملفك ويُفعّل حسابك.')],
            ['number' => '3', 'title' => portal_content('marketers', 'steps', 'step_3_title', 'Start earning', 'ابدأ الربح'), 'desc' => portal_content('marketers', 'steps', 'step_3_text', 'Browse campaigns or share links and get paid.', 'تصفّح الحملات أو شارك الروابط واحصل على أرباحك.')],
        ]"
        :steps-title="portal_content('marketers', 'steps', 'title', 'Getting started is simple', 'البدء بسيط')"
        :cta="[
            'title' => portal_content('marketers', 'cta', 'title', 'Pick how you want to earn', 'اختر كيف تريد أن تربح'),
            'subtitle' => portal_content('marketers', 'cta', 'subtitle', 'Choose the marketer type that fits you and register in a few minutes.', 'اختر نوع التسويق المناسب لك وسجّل في بضع دقائق.'),
            'button' => portal_link('marketers', 'cta', 'button', 'See ways to earn', 'اطّلع على طرق الربح', '#types'),
        ]">
        {{-- Ways to earn --}}
        <section data-portal-section="types" id="types" class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 scroll-mt-[72px]">
            <h2 class="text-[#0F807E] font-bold text-[28px] lg:text-[36px] mb-6 lg:mb-8">
                {{ portal_content('marketers', 'types', 'title', 'Ways to earn', 'طرق الربح') }}
            </h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($types as $type)
                    <a href="{{ route('portal.marketers.type', $type['key']) }}"
                        class="group flex h-full flex-col overflow-hidden rounded-3xl border border-white/10 bg-[#151515] transition-all duration-300 hover:-translate-y-1 hover:border-[#0F807E]">
                        <div class="relative h-40 w-full overflow-hidden bg-gray-800">
                            <img src="{{ $type['image']['src'] }}" alt="{{ $type['image']['alt'] }}" loading="lazy"
                                class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105">
                        </div>
                        <div class="flex flex-1 flex-col p-6">
                            <h3 class="text-white font-black text-lg">{{ $type['label'] }}</h3>
                            <p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ $type['description'] }}</p>
                            <span class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-[#0F807E]">
                                {{ portal_content('marketers', 'types', 'card_link', 'Learn more', 'اعرف المزيد') }}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" width="16" height="16" class="{{ $isAr ? '-scale-x-100' : '' }}">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                                </svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </x-portal.section-landing>

    @include('portal.partials.portal-footer')
@endsection
