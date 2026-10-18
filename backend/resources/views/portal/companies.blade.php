@extends('layouts.portal')

@php
    $selectedChoice = old('choice', $selection['choice'] ?? null);
    if ($selectedChoice !== null && ! in_array($selectedChoice, array_column($cards, 'value'), true)) {
        $selectedChoice = null;
    }
    $selectedCategory = old('classified_category_id', $selection['classified_category_id'] ?? null);
@endphp

@section('title', portal_content('companies', 'meta', 'title', 'Specialized Companies & Brokers', 'الشركات التخصصية والوسطاء'))
@section('hide_nav', true)

@section('content')
    <x-portal.choice-screen
        :hero-title="portal_content('companies', 'hero', 'title', 'How would you like to join?', 'كيف تريد الانضمام؟')"
        :hero-subtitle="portal_content('companies', 'hero', 'subtitle', 'Choose the option that fits your business, then continue.', 'اختر الخيار المناسب لنشاطك، ثم تابع.')"
        :cards="$cards"
        field-name="choice"
        :selected="$selectedChoice"
        :action="route('portal.companies.continue')"
        :submit="portal_content('companies', 'actions', 'continue', 'Continue', 'متابعة')"
        :select-hint="portal_content('companies', 'actions', 'select_hint', 'Select an option to continue', 'اختر أحد الخيارات للمتابعة')"
        :countries="$countries"
        :current-country="$currentCountry"
        :country-id="$currentCountry?->id"
        :back-url="route('portal.join')"
        :back-label="portal_content('companies', 'hero', 'back', 'Back to join options', 'الرجوع لاختيار نوع الانضمام')"
        reveal-value="open_souq"
        :reveal-title="portal_content('companies', 'form', 'category_label', 'Your specialization', 'التخصص')"
        can-continue="selected && (selected !== 'open_souq' || category)"
        :alpine="['category' => $selectedCategory]">
        @if ($categories->isEmpty())
            <p class="text-sm text-gray-400">{{ portal_content('companies', 'form', 'category_empty', 'Specializations are being updated, please check back soon.', 'يتم تحديث التخصصات حالياً، يرجى المحاولة قريباً.') }}</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $category)
                    <label for="classified-category-{{ $category->id }}"
                        class="cursor-pointer rounded-xl border bg-gray-900 px-4 py-3.5 transition-all duration-200"
                        :class="category === '{{ $category->id }}' ? 'border-[#feee00] ring-1 ring-[#feee00]' : 'border-gray-700 hover:border-gray-500'">
                        <input type="radio" id="classified-category-{{ $category->id }}" name="classified_category_id"
                            value="{{ $category->id }}" x-model="category" class="sr-only">
                        <span class="text-sm font-bold text-white">{{ $category->name }}</span>
                    </label>
                @endforeach
            </div>
        @endif
    </x-portal.choice-screen>
@endsection
