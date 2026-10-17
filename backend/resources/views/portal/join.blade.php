@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';

    $cards = [
        'product_vendor' => [
            'accent' => '#0F807E',
            'icon' => 'sellers',
            'title' => portal_content('join', 'card_product_vendor', 'title', 'Product Seller', 'بائع منتجات'),
            'desc' => portal_content('join', 'card_product_vendor', 'description', 'Sell physical or digital products and manage your own store on Nawy.', 'بِع منتجاتك المادية أو الرقمية وأدر متجرك الخاص على ناوي.'),
            'cta' => portal_content('join', 'card_product_vendor', 'cta', 'Start selling', 'ابدأ البيع'),
        ],
        'classified_vendor' => [
            'accent' => '#feee00',
            'icon' => 'companies',
            'title' => portal_content('join', 'card_classified_vendor', 'title', 'Specialized Companies & Brokers', 'شركات تخصصية ووسطاء'),
            'desc' => portal_content('join', 'card_classified_vendor', 'description', 'Join as a specialized company or an intermediary across real estate, cars, travel, chalets and more.', 'انضم كشركة تخصصية أو وسيط في العقارات والسيارات والسفر والشاليهات وغيرها.'),
            'cta' => portal_content('join', 'card_classified_vendor', 'cta', 'Explore the section', 'استكشف القسم'),
        ],
        'marketer' => [
            'accent' => '#7C5CFF',
            'icon' => 'marketers',
            'title' => portal_content('join', 'card_marketer', 'title', 'Influencers, Creators & Affiliates', 'مشاهير ومؤثرون ومسوقون بالعمولة'),
            'desc' => portal_content('join', 'card_marketer', 'description', 'Promote products and listings as an influencer, creator or commission-based marketer.', 'روّج للمنتجات والإعلانات كمشهور أو مؤثر أو مسوق بالعمولة.'),
            'cta' => portal_content('join', 'card_marketer', 'cta', 'Join as a marketer', 'انضم كمسوق'),
        ],
    ];
@endphp

@section('title', portal_content('join', 'meta', 'title', 'Join Nawy', 'انضم إلى ناوي'))

@section('content')
    <div class="bg-gray-950 pt-[104px] pb-16 lg:pb-24 min-h-screen" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
        <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-white font-extrabold text-3xl sm:text-4xl lg:text-[44px] leading-tight">
                    {{ portal_content('join', 'hero', 'title', 'Join Nawy', 'انضم إلى ناوي') }}
                </h1>
                <p class="mt-4 text-gray-400 text-base sm:text-lg">
                    {{ portal_content('join', 'hero', 'subtitle', 'Choose how you would like to join and we will take you to the experience made for you.', 'اختر نوع انضمامك أولاً، وسنوجهك إلى التجربة المخصصة لك.') }}
                </p>
            </div>

            @if (session('join_status'))
                <div class="mt-8 max-w-2xl mx-auto rounded-xl border border-[#feee00]/40 bg-[#feee00]/10 px-5 py-4 text-sm font-medium text-[#feee00]">
                    {{ session('join_status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-8 max-w-2xl mx-auto rounded-xl border border-red-500/40 bg-red-500/10 px-5 py-4 text-sm text-red-300">
                    <ul class="space-y-1 {{ $isAr ? 'list-disc pr-5' : 'list-disc pl-5' }}">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sections as $value => $label)
                    @php($card = $cards[$value] ?? null)
                    <form method="POST" action="{{ route('portal.join.select') }}" class="h-full">
                        @csrf
                        <input type="hidden" name="section" value="{{ $value }}">
                        <button type="submit"
                            class="group relative flex h-full w-full flex-col items-start gap-5 rounded-3xl border border-gray-800 bg-gray-900 p-7 text-start transition-all duration-300 hover:-translate-y-1 hover:border-gray-600 hover:shadow-2xl">
                            <span class="inline-flex items-center justify-center w-14 h-14 rounded-2xl text-gray-950"
                                style="background-color: {{ $card['accent'] ?? '#0F807E' }}">
                                @if (($card['icon'] ?? '') === 'sellers')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="26" height="26">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
                                    </svg>
                                @elseif (($card['icon'] ?? '') === 'companies')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="26" height="26">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="26" height="26">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                                    </svg>
                                @endif
                            </span>

                            <span class="flex-1">
                                <span class="block text-white font-extrabold text-xl leading-snug">{{ $card['title'] ?? $label }}</span>
                                <span class="mt-3 block text-sm text-gray-400 leading-relaxed">{{ $card['desc'] ?? '' }}</span>
                            </span>

                            <span class="inline-flex items-center gap-2 text-sm font-bold" style="color: {{ $card['accent'] ?? '#0F807E' }}">
                                {{ $card['cta'] ?? '' }}
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18"
                                    class="transition-transform duration-300 group-hover:{{ $isAr ? '-translate-x-1' : 'translate-x-1' }} {{ $isAr ? '-scale-x-100' : '' }}">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                                </svg>
                            </span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
@endsection
