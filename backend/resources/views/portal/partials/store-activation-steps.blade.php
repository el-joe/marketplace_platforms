@php $isAr = session('locale', 'ar') === 'ar'; @endphp

@push('head')
<style>
    @keyframes blink-four-times {
        0%, 100% { opacity: 1; }
        50% { opacity: 0; }
    }
    .animate-blink-4 {
        animation: blink-four-times 0.6s ease-in-out 4 forwards;
    }
</style>
@endpush

<section class="bg-black relative pt-8 pb-10 lg:pb-12 md:py-10 lg:py-12">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <h2 class="text-[32px] sm:text-[40px] lg:text-[48px] font-black mb-6 lg:mb-8 text-white leading-tight">
            {{ portal_content('how-it-works', 'steps', 'title_prefix', 'Steps to ', 'خطوات لـ ') }}<span class="text-[#feee00] animate-blink-4 inline-block">{{ portal_content('how-it-works', 'steps', 'title_highlight', 'Go Live', 'البدء') }}</span>
        </h2>

        <div class="rounded-2xl overflow-hidden bg-[#1c1c1c] md:bg-transparent md:grid md:grid-cols-[1.5fr_2fr] md:gap-10 lg:gap-14">
            <div class="relative aspect-[4/3] sm:aspect-[2/1] md:aspect-auto md:rounded-2xl md:overflow-hidden">
                @php($stepsImg = portal_image('how-it-works', 'steps', 'photo', 'https://f.nooncdn.com/s/app/pr-comms/sell-with-us/02-steps-go-live.jpg', 'noon Employees packing items into crates', 'موظفو نون يعبئون الصناديق'))
                <img src="{{ $stepsImg['src'] }}"
                     alt="{{ $stepsImg['alt'] }}"
                     class="absolute inset-0 w-full h-full object-cover">
            </div>
            <div class="pt-8 px-6 pb-10 md:px-0 md:py-6">
                <h3 class="text-white font-black text-xl lg:text-2xl">
                    {{ portal_content('how-it-works', 'steps', 'subtitle', 'Start selling on noon in three easy steps', 'ابدأ البيع على نون بثلاث خطوات سهلة') }}
                </h3>

                <ul class="mt-6 space-y-3">
                    @foreach([
                        [portal_content('how-it-works', 'steps', 'step_1', 'Set up your account', 'قم بإعداد حسابك')],
                        [portal_content('how-it-works', 'steps', 'step_2', 'Get your listings ready', 'جهز قوائم منتجاتك')],
                        [portal_content('how-it-works', 'steps', 'step_3', 'Choose your fulfilment model', 'اختر نموذج التنفيذ الخاص بك')],
                    ] as $item)
                        <li class="flex items-start gap-3 text-white font-bold text-[15px]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#feee00" width="16" class="shrink-0 mt-1 {{ $isAr ? '-scale-x-100' : '' }}">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                            <span>{{ $item[0] }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-8 text-gray-300 font-medium text-sm">
                    {{ portal_content('how-it-works', 'steps', 'closing_note', "That's it — you're ready to receive your first order", 'هذا كل شيء — أنت مستعد لتلقي أول طلب لك') }}
                </p>
            </div>
        </div>
    </div>
</section>
