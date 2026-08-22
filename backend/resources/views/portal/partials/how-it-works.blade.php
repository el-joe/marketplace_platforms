@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<div class="bg-[#151515] py-10 lg:py-12">
    <section class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-[#feee00] font-bold text-[28px] lg:text-[36px] mb-2">{{ portal_content('how-it-works', 'checklist', 'eyebrow', 'Getting Started', 'البدء') }}</h1>
        <h2 class="text-white font-bold text-[22px] lg:text-[26px] leading-tight mb-8 lg:mb-10">
            {{ portal_content('how-it-works', 'checklist', 'title', 'Ready to start selling?', 'جاهز انك تبدأ البيع؟') }}<br>
            {{ portal_content('how-it-works', 'checklist', 'subtitle', "It's quick and easy — here's what you'll need", 'انه سريع و سهل — اليك ما تحتاجه') }}
        </h2>

        <div class="grid md:grid-cols-2 gap-10 lg:gap-16 items-start">

            {{-- Floating device grid --}}
            <div class="relative mx-auto max-w-[420px] w-full">
                <div class="grid grid-cols-3 gap-4 items-center">
                    <div class="self-center -mt-6">
                        <img src="https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-sell-grid-01.png" alt=""
                             class="animate-[float_6s_ease-in-out_infinite] rounded-xl w-full">
                    </div>
                    <div class="flex flex-col gap-4">
                        <img src="https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-sell-grid-02.png" alt=""
                             class="animate-[float_7s_ease-in-out_infinite] rounded-xl w-full" style="animation-delay:1s">
                        <img src="https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-sell-grid-03.png" alt=""
                             class="animate-[float_5s_ease-in-out_infinite] rounded-xl w-full" style="animation-delay:.5s">
                    </div>
                    <div class="flex flex-col gap-4 mt-10">
                        <img src="https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-sell-grid-04.png" alt=""
                             class="animate-[float_6s_ease-in-out_infinite] rounded-xl w-full" style="animation-delay:1.5s">
                        <img src="https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-sell-grid-05.png" alt=""
                             class="animate-[float_8s_ease-in-out_infinite] rounded-xl w-full" style="animation-delay:.8s">
                    </div>
                </div>
            </div>

            {{-- Checklist + steps --}}
            <div>
                <h3 class="text-white font-bold text-lg mb-4">{{ portal_content('how-it-works', 'checklist', 'list_title', 'Seller Setup Checklist:', 'قائمة إعداد البائع:') }}</h3>
                <ul class="space-y-3 mb-8">
                    @foreach([
                        [portal_content('how-it-works', 'checklist', 'item_1', 'Email address and a phone number', 'عنوان بريد إلكتروني ورقم هاتف')],
                        [portal_content('how-it-works', 'checklist', 'item_2', 'Commercial Registration / Trade License', 'السجل التجاري / رخصة التجارة')],
                        [portal_content('how-it-works', 'checklist', 'item_3', 'Identity proof — Passport / Emirates ID', 'إثبات الهوية — جواز السفر / بطاقة الهوية الإماراتية')],
                    ] as $item)
                        <li class="flex items-start gap-3 text-gray-200 text-[15px]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#feee00" width="18" class="shrink-0 mt-1 {{ $isAr ? '-scale-x-100' : '' }}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                            <span>{{ $item[0] }}</span>
                        </li>
                    @endforeach
                </ul>

                @php($docsFaqCta = portal_link('how-it-works', 'checklist', 'documents_faq_button', 'Documents FAQs', 'الأسئلة الشائعة حول المستندات', route('portal.faq')))
                <a href="{{ $docsFaqCta['url'] }}"
                   class="inline-block bg-[#feee00] text-black text-[15px] font-bold w-full sm:w-[260px] text-center py-2.5 rounded-full hover:bg-[#e5d600] transition-colors mb-10">
                    {{ $docsFaqCta['label'] }}
                </a>

                <p class="text-[15px] text-gray-200 mb-4">
                    {{ portal_content('how-it-works', 'checklist', 'steps_intro', 'Once you have everything ready, getting started is super simple:', 'بمجرد أن يكون لديك كل شيء جاهزًا، فإن البدء بسيط جدًا:') }}
                </p>

                <div class="space-y-3 mb-6">
                    @foreach([
                        [portal_content('how-it-works', 'checklist', 'step_1_label', 'Step 1:', 'الخطوة 1:'), portal_content('how-it-works', 'checklist', 'step_1_text', 'Set up your account', 'إعداد حسابك')],
                        [portal_content('how-it-works', 'checklist', 'step_2_label', 'Step 2:', 'الخطوة 2:'), portal_content('how-it-works', 'checklist', 'step_2_text', 'List your products', 'إدراج منتجاتك')],
                        [portal_content('how-it-works', 'checklist', 'step_3_label', 'Step 3:', 'الخطوة 3:'), portal_content('how-it-works', 'checklist', 'step_3_text', 'Choose your fulfilment model', 'اختر نموذج التنفيذ الخاص بك')],
                    ] as $step)
                        <p class="text-[15px]">
                            <span class="text-gray-500">{{ $step[0] }} </span>
                            <span class="text-white font-semibold">{{ $step[1] }}</span>
                        </p>
                    @endforeach
                </div>

                <p class="text-[15px] text-gray-300 mb-4">
                    <span class="text-white font-bold">{{ portal_content('how-it-works', 'checklist', 'closing_lead', "And that's it!", 'و هذا هو كل شئ!') }}</span>
                    {{ portal_content('how-it-works', 'checklist', 'closing_text', ' Your first customer could be just a click away.', ' عميلك الأول قد يكون على بعد نقرة واحدة فقط.') }}
                </p>

                @php($checklistLearnMore = portal_link('how-it-works', 'checklist', 'learn_more_button', 'Learn more', 'اعرف أكثر', route('portal.how-it-works')))
                <a href="{{ $checklistLearnMore['url'] }}" class="inline-flex items-center gap-2 text-[#feee00] font-bold text-[15px] hover:text-[#e5d600] transition-colors">
                    {{ $checklistLearnMore['label'] }}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" class="{{ $isAr ? '-scale-x-100' : '' }}">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                    </svg>
                </a>
            </div>
        </div>
    </section>
</div>

{{-- Dubai Traders Program banner --}}
<section class="max-w-[1280px] w-full mx-auto px-4 sm:px-6 lg:px-8">
    <a href="{{ route('portal.register') }}" class="block w-full">
        <img src="https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-trader-banner-{{ $isAr ? 'ar' : 'en' }}.png"
             alt="{{ portal_content('how-it-works', 'trader_banner', 'alt', 'Dubai Traders Program', 'برنامج تجار دبي') }}" class="w-full rounded-2xl lg:hidden">
        <img src="https://f.nooncdn.com/s/app/pr-comms/sell-with-us/01-trader-banner-strip-{{ $isAr ? 'ar' : 'en' }}.jpg"
             alt="{{ portal_content('how-it-works', 'trader_banner', 'alt', 'Dubai Traders Program', 'برنامج تجار دبي') }}" class="w-full rounded-2xl hidden lg:block">
    </a>
</section>

<style>
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
</style>
