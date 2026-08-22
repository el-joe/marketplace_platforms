@php
    $isAr = session('locale', 'ar') === 'ar';

    $faqs = \App\Models\Faq::forContext('product_ads')->active()->orderBy('sort_order')->get()
        ->map(fn ($faq) => [
            'q_en' => $faq->question_en, 'q_ar' => $faq->question_ar,
            'a_en' => $faq->answer_en, 'a_ar' => $faq->answer_ar,
        ]);
@endphp

<section class="bg-white pb-16 lg:pb-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl sm:text-3xl lg:text-[34px] font-extrabold text-gray-900 text-center mb-8 lg:mb-10">
            {{ portal_content('advertise-product', 'faq', 'title', 'Frequently asked questions', 'الأسئلة الشائعة') }}
        </h2>

        <div class="divide-y divide-gray-100 border-y border-gray-100" x-data="{ openIndex: 0 }">
            @foreach($faqs as $idx => $faq)
                <div class="py-5">
                    <button @click="openIndex = openIndex === {{ $idx }} ? null : {{ $idx }}"
                            class="w-full flex items-center justify-between gap-4 {{ $isAr ? 'text-right' : 'text-left' }} focus:outline-none group">
                        <span class="font-bold text-gray-900 group-hover:text-gray-600 transition-colors text-pretty">
                            {{ $isAr ? $faq['q_ar'] : $faq['q_en'] }}
                        </span>
                        <span class="shrink-0 w-5 h-5 flex items-center justify-center">
                            <svg x-show="openIndex === {{ $idx }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-gray-700">
                                <path stroke-linecap="round" d="M5 12h14" />
                            </svg>
                            <svg x-show="openIndex !== {{ $idx }}" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-gray-700">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                            </svg>
                        </span>
                    </button>

                    <div x-show="openIndex === {{ $idx }}" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-3 {{ $isAr ? 'text-right' : 'text-left' }}">
                        <p class="text-gray-600 leading-relaxed text-pretty">
                            {{ $isAr ? $faq['a_ar'] : $faq['a_en'] }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
