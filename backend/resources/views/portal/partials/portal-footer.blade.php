@php
    $isAr = session('locale', 'ar') === 'ar';
    $footerLogo = portal_image('footer', 'logo', 'image', asset('images/nawy_logo_transparent.png'), 'Nawy', 'ناوي');
@endphp

<footer class="border-t border-white/10 bg-gray-950" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
    <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8 py-10 flex flex-col items-center gap-5 text-center">
        <img src="{{ $footerLogo['src'] }}" alt="{{ $footerLogo['alt'] }}" class="h-[38px] w-auto">
        <p class="text-xs font-medium text-gray-500">
            &copy; {{ date('Y') }}
            {{ portal_content('footer', 'main', 'copyright', 'Nawy. All rights reserved', 'ناوي. جميع الحقوق محفوظة') }}
        </p>
    </div>
</footer>
