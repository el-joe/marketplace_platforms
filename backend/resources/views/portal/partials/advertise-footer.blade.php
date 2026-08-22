@php
    $isAr = session('locale', 'ar') === 'ar';
    $footerSell = portal_link('advertise_footer', 'main', 'sell_with_us', 'Sell with us', 'بيع معنا', route('portal.register'));
    $footerTerms = portal_link('advertise_footer', 'main', 'terms_of_use', 'Terms of Use', 'شروط الاستخدام', 'https://www.noon.com/uae-en/terms-of-use/');
    $footerSale = portal_link('advertise_footer', 'main', 'terms_of_sale', 'Terms of Sale', 'شروط البيع', 'https://www.noon.com/uae-en/terms-of-sale/');
    $footerPrivacy = portal_link('advertise_footer', 'main', 'privacy_policy', 'Privacy Policy', 'سياسة الخصوصية', 'https://www.noon.com/uae-en/privacy-policy/');
@endphp

<footer class="bg-gray-50 border-t border-gray-100">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-center">
        <p class="text-xs sm:text-sm text-gray-500 order-2 sm:order-1">
            &copy; {{ date('Y') }} {{ portal_content('advertise_footer', 'main', 'copyright', 'noon. All Rights Reserved', 'نون. جميع الحقوق محفوظة') }}
        </p>
        <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs sm:text-sm font-medium text-gray-500 order-1 sm:order-2">
            <a href="{{ $footerSell['url'] }}" class="hover:text-gray-900">{{ $footerSell['label'] }}</a>
            <a href="{{ $footerTerms['url'] }}" target="_blank" rel="noopener" class="hover:text-gray-900">{{ $footerTerms['label'] }}</a>
            <a href="{{ $footerSale['url'] }}" target="_blank" rel="noopener" class="hover:text-gray-900">{{ $footerSale['label'] }}</a>
            <a href="{{ $footerPrivacy['url'] }}" target="_blank" rel="noopener" class="hover:text-gray-900">{{ $footerPrivacy['label'] }}</a>
        </div>
    </div>
</footer>
