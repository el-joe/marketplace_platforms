@php
    $variant = $variant ?? 'lite'; // 'home' | 'lite'
    $homeUrl = route('portal.adsupport.index', $country);
    $isAr = app()->getLocale() === 'ar';
@endphp

<header class="adsupport-fade-header relative flex flex-col text-black" x-data="{ mobileOpen: false }">
    <div class="relative flex grow flex-col {{ $variant === 'home' ? 'mb-9 pb-9' : 'pb-4' }}">
        <div class="flex h-full flex-col items-center">

            {{-- Top bar --}}
            <section class="relative flex w-full flex-col mb-6 pb-6">
                <div class="flex justify-center px-5 pt-6 leading-none sm:px-10">
                    <div class="flex items-center w-full lg:w-[960px]">
                        <div class="mo__body">
                            @php($headerLogo = portal_image('adsupport', 'header', 'logo', 'https://downloads.intercomcdn.com/i/o/yba8j1xj/654495/fd5bbb8bb174fa85e45ad370b70b/d9702cdb7e2403024b5c1d0d2a3f23e3.png', 'Advertise smarter, grow faster', 'أعلن بذكاء، وانمُ أسرع'))
                            <a href="{{ $homeUrl }}" class="block" aria-label="{{ $headerLogo['alt'] }}">
                                <img src="{{ $headerLogo['src'] }}"
                                     height="60" alt="{{ $headerLogo['alt'] }}" class="h-[60px] w-auto">
                            </a>
                        </div>

                        <div class="ms-auto flex items-center font-semibold">
                            {{-- Mobile hamburger --}}
                            <div class="flex items-center md:hidden">
                                <button type="button" @click="mobileOpen = !mobileOpen" class="flex items-center border-none bg-transparent px-1.5" aria-label="{{ portal_content('adsupport', 'header', 'open_menu', 'Open menu', 'فتح القائمة') }}">
                                    <svg width="24" height="24" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" class="fill-current">
                                        <path d="M1.86861 2C1.38889 2 1 2.3806 1 2.85008C1 3.31957 1.38889 3.70017 1.86861 3.70017H14.1314C14.6111 3.70017 15 3.31957 15 2.85008C15 2.3806 14.6111 2 14.1314 2H1.86861Z"></path>
                                        <path d="M1 8C1 7.53051 1.38889 7.14992 1.86861 7.14992H14.1314C14.6111 7.14992 15 7.53051 15 8C15 8.46949 14.6111 8.85008 14.1314 8.85008H1.86861C1.38889 8.85008 1 8.46949 1 8Z"></path>
                                        <path d="M1 13.1499C1 12.6804 1.38889 12.2998 1.86861 12.2998H14.1314C14.6111 12.2998 15 12.6804 15 13.1499C15 13.6194 14.6111 14 14.1314 14H1.86861C1.38889 14 1 13.6194 1 13.1499Z"></path>
                                    </svg>
                                </button>
                            </div>

                            {{-- Desktop nav --}}
                            <nav class="hidden items-center md:flex">
                                @php($startNowLink = portal_link('adsupport', 'header', 'start_now', 'Start now', 'ابدأ الآن', route('portal.register')))
                                <a target="_blank" rel="noopener noreferrer" href="{{ $startNowLink['url'] }}"
                                   class="mx-5 text-md no-underline hover:opacity-80 md:mx-3 md:text-base">{{ $startNowLink['label'] }}</a>
                                @php($contactUsLink = portal_link('adsupport', 'header', 'contact_us', 'Contact us', 'تواصل معنا', route('portal.advertise.request', $country)))
                                <a href="{{ $contactUsLink['url'] }}"
                                   class="mx-3 text-md no-underline hover:opacity-80 md:text-base">{{ $contactUsLink['label'] }}</a>
                            </nav>
                        </div>
                    </div>
                </div>

                {{-- Mobile menu --}}
                <div x-show="mobileOpen" x-cloak x-transition class="fixed right-0 top-0 z-50 h-full w-full md:hidden">
                    <div class="flex h-full w-full justify-end bg-black bg-opacity-30" @click="mobileOpen = false">
                        <div class="flex h-fit w-full flex-col bg-white opacity-100 sm:w-1/2" @click.stop>
                            <button type="button" @click="mobileOpen = false" class="flex items-center self-end border-none bg-transparent pr-6 pt-6" aria-label="{{ portal_content('adsupport', 'header', 'close_menu', 'Close menu', 'إغلاق القائمة') }}">
                                <svg width="24" height="24" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3.5097 3.5097C3.84165 3.17776 4.37984 3.17776 4.71178 3.5097L7.99983 6.79775L11.2879 3.5097C11.6198 3.17776 12.158 3.17776 12.49 3.5097C12.8219 3.84165 12.8219 4.37984 12.49 4.71178L9.20191 7.99983L12.49 11.2879C12.8219 11.6198 12.8219 12.158 12.49 12.49C12.158 12.8219 11.6198 12.8219 11.2879 12.49L7.99983 9.20191L4.71178 12.49C4.37984 12.8219 3.84165 12.8219 3.5097 12.49C3.17776 12.158 3.17776 11.6198 3.5097 11.2879L6.79775 7.99983L3.5097 4.71178C3.17776 4.37984 3.17776 3.84165 3.5097 3.5097Z"></path>
                                </svg>
                            </button>
                            <nav class="flex flex-col pl-4 text-black">
                                @php($startNowLinkMobile = portal_link('adsupport', 'header', 'start_now_mobile', 'Start now', 'ابدأ الآن', 'https://admanager.noon.partners/en-ae?utm_source=help_center&utm_medium=header'))
                                <a target="_blank" rel="noopener noreferrer" href="{{ $startNowLinkMobile['url'] }}" class="mx-5 mb-5 text-md no-underline hover:opacity-80">{{ $startNowLinkMobile['label'] }}</a>
                                @php($contactUsLinkMobile = portal_link('adsupport', 'header', 'contact_us', 'Contact us', 'تواصل معنا', route('portal.advertise.request', $country)))
                                <a href="{{ $contactUsLinkMobile['url'] }}" class="mx-5 mb-5 text-md no-underline hover:opacity-80">{{ $contactUsLinkMobile['label'] }}</a>
                            </nav>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Hero / search --}}
            <section class="relative mx-5 flex h-full w-full flex-col items-center px-5 sm:px-10">
                <div class="flex h-full max-w-full flex-col w-full lg:w-[960px] justify-center">
                    @if($variant === 'home')
                        <h1 class="text-3xl sm:text-4xl mb-6 font-bold text-black text-center">{{ portal_content('adsupport', 'header', 'hero_title', 'Hi, how can we help you?', 'مرحباً، كيف يمكننا مساعدتك؟') }}</h1>
                    @endif

                    <div class="relative w-full">
                        <form action="{{ $homeUrl }}" method="GET" autocomplete="off">
                            <div class="flex w-full flex-col items-center">
                                <div class="relative flex w-full sm:w-[640px]">
                                    <label for="search-input" class="sr-only">{{ portal_content('adsupport', 'header', 'search_placeholder', 'Search for articles...', 'ابحث في المقالات...') }}</label>
                                    <input id="search-input" type="text" name="q" autocomplete="off" value="{{ request('q') }}"
                                           placeholder="{{ portal_content('adsupport', 'header', 'search_placeholder', 'Search for articles...', 'ابحث في المقالات...') }}" aria-label="{{ portal_content('adsupport', 'header', 'search_placeholder', 'Search for articles...', 'ابحث في المقالات...') }}"
                                           class="peer w-full rounded-[10px] border border-black/10 bg-white/70 p-4 ps-12 font-sans text-lg text-black shadow-sm outline-none transition ease-linear placeholder:text-black hover:bg-white/80 focus:border-transparent focus:bg-white focus:shadow-md">
                                    <div class="absolute inset-y-0 start-0 flex items-center fill-black pointer-events-none ps-5">
                                        <svg width="22" height="21" viewBox="0 0 22 21" xmlns="http://www.w3.org/2000/svg" class="fill-inherit" aria-hidden="true">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M3.27485 8.7001C3.27485 5.42781 5.92757 2.7751 9.19985 2.7751C12.4721 2.7751 15.1249 5.42781 15.1249 8.7001C15.1249 11.9724 12.4721 14.6251 9.19985 14.6251C5.92757 14.6251 3.27485 11.9724 3.27485 8.7001ZM9.19985 0.225098C4.51924 0.225098 0.724854 4.01948 0.724854 8.7001C0.724854 13.3807 4.51924 17.1751 9.19985 17.1751C11.0802 17.1751 12.8176 16.5627 14.2234 15.5265L19.0981 20.4013C19.5961 20.8992 20.4033 20.8992 20.9013 20.4013C21.3992 19.9033 21.3992 19.0961 20.9013 18.5981L16.0264 13.7233C17.0625 12.3176 17.6749 10.5804 17.6749 8.7001C17.6749 4.01948 13.8805 0.225098 9.19985 0.225098Z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</header>
