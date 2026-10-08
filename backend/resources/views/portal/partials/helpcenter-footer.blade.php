@php $isAr = app()->getLocale() === 'ar'; @endphp
<footer class="mt-20 shrink-0 bg-gray-50 px-0 py-12 text-base text-gray-500">
    <div class="shrink-0 grow basis-auto px-4 sm:px-8">
        <div class="mx-auto max-w-[1000px]">
            <div class="flex flex-col md:flex-row">
                <div class="mb-6 max-w-xs shrink-0 md:mb-0 {{ $isAr ? 'md:ms-16' : 'md:me-16' }}">
                    <a class="no-underline flex items-center gap-2" href="{{ route('portal.helpcenter.index', $country ?? 'ae') }}">
                        <img src="{{ asset('images/nawi-logo.png') }}" alt="Nawy" class="h-[24px] w-auto">
                        <span class="text-gray-700 font-medium">{{ portal_content('helpcenter', 'footer', 'brand_label', 'Nawy Seller Help Center', 'مركز مساعدة البائع') }}</span>
                    </a>
                </div>

                <div class="mt-10 flex grow flex-col md:mt-0 {{ $isAr ? 'md:items-start' : 'md:items-end' }}">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-10 md:flex md:flex-row md:flex-wrap">
                        <div class="w-1/2 sm:w-auto">
                            <div class="flex w-40 flex-col break-words">
                                <p class="mb-4 font-semibold text-gray-900">{{ portal_content('helpcenter', 'footer', 'support_heading', 'Support', 'الدعم') }}</p>
                                <ul class="p-0 m-0">
                                    @php($footerContactLink = portal_link('helpcenter', 'footer', 'contact_us', 'Contact us', 'تواصل معنا', 'mailto:seller@noon.com'))
                                    <li class="mb-3 list-none"><a href="{{ $footerContactLink['url'] }}" class="no-underline hover:text-[#0B6866]">{{ $footerContactLink['label'] }}</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="w-1/2 sm:w-auto">
                            <div class="flex w-40 flex-col break-words">
                                <p class="mb-4 font-semibold text-gray-900">{{ portal_content('helpcenter', 'footer', 'related_links_heading', 'Related Links', 'روابط ذات صلة') }}</p>
                                <ul class="p-0 m-0">
                                    @php($footerWebsiteLink = portal_link('helpcenter', 'footer', 'our_website', 'Our website', 'موقعنا', route('portal.home')))
                                    <li class="mb-3 list-none"><a href="{{ $footerWebsiteLink['url'] }}" class="no-underline hover:text-[#0B6866]">{{ $footerWebsiteLink['label'] }}</a></li>
                                    @php($footerRegisterLink = portal_link('helpcenter', 'footer', 'register_as_seller', 'Register as a seller', 'سجّل كبائع', route('portal.register')))
                                    <li class="mb-3 list-none"><a href="{{ $footerRegisterLink['url'] }}" class="no-underline hover:text-[#0B6866]">{{ $footerRegisterLink['label'] }}</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-12 pt-6 border-t border-gray-200 text-sm text-gray-400">
                &copy; {{ now()->year }} Nawy. {{ portal_content('helpcenter', 'footer', 'copyright', 'All rights reserved.', 'جميع الحقوق محفوظة.') }}
            </div>
        </div>
    </div>
</footer>
