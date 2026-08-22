@php $isAr = app()->getLocale() === 'ar'; @endphp
<footer class="mt-20 shrink-0 bg-gray-50 px-0 py-12 text-base text-gray-500">
    <div class="shrink-0 grow basis-auto px-4 sm:px-8">
        <div class="mx-auto max-w-[1000px]">
            <div class="flex flex-col md:flex-row">
                <div class="mb-6 max-w-xs shrink-0 md:mb-0 {{ $isAr ? 'md:ms-16' : 'md:me-16' }}">
                    <a class="no-underline flex items-center gap-2" href="{{ route('portal.helpcenter.index', $country ?? 'ae') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="18" viewBox="0 0 659.5 288.2" fill="currentColor" class="text-orange-500" aria-hidden="true">
                            <path d="M621.1,72.1v46.2c0,22.9-18.6,41.5-41.5,41.5h-199.7v-39.4c0-23.1-10.1-44.8-27.8-59.6-17.7-14.8-41-21-64-17-31.8,5.6-57,30.8-62.6,62.6-4.1,23,2.1,46.3,17,64,14.8,17.7,36.6,27.8,59.6,27.8h38.8c-1.8,13.6-8.3,26.2-18.5,35.7-11,10.3-25.4,15.9-40.4,15.9h-2.9v38.4h2.9c25.2,0,49.2-9.6,67.5-27,17.5-16.7,28.1-39,30.1-63h200.1c44,0,79.9-35.8,79.9-79.9v-46.2h-38.4ZM341.5,120.4v39.4h-39.4c-11.7,0-22.7-5.1-30.2-14.1-7.6-9.1-10.7-20.7-8.5-32.6,2.8-15.7,15.7-28.7,31.4-31.4,12-2.1,23.5.9,32.6,8.5,9,7.5,14.1,18.5,14.1,30.2Z"></path>
                            <path d="M98,243.1c54,0,98-44,98-98v-72.8h-38.4v72.8c0,32.8-26.7,59.6-59.6,59.6s-59.6-26.7-59.6-59.6v-72.8H0v72.8c0,54,44,98,98,98Z"></path>
                            <path d="M98,41.9c11.6,0,21-9.4,21-21S109.5,0,98,0s-21,9.4-21,21,9.4,21,21,21Z"></path>
                            <path d="M582.4,41.9c11.6,0,21-9.4,21-21s-9.4-21-21-21-21,9.4-21,21,9.4,21,21,21Z"></path>
                        </svg>
                        <span class="text-gray-700 font-medium">{{ portal_content('helpcenter', 'footer', 'brand_label', 'noon Seller Help Center', 'مركز مساعدة البائع') }}</span>
                    </a>
                </div>

                <div class="mt-10 flex grow flex-col md:mt-0 {{ $isAr ? 'md:items-start' : 'md:items-end' }}">
                    <div class="grid grid-cols-2 gap-x-8 gap-y-10 md:flex md:flex-row md:flex-wrap">
                        <div class="w-1/2 sm:w-auto">
                            <div class="flex w-40 flex-col break-words">
                                <p class="mb-4 font-semibold text-gray-900">{{ portal_content('helpcenter', 'footer', 'support_heading', 'Support', 'الدعم') }}</p>
                                <ul class="p-0 m-0">
                                    @php($footerContactLink = portal_link('helpcenter', 'footer', 'contact_us', 'Contact us', 'تواصل معنا', 'mailto:seller@noon.com'))
                                    <li class="mb-3 list-none"><a href="{{ $footerContactLink['url'] }}" class="no-underline hover:text-orange-600">{{ $footerContactLink['label'] }}</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="w-1/2 sm:w-auto">
                            <div class="flex w-40 flex-col break-words">
                                <p class="mb-4 font-semibold text-gray-900">{{ portal_content('helpcenter', 'footer', 'related_links_heading', 'Related Links', 'روابط ذات صلة') }}</p>
                                <ul class="p-0 m-0">
                                    @php($footerWebsiteLink = portal_link('helpcenter', 'footer', 'our_website', 'Our website', 'موقعنا', route('portal.home')))
                                    <li class="mb-3 list-none"><a href="{{ $footerWebsiteLink['url'] }}" class="no-underline hover:text-orange-600">{{ $footerWebsiteLink['label'] }}</a></li>
                                    @php($footerRegisterLink = portal_link('helpcenter', 'footer', 'register_as_seller', 'Register as a seller', 'سجّل كبائع', route('portal.register')))
                                    <li class="mb-3 list-none"><a href="{{ $footerRegisterLink['url'] }}" class="no-underline hover:text-orange-600">{{ $footerRegisterLink['label'] }}</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-12 pt-6 border-t border-gray-200 text-sm text-gray-400">
                &copy; {{ now()->year }} noon. {{ portal_content('helpcenter', 'footer', 'copyright', 'All rights reserved.', 'جميع الحقوق محفوظة.') }}
            </div>
        </div>
    </div>
</footer>
