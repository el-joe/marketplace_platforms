@extends('layouts.portal')

@php
    $isAr = session('locale', 'ar') === 'ar';
    $entityTypes = [
        'company' => [
            'title' => portal_content('companies', 'entity_company', 'title', 'Specialized Company', 'شركة تخصصية'),
            'desc' => portal_content('companies', 'entity_company', 'description', 'A registered company offering its services in one of the supported specializations.', 'شركة مسجّلة تقدّم خدماتها في أحد التخصصات المدعومة.'),
        ],
        'broker' => [
            'title' => portal_content('companies', 'entity_broker', 'title', 'Broker / Intermediary', 'وسيط'),
            'desc' => portal_content('companies', 'entity_broker', 'description', 'An individual intermediary connecting buyers and sellers in a specialization.', 'وسيط فردي يربط بين المشترين والبائعين في أحد التخصصات.'),
        ],
    ];
@endphp

@section('title', portal_content('companies', 'meta', 'title', 'Specialized Companies & Brokers', 'الشركات التخصصية والوسطاء'))

@section('nav')
    @include('portal.partials.section-nav', [
        'section' => 'classified_vendor',
        'links' => [
            ['key' => 'how', 'href' => route('portal.companies') . '#how', 'label_en' => 'How to join', 'label_ar' => 'كيف تنضم'],
            ['key' => 'benefits', 'href' => route('portal.companies') . '#benefits', 'label_en' => 'Benefits', 'label_ar' => 'المزايا'],
            ['key' => 'specializations', 'href' => route('portal.companies') . '#specializations', 'label_en' => 'Specializations', 'label_ar' => 'التخصصات'],
            ['key' => 'register', 'href' => route('portal.companies') . '#register', 'label_en' => 'Join now', 'label_ar' => 'انضم الآن'],
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
                {{ portal_content('companies', 'hero', 'back', 'Back to join options', 'الرجوع لاختيار نوع الانضمام') }}
            </a>

            <div class="mt-8 max-w-3xl">
                <h1 class="text-white font-extrabold text-3xl sm:text-4xl lg:text-[42px] leading-tight">
                    {{ portal_content('companies', 'hero', 'title', 'Join as a specialized company or broker', 'انضم كشركة تخصصية أو وسيط') }}
                </h1>
                <p class="mt-4 text-gray-400 text-base sm:text-lg">
                    {{ portal_content('companies', 'hero', 'subtitle', 'Grow your business on Nawy, reach more clients in your field, and manage your presence from one place.', 'طوّر أعمالك على ناوي، وصل إلى عملاء أكثر في مجالك، وأدر حضورك من مكان واحد.') }}
                </p>
            </div>

            <div id="benefits" class="mt-10 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['title' => portal_content('companies', 'benefit_reach', 'title', 'Wider reach', 'انتشار أوسع'), 'desc' => portal_content('companies', 'benefit_reach', 'description', 'Show your services to clients actively searching in your specialization.', 'اعرض خدماتك لعملاء يبحثون فعلياً في تخصصك.')],
                    ['title' => portal_content('companies', 'benefit_control', 'title', 'Full control', 'تحكم كامل'), 'desc' => portal_content('companies', 'benefit_control', 'description', 'Manage your listings, inquiries and profile from your own dashboard.', 'أدر إعلاناتك واستفساراتك وملفك من لوحة تحكم خاصة.')],
                    ['title' => portal_content('companies', 'benefit_trust', 'title', 'Verified presence', 'حضور موثوق'), 'desc' => portal_content('companies', 'benefit_trust', 'description', 'A trusted platform experience that builds client confidence.', 'تجربة منصة موثوقة تعزز ثقة العملاء.')],
                ] as $benefit)
                    <div class="rounded-2xl border border-gray-800 bg-gray-900 p-6">
                        <h3 class="text-white font-bold text-base">{{ $benefit['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ $benefit['desc'] }}</p>
                    </div>
                @endforeach
            </div>

            @if (session('join_status'))
                <div class="mt-10 rounded-xl border border-[#feee00]/40 bg-[#feee00]/10 px-5 py-4 text-sm font-medium text-[#feee00]">
                    {{ session('join_status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-10 rounded-xl border border-red-500/40 bg-red-500/10 px-5 py-4 text-sm text-red-300">
                    <ul class="space-y-1 {{ $isAr ? 'list-disc pr-5' : 'list-disc pl-5' }}">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('portal.join.continue') }}" class="mt-12" id="register"
                x-data="{
                    entity: @js(old('entity_type', $selection['entity_type'] ?? '')),
                    category: @js(old('classified_category_id', $selection['classified_category_id'] ?? '')),
                }">
                @csrf
                <input type="hidden" name="section" value="classified_vendor">
                <input type="hidden" name="country_id" value="{{ $currentCountry?->id }}">

                {{-- Company vs broker --}}
                <fieldset id="how">
                    <legend class="text-white font-bold text-lg mb-4">{{ portal_content('companies', 'form', 'entity_label', 'How would you like to join?', 'كيف تريد الانضمام؟') }}</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($entityTypes as $value => $entity)
                            <label class="cursor-pointer rounded-2xl border bg-gray-900 p-5 transition-all duration-200"
                                :class="entity === @js($value) ? 'border-[#feee00] ring-1 ring-[#feee00]' : 'border-gray-700 hover:border-gray-500'">
                                <input type="radio" name="entity_type" value="{{ $value }}" x-model="entity" class="sr-only" required>
                                <span class="block text-white font-bold">{{ $entity['title'] }}</span>
                                <span class="mt-2 block text-sm text-gray-400 leading-relaxed">{{ $entity['desc'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p x-show="! entity" x-cloak class="mt-2 text-xs text-gray-500">{{ __('portal.join.entity_type_required') }}</p>
                </fieldset>

                {{-- Specialization --}}
                <fieldset id="specializations" class="mt-10">
                    <legend class="text-white font-bold text-lg mb-4">{{ portal_content('companies', 'form', 'category_label', 'Your specialization', 'التخصص') }}</legend>
                    @if ($categories->isEmpty())
                        <p class="text-sm text-gray-400">{{ portal_content('companies', 'form', 'category_empty', 'Specializations are being updated, please check back soon.', 'يتم تحديث التخصصات حالياً، يرجى المحاولة قريباً.') }}</p>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($categories as $category)
                                <label class="cursor-pointer rounded-xl border bg-gray-900 px-4 py-3.5 transition-all duration-200"
                                    :class="category === @js($category->id) ? 'border-[#feee00] ring-1 ring-[#feee00]' : 'border-gray-700 hover:border-gray-500'">
                                    <input type="radio" name="classified_category_id" value="{{ $category->id }}" x-model="category" class="sr-only" required>
                                    <span class="text-sm font-bold text-white">{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>

                <div class="mt-10 max-w-2xl">
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-3 bg-[#feee00] hover:opacity-90 text-gray-950 font-black text-base py-3.5 rounded-full transition-all duration-300">
                        {{ portal_content('companies', 'form', 'submit', 'Continue', 'متابعة') }}
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="20" class="{{ $isAr ? '-scale-x-100' : '' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
