@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';
    $sectionValues = [
        'product_vendor' => 'bg-[#0F807E]',
        'classified_vendor' => 'bg-[#feee00]',
        'marketer' => 'bg-[#7C5CFF]',
    ];
@endphp

@section('title', portal_content('join', 'meta', 'title', 'Join Nawy', 'انضم إلى ناوي'))

@section('content')
    <div class="bg-gray-950 pt-[104px] pb-16 lg:pb-24 min-h-screen" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
        <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Heading --}}
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-white font-extrabold text-3xl sm:text-4xl lg:text-[44px] leading-tight">
                    {{ portal_content('join', 'hero', 'title', 'Join Nawy', 'انضم إلى ناوي') }}
                </h1>
                <p class="mt-4 text-gray-400 text-base sm:text-lg">
                    {{ portal_content('join', 'hero', 'subtitle', 'Choose your country and the account type that fits you, and we will take you to the right registration flow.', 'اختر دولتك ونوع الحساب المناسب لك، وسنوجهك إلى مسار التسجيل الصحيح.') }}
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

            <form method="POST" action="{{ route('portal.join.continue') }}" class="mt-10"
                x-data="{
                    countryId: @js(old('country_id', $selection['country_id'] ?? '')),
                    section: @js(old('section', $selection['section'] ?? '')),
                    categoryId: @js(old('classified_category_id', $selection['classified_category_id'] ?? '')),
                    marketerType: @js(old('marketer_type', $selection['marketer_type'] ?? '')),
                    marketerTypes: @js(collect($marketerTypes)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()),
                    loading: false,
                    async loadOptions() {
                        if (! this.countryId) { return; }
                        this.loading = true;
                        try {
                            const url = @js(route('portal.join.options')) + '?country_id=' + encodeURIComponent(this.countryId);
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                            if (! res.ok) { this.countryId = ''; return; }
                            const data = await res.json();
                            this.marketerTypes = Object.entries(data.marketer_types || {}).map(([key, label]) => ({ key, label }));
                        } finally {
                            this.loading = false;
                        }
                    }
                }"
                x-init="if (countryId) { loadOptions(); }">
                @csrf

                {{-- Country --}}
                <div class="max-w-2xl mx-auto">
                    <label for="join-country"
                        class="block text-sm font-bold text-white mb-2">{{ portal_content('join', 'form', 'country_label', 'Country', 'الدولة') }}</label>
                    <select id="join-country" name="country_id" x-model="countryId" @change="loadOptions()" required
                        class="w-full bg-gray-900 border border-gray-700 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#0F807E]">
                        <option value="">{{ portal_content('join', 'form', 'country_placeholder', '— Select Country —', '— اختر الدولة —') }}</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}">
                                {{ $country->flag_emoji }} {{ $isAr ? $country->name_ar : ($country->name_en ?? $country->name_ar) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sections --}}
                <fieldset class="mt-10">
                    <legend class="block text-sm font-bold text-white mb-4">
                        {{ portal_content('join', 'form', 'section_label', 'How would you like to join?', 'كيف تريد الانضمام؟') }}
                    </legend>

                    <div class="grid gap-4 sm:grid-cols-3">
                        @foreach ($sections as $value => $label)
                            <label
                                class="relative flex flex-col gap-3 rounded-2xl border p-5 cursor-pointer transition-all duration-200 bg-gray-900
                                       border-gray-700 hover:border-gray-500"
                                :class="section === @js($value) ? 'border-[#0F807E] ring-1 ring-[#0F807E]' : ''">
                                <input type="radio" name="section" value="{{ $value }}" x-model="section" class="sr-only" required>

                                <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl text-gray-950 {{ $sectionValues[$value] ?? 'bg-[#0F807E]' }}">
                                    @if ($value === 'product_vendor')
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="22" height="22">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />
                                        </svg>
                                    @elseif ($value === 'classified_vendor')
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="22" height="22">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="22" height="22">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                                        </svg>
                                    @endif
                                </span>

                                <span class="font-bold text-white text-[15px] leading-snug">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Classified specialization --}}
                <div x-show="section === 'classified_vendor'" x-cloak x-transition class="mt-8 max-w-2xl mx-auto">
                    <label for="join-category"
                        class="block text-sm font-bold text-white mb-2">{{ portal_content('join', 'form', 'specialization_label', 'Specialization', 'التخصص') }}</label>
                    <select id="join-category" name="classified_category_id" x-model="categoryId"
                        :required="section === 'classified_vendor'"
                        class="w-full bg-gray-900 border border-gray-700 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#0F807E]">
                        <option value="">{{ portal_content('join', 'form', 'specialization_placeholder', '— Select Specialization —', '— اختر التخصص —') }}</option>
                        @foreach ($classifiedCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Marketer type --}}
                <div x-show="section === 'marketer'" x-cloak x-transition class="mt-8">
                    <p class="max-w-2xl mx-auto text-sm font-bold text-white mb-3">
                        {{ portal_content('join', 'form', 'marketer_type_label', 'Registration type', 'نوع التسجيل') }}
                    </p>
                    <div class="max-w-2xl mx-auto grid gap-3 sm:grid-cols-3">
                        <template x-for="item in marketerTypes" :key="item.key">
                            <label class="relative flex items-center gap-3 rounded-xl border border-gray-700 bg-gray-900 p-4 cursor-pointer hover:border-gray-500 transition-colors"
                                :class="marketerType === item.key ? 'border-[#0F807E] ring-1 ring-[#0F807E]' : ''">
                                <input type="radio" name="marketer_type" :value="item.key" x-model="marketerType"
                                    :required="section === 'marketer'" class="sr-only">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#0F807E]" x-show="marketerType === item.key" x-cloak></span>
                                <span class="font-bold text-white text-sm" x-text="item.label"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="mt-10 max-w-2xl mx-auto">
                    <button type="submit" :disabled="loading"
                        class="w-full inline-flex items-center justify-center gap-3 bg-[#0F807E] hover:opacity-90 disabled:opacity-60 text-white font-black text-base py-3.5 rounded-full transition-all duration-300">
                        {{ portal_content('join', 'form', 'submit', 'Continue', 'متابعة') }}
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="20" class="animate-bounce-x {{ $isAr ? '-scale-x-100' : '' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                        </svg>
                    </button>
                    <p class="mt-4 text-center text-xs text-gray-500">
                        {{ portal_content('join', 'form', 'note', 'You can change your country, section and specialization before completing registration.', 'يمكنك تغيير الدولة والقسم والتخصص قبل إكمال التسجيل.') }}
                    </p>
                </div>
            </form>
        </div>
    </div>
@endsection
