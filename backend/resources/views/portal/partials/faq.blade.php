@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<section class="bg-gray-900 py-24" id="faq">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Section header --}}
        <div class="text-center mb-16">
            <span class="inline-block bg-[#feee00]/10 border border-[#feee00]/30 text-[#feee00]
                         text-sm font-semibold px-4 py-1.5 rounded-full mb-4">
                {{ portal_content('faq', 'header', 'eyebrow', 'FAQ', 'الأسئلة الشائعة') }}
            </span>
            <h2 class="text-3xl sm:text-4xl font-black text-white">
                {{ portal_content('faq', 'header', 'title', 'Questions Sellers Ask', 'أسئلة يسألها البائعون') }}
            </h2>
            <p class="mt-4 text-gray-400">
                {{ portal_content('faq', 'header', 'subtitle', 'Answers to the most common questions about selling on Noon.', 'إجابات لأكثر الأسئلة شيوعاً حول البيع على نون.') }}
            </p>
        </div>

        @php
            $faqs = \App\Models\Faq::forContext('seller')->active()->orderBy('sort_order')->get()
                ->map(fn ($faq) => ['q' => $faq->localizedQuestion(), 'a' => $faq->localizedAnswer()]);
        @endphp

        <div class="space-y-3" x-data="{ openIndex: null }">
            @foreach($faqs as $idx => $faq)
                <div class="bg-gray-800/50 border border-gray-700 rounded-2xl overflow-hidden
                            hover:border-gray-600 transition-colors">
                    <button @click="openIndex = openIndex === {{ $idx }} ? null : {{ $idx }}" class="w-full flex items-center justify-between gap-4 px-6 py-5
                               text-{{ $isAr ? 'right' : 'left' }} focus:outline-none group">
                        <span class="font-semibold text-white group-hover:text-[#feee00] transition-colors">
                            {{ $faq['q'] }}
                        </span>
                        <span class="shrink-0 w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center
                                     transition-colors group-hover:bg-[#feee00]/20">
                            <svg class="w-4 h-4 text-gray-400 group-hover:text-[#feee00] transition-all duration-200"
                                :class="openIndex === {{ $idx }} ? 'rotate-45' : ''" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2.5">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                        </span>
                    </button>

                    <div x-show="openIndex === {{ $idx }}" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0" class="px-6 pb-5">
                        <p class="text-gray-400 leading-relaxed {{ $isAr ? 'text-right' : 'text-left' }}">
                            {{ $faq['a'] }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Still have questions CTA --}}
        <div class="mt-12 text-center bg-gray-800/50 border border-gray-700 rounded-3xl p-8">
            <div class="text-3xl mb-3">🤔</div>
            <h3 class="text-xl font-black text-white mb-2">
                {{ portal_content('faq', 'contact_cta', 'title', 'Have Another Question?', 'لديك سؤال آخر؟') }}
            </h3>
            <p class="text-gray-400 mb-6">
                {{ portal_content('faq', 'contact_cta', 'subtitle', 'Our dedicated support team is available 24/7 to answer all your inquiries.', 'فريق الدعم المخصص لدينا متاح ٢٤/٧ للإجابة على جميع استفساراتك.') }}
            </p>
            @php($contactCta = portal_link('faq', 'contact_cta', 'button', 'Contact Support', 'تواصل مع الدعم', 'mailto:sellers@noon.com'))
            <a href="{{ $contactCta['url'] }}" class="inline-flex items-center gap-2 bg-[#feee00] hover:bg-[#e5d600] text-gray-950
                      font-bold px-6 py-3 rounded-xl transition-colors">
                {{ $contactCta['label'] }}
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                    <polyline points="22,6 12,13 2,6" />
                </svg>
            </a>
        </div>

    </div>
</section>
