@php $isAr = session('locale', 'ar') === 'ar'; @endphp

<footer class="text-center">
    <div class="bg-[#feee00] text-black">
        <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-12">
                <p class="font-black text-[11px] uppercase tracking-wider">{{ portal_content('cta_footer', 'main', 'eyebrow', 'Get Started', 'ابدأ الآن') }}</p>
                <p class="mt-2 text-[28px] sm:text-[34px] font-extrabold mb-3">
                    {{ portal_content('cta_footer', 'main', 'title', 'Ready to Sell?', 'جاهز للبيع؟') }}
                </p>
                <p class="text-[15px] font-medium max-w-[60ch] mx-auto text-[#1f1f1f]">
                    {{ portal_content('cta_footer', 'main', 'subtitle', 'Join thousands of sellers across the region who are growing with noon.', 'انضم إلى آلاف البائعين في جميع أنحاء المنطقة الذين ينمون ويكبرون مع نون.') }}
                </p>
                @php($registerCta = portal_link('cta_footer', 'main', 'button', 'Sign Up Now', 'سجل الآن', route('portal.register')))
                <a href="{{ $registerCta['url'] }}"
                   class="mt-8 inline-flex items-center justify-center gap-3 bg-black hover:bg-gray-900 text-white
                          font-black text-base min-w-[260px] py-[10px] rounded-full transition-colors">
                    {{ $registerCta['label'] }}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" class="animate-bounce-x {{ $isAr ? '-scale-x-100' : '' }}">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                    </svg>
                </a>
            </div>
        </div>

        <div class="flex justify-end px-4 pb-3">
            <p class="text-xs font-medium">
                &copy; {{ date('Y') }} {{ portal_content('cta_footer', 'main', 'copyright', 'noon. All rights reserved', 'نون. جميع الحقوق محفوظة') }}
            </p>
        </div>
    </div>
</footer>
