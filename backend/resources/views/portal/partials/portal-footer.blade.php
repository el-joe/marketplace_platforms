@php
    $isAr = session('locale', 'ar') === 'ar';
@endphp

<footer class="border-t border-white/10 bg-[#0b2422]" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
    <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8 py-4 text-center">
        <p class="text-xs sm:text-sm font-medium text-gray-400">
            {{ portal_content('footer', 'main', 'copyright', 'Nawy Platform - Empowering your digital business. © 2026 All Rights Reserved.', 'منصة ناوي - نمكّن أعمالك الرقمية. © 2026 جميع الحقوق محفوظة.') }}
        </p>
    </div>
</footer>
