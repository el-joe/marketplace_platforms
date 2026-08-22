@php $isAr = app()->getLocale() === 'ar'; @endphp
<footer class="mt-24 shrink-0 bg-white px-0 py-12 text-left text-base text-[#909aa5]">
    <div class="shrink-0 grow basis-auto px-5 sm:px-10">
        <div class="mx-auto max-w-[960px] sm:w-auto">
            <div class="flex flex-col md:flex-row" >
                <div class="mb-6 me-0 max-w-65 shrink-0 sm:mb-0 sm:me-18 sm:w-auto">
                    <div class="align-middle text-lg text-[#909aa5]">
                        <a class="no-underline" href="{{ route('portal.adsupport.index', $country ?? 'ae') }}">
                            <span>{{ portal_content('adsupport', 'footer', 'brand_label', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع') }}</span>
                        </a>
                    </div>
                </div>

                <div class="mt-18 flex grow flex-col md:mt-0 md:items-end">
                    <div class="grid grid-cols-2 gap-x-7 gap-y-14 md:flex md:flex-row md:flex-wrap">
                        <div class="w-1/2 sm:w-auto">
                            <div class="flex w-40 flex-col break-words">
                                <p class="mb-6 text-start font-semibold text-black">{{ portal_content('adsupport', 'footer', 'support_heading', 'Support', 'الدعم') }}</p>
                                <ul class="p-0">
                                    @php($footerContactLink = portal_link('adsupport', 'footer', 'contact_us', 'Contact us', 'تواصل معنا', 'mailto:adsupport@noon.com'))
                                    <li class="mb-4 list-none">
                                        <a target="_blank" href="{{ $footerContactLink['url'] }}" rel="nofollow noreferrer noopener" class="no-underline hover:text-black">{{ $footerContactLink['label'] }}</a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="w-1/2 sm:w-auto">
                            <div class="flex w-40 flex-col break-words">
                                <p class="mb-6 text-start font-semibold text-black">{{ portal_content('adsupport', 'footer', 'webinars_heading', 'Webinars', 'الندوات عبر الإنترنت') }}</p>
                                <ul class="p-0">
                                    @php($webinarEnLink = portal_link('adsupport', 'footer', 'webinar_english', 'English', 'الإنجليزية', 'https://outlook.office365.com/book/NoonTrainingCalenderUAE@nooncorpio.onmicrosoft.com/s/4eJP6vrZXkGt97Ji8aCx2g2'))
                                    <li class="mb-4 list-none">
                                        <a target="_blank" href="{{ $webinarEnLink['url'] }}" rel="nofollow noreferrer noopener" class="no-underline hover:text-black">{{ $webinarEnLink['label'] }}</a>
                                    </li>
                                    @php($webinarArLink = portal_link('adsupport', 'footer', 'webinar_arabic', 'Arabic', 'العربية', 'https://outlook.office365.com/book/Noon_KSA_Training@nooncorpio.onmicrosoft.com/s/W4-kOEYWQEqUuin7sziZuw2'))
                                    <li class="mb-4 list-none">
                                        <a target="_blank" href="{{ $webinarArLink['url'] }}" rel="nofollow noreferrer noopener" class="no-underline hover:text-black">{{ $webinarArLink['label'] }}</a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="w-1/2 sm:w-auto">
                            <div class="flex w-40 flex-col break-words">
                                <p class="mb-6 text-start font-semibold text-black">{{ portal_content('adsupport', 'footer', 'related_links_heading', 'Related Links', 'روابط ذات صلة') }}</p>
                                <ul class="p-0">
                                    @php($footerWebsiteLink = portal_link('adsupport', 'footer', 'our_website', 'Our website', 'موقعنا', route('portal.home')))
                                    <li class="mb-4 list-none">
                                        <a href="{{ $footerWebsiteLink['url'] }}" class="no-underline hover:text-black">{{ $footerWebsiteLink['label'] }}</a>
                                    </li>
                                    @php($footerAdManagerLink = portal_link('adsupport', 'footer', 'ad_manager', 'Ad Manager', 'مدير الإعلانات', route('portal.register')))
                                    <li class="mb-4 list-none">
                                        <a target="_blank" href="{{ $footerAdManagerLink['url'] }}" rel="nofollow noreferrer noopener" class="no-underline hover:text-black">{{ $footerAdManagerLink['label'] }}</a>
                                    </li>
                                    @php($footerLinkedinLink = portal_link('adsupport', 'footer', 'linkedin', 'Linkedin', 'Linkedin', 'https://www.linkedin.com/company/noon-ads/'))
                                    <li class="mb-4 list-none">
                                        <a target="_blank" href="{{ $footerLinkedinLink['url'] }}" rel="nofollow noreferrer noopener" class="no-underline hover:text-black">{{ $footerLinkedinLink['label'] }}</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
