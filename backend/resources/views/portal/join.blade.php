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
            'title' => portal_content('join', 'card_open_souq', 'title', 'Classifieds', 'الإعلانات المبوبة'),
            'description' => portal_content('join', 'card_open_souq', 'description', 'List in our classified sections — real estate, cars, travel & tourism and more.', 'انشر إعلاناتك في أقسام الإعلانات المبوبة — العقارات والسيارات والسفر والسياحة وغيرها.'),
            'image' => portal_image('join', 'card_open_souq', 'image', asset('images/join/open-souq.jpg'), 'Classifieds', 'الإعلانات المبوبة'),
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

    // Classifieds sub-options (active classified categories + travel agencies), revealed under its card.
    $classifiedOptions = $classifiedOptions ?? [];
    $selectedClassifiedOption = old('classified_option', $selection['classified_category_id'] ?? null);
    if (! in_array($selectedClassifiedOption, array_column($classifiedOptions, 'value'), true)) {
        $selectedClassifiedOption = null;
    }
    $hasClassifiedOptions = $classifiedOptions !== [] && array_key_exists('open_souq', $sections);
    $canContinue = $hasClassifiedOptions ? "selected && (selected !== 'open_souq' || classifiedOption)" : 'selected';
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
        :no-scroll="true"
        :alpine="['classifiedOption' => $selectedClassifiedOption]"
        :can-continue="$canContinue"
        :reveal-value="$hasClassifiedOptions ? 'open_souq' : null"
        :reveal-title="__('portal.join.classified_options_title')">
        @if ($hasClassifiedOptions)
            <input type="hidden" name="classified_option" :value="selected === 'open_souq' ? (classifiedOption || '') : ''">
            <div data-testid="classified-options" role="radiogroup" aria-label="{{ __('portal.join.classified_options_title') }}"
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($classifiedOptions as $option)
                    <button type="button" role="radio" data-classified-option="{{ $option['value'] }}"
                        @click="classifiedOption = @js($option['value'])"
                        :aria-checked="classifiedOption === @js($option['value']) ? 'true' : 'false'"
                        class="flex items-center gap-3 rounded-2xl border bg-gray-900 p-3 text-start transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#feee00] focus-visible:ring-offset-2 focus-visible:ring-offset-gray-950"
                        :class="classifiedOption === @js($option['value']) ? 'border-[#feee00]' : 'border-gray-800 hover:border-gray-600'">
                        @if ($option['image'])
                            <img src="{{ $option['image'] }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-xl object-cover">
                        @else
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-800 text-lg font-extrabold text-[#feee00]" aria-hidden="true">{{ mb_substr($option['title'], 0, 1) }}</span>
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-extrabold text-white">{{ $option['title'] }}</span>
                            <span class="mt-0.5 block text-xs leading-snug text-gray-400">{{ $option['description'] }}</span>
                        </span>
                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                            :class="classifiedOption === @js($option['value']) ? 'border-[#feee00] bg-[#feee00]' : 'border-gray-600'" aria-hidden="true">
                            <span x-show="classifiedOption === @js($option['value'])" x-cloak class="h-2 w-2 rounded-full bg-gray-950"></span>
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </x-portal.choice-screen>
@endsection
