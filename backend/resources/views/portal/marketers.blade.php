@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';

    $typeMeta = [
        'influencer' => [
            'accent' => '#7C5CFF',
            'desc' => portal_content('marketers', 'type_influencer', 'description', 'Promote products and listings to your audience with a fixed amount per campaign.', 'روّج للمنتجات والإعلانات لجمهورك مقابل مبلغ ثابت لكل حملة.'),
        ],
        'affiliate' => [
            'accent' => '#0F807E',
            'desc' => portal_content('marketers', 'type_affiliate', 'description', 'Earn a percentage of every sale you drive through your affiliate links.', 'اربح نسبة من كل عملية بيع تحققها عبر روابطك التسويقية.'),
        ],
        'broker' => [
            'accent' => '#feee00',
            'desc' => portal_content('marketers', 'type_broker', 'description', 'Connect buyers and sellers and earn a fixed amount, a percentage, or both.', 'اربط المشترين بالبائعين واربح مبلغاً ثابتاً أو نسبة أو كليهما.'),
        ],
    ];
@endphp

@section('title', portal_content('marketers', 'meta', 'title', 'Influencers, Creators & Affiliates', 'المشاهير والمؤثرون والمسوقون بالعمولة'))

@section('nav')
    @include('portal.partials.section-nav', [
        'section' => 'marketer',
        'links' => [
            ['key' => 'types', 'href' => route('portal.marketers') . '#types', 'label_en' => 'Types', 'label_ar' => 'الأنواع'],
            ['key' => 'register', 'href' => route('portal.marketers') . '#register', 'label_en' => 'Join now', 'label_ar' => 'انضم الآن'],
        ],
    ])
@endsection

@section('content')
    <div class="bg-gray-950 pt-[104px] pb-16 lg:pb-24 min-h-screen" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
        <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('portal.join') }}" class="inline-flex items-center gap-2 text-sm font-bold text-gray-400 hover:text-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18" class="{{ $isAr ? '-scale-x-100' : '' }}">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ portal_content('marketers', 'hero', 'back', 'Back to join options', 'الرجوع لاختيار نوع الانضمام') }}
            </a>

            <div id="types" class="mt-8 max-w-3xl">
                <h1 class="text-white font-extrabold text-3xl sm:text-4xl lg:text-[42px] leading-tight">
                    {{ portal_content('marketers', 'hero', 'title', 'Join as an influencer, creator or affiliate', 'انضم كمشهور أو مؤثر أو مسوق بالعمولة') }}
                </h1>
                <p class="mt-4 text-gray-400 text-base sm:text-lg">
                    {{ portal_content('marketers', 'hero', 'subtitle', 'Turn your reach into income. Choose the type that matches how you work and complete your registration.', 'حوّل جمهورك إلى دخل. اختر النوع الذي يناسب طريقة عملك وأكمل تسجيلك.') }}
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-8 max-w-2xl rounded-xl border border-red-500/40 bg-red-500/10 px-5 py-4 text-sm text-red-300">
                    <ul class="space-y-1 {{ $isAr ? 'list-disc pr-5' : 'list-disc pl-5' }}">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div id="register" class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($marketerTypes as $key => $label)
                    @php($meta = $typeMeta[$key] ?? ['accent' => '#0F807E', 'desc' => ''])
                    <div class="flex h-full flex-col rounded-3xl border border-gray-800 bg-gray-900 p-7">
                        <h2 class="text-white font-extrabold text-xl leading-snug">{{ $label }}</h2>
                        <p class="mt-3 flex-1 text-sm text-gray-400 leading-relaxed">{{ $meta['desc'] }}</p>
                        <a href="{{ route('marketer.register', ['type' => $key]) }}"
                            class="mt-6 inline-flex items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-bold text-gray-950 transition-opacity hover:opacity-90"
                            style="background-color: {{ $meta['accent'] }}">
                            {{ portal_content('marketers', 'cta', 'register', 'Register now', 'سجّل الآن') }}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18" class="{{ $isAr ? '-scale-x-100' : '' }}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                            </svg>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
