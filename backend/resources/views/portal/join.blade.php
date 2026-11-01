@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';

    $cards = [
        [
            'value' => 'product_vendor',
            'title' => portal_content('join', 'card_product_vendor', 'title', 'Product Seller', 'بائع منتجات'),
            'description' => portal_content('join', 'card_product_vendor', 'description', 'Sell physical or digital products and manage your own store on Nawy.', 'بِع منتجاتك المادية أو الرقمية وأدر متجرك الخاص على ناوي.'),
            'image' => portal_image('join', 'card_product_vendor', 'image', asset('images/join/seller.svg'), 'Product seller', 'بائع منتجات'),
        ],
        [
            'value' => 'open_souq',
            'title' => portal_content('join', 'card_open_souq', 'title', 'Open Souq', 'السوق المفتوح'),
            'description' => portal_content('join', 'card_open_souq', 'description', 'List across our classified categories — real estate, cars, chalets and more.', 'أدرج إعلاناتك في فئات السوق المفتوح — العقارات والسيارات والشاليهات وغيرها.'),
            'image' => portal_image('join', 'card_open_souq', 'image', asset('images/join/open-souq.jpg'), 'Open Souq', 'السوق المفتوح'),
        ],
        [
            'value' => 'travel_agency',
            'title' => portal_content('join', 'card_travel_agency', 'title', 'Travel Agency', 'وكالة سفر'),
            'description' => portal_content('join', 'card_travel_agency', 'description', 'Register your travel agency to publish trips, packages and bookings on Nawy.', 'سجّل وكالة السفر الخاصة بك لنشر الرحلات والباقات والحجوزات على ناوي.'),
            'image' => portal_image('join', 'card_travel_agency', 'image', asset('images/join/travel-agency.jpg'), 'Travel agency', 'وكالة سفر'),
        ],
        [
            'value' => 'marketer',
            'title' => portal_content('join', 'card_marketer', 'title', 'Influencers, Creators & Affiliates', 'مشاهير ومؤثرون ومسوقون بالعمولة'),
            'description' => portal_content('join', 'card_marketer', 'description', 'Promote products and listings as an influencer, creator or commission-based marketer.', 'روّج للمنتجات والإعلانات كمشهور أو مؤثر أو مسوق بالعمولة.'),
            'image' => portal_image('join', 'card_marketer', 'image', asset('images/join/marketer.svg'), 'Influencers, creators and affiliates', 'مشاهير ومؤثرون ومسوقون بالعمولة'),
        ],
    ];

    // Admin-managed visibility (Settings → General): hide the cards switched off.
    $cards = array_values(array_filter($cards, fn ($card) => array_key_exists($card['value'], $sections)));

    // Re-select the section on validation round-trips, ignoring stale/invalid values.
    $selectedSection = old('section', $selection['section'] ?? null);
    if ($selectedSection !== null && ! in_array($selectedSection, array_column($cards, 'value'), true)) {
        $selectedSection = null;
    }
@endphp

@section('title', portal_content('join', 'meta', 'title', 'Join Nawy', 'انضم إلى ناوي'))
@section('hide_nav', true)

@section('content')
    <x-portal.choice-screen
        :hero-title="portal_content('join', 'hero', 'title', 'Join Nawy', 'انضم إلى ناوي')"
        :hero-subtitle="portal_content('join', 'hero', 'subtitle', 'Choose the account type that fits you, then continue to the registration experience made for it.', 'اختر نوع الحساب المناسب لك، ثم تابع إلى تجربة التسجيل المخصصة له.')"
        :cards="$cards"
        field-name="section"
        :selected="$selectedSection"
        :action="route('portal.join.select')"
        :submit="portal_content('join', 'actions', 'continue', 'Continue', 'متابعة')"
        :select-hint="portal_content('join', 'actions', 'select_hint', 'Select an option to continue', 'اختر أحد الخيارات للمتابعة')"
        :logo="portal_image('join', 'logo', 'image', asset('images/nawy_logo_transparent.png'), 'Nawy', 'ناوي')"
        :logo-aria="portal_content('join', 'logo', 'aria_label', 'Nawy', 'ناوي')"
        :countries="$countries"
        :current-country="$currentCountry"
        :no-scroll="true" />
@endsection
