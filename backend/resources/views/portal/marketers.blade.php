@extends('layouts.portal')

@php
    $selectedType = old('marketer_type', $selection['marketer_type'] ?? null);
    if ($selectedType !== null && ! in_array($selectedType, array_column($cards, 'value'), true)) {
        $selectedType = null;
    }
@endphp

@section('title', portal_content('marketers', 'meta', 'title', 'Influencers, Creators & Affiliates', 'المشاهير والمؤثرون والمسوقون بالعمولة'))
@section('hide_nav', true)

@section('content')
    <x-portal.choice-screen
        :hero-title="portal_content('marketers', 'hero', 'title', 'How would you like to join?', 'كيف تريد الانضمام؟')"
        :hero-subtitle="portal_content('marketers', 'hero', 'subtitle', 'Turn your reach into income. Choose the type that matches how you work and complete your registration.', 'حوّل جمهورك إلى دخل. اختر النوع الذي يناسب طريقة عملك وأكمل تسجيلك.')"
        :cards="$cards"
        field-name="marketer_type"
        :selected="$selectedType"
        :action="route('portal.marketers.continue')"
        :submit="portal_content('marketers', 'actions', 'continue', 'Continue', 'متابعة')"
        :select-hint="portal_content('marketers', 'actions', 'select_hint', 'Select an option to continue', 'اختر أحد الخيارات للمتابعة')"
        :logo-aria="portal_content('nav', 'logo', 'aria_label', 'Home', 'الصفحة الرئيسية')"
        :countries="$countries"
        :current-country="$currentCountry"
        :back-url="route('portal.join')"
        :back-label="portal_content('marketers', 'hero', 'back', 'Back to join options', 'الرجوع لاختيار نوع الانضمام')" />
@endsection
