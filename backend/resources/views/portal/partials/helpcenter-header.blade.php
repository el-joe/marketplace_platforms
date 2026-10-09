@php
    $variant = $variant ?? 'lite'; // 'home' | 'lite'
    $homeUrl = route('portal.helpcenter.index', $country);
    $isAr = app()->getLocale() === 'ar';
@endphp

<header class="hc-fade-header relative flex flex-col text-black" x-data="{ mobileOpen: false }">
    <div class="relative flex grow flex-col {{ $variant === 'home' ? 'mb-8 pb-8' : 'pb-4' }}">
        <div class="flex h-full flex-col items-center">

            {{-- Top bar --}}
            <section class="relative flex w-full flex-col mb-6 pb-6 border-b border-black/5">
                <div class="flex justify-center px-4 pt-6 leading-none sm:px-8">
                    <div class="flex items-center justify-between w-full lg:w-[1000px]">
                        <a href="{{ $homeUrl }}" class="flex items-center gap-2" aria-label="{{ portal_content('helpcenter', 'header', 'home_aria', 'Nawy Seller Help Center', 'مركز مساعدة بائعي ناوي') }}">
                            @php($helpcenterLogo = portal_image('helpcenter', 'header', 'logo', asset('images/nawi-logo.png'), 'Nawy', 'Nawy'))
                            <img src="{{ $helpcenterLogo['src'] }}" alt="{{ $helpcenterLogo['alt'] }}" class="h-[28px] w-auto">
                            <span class="hidden sm:block text-sm font-semibold text-gray-800">{{ portal_content('helpcenter', 'header', 'brand_label', 'Seller Help Center', 'مركز مساعدة البائع') }}</span>
                        </a>

                        <div class="flex items-center gap-1 font-medium text-sm">
                            {{-- Language switcher --}}
                            <div class="hidden sm:flex items-center gap-1 me-2">
                                @php($langToggle = portal_link('helpcenter', 'header', 'language_toggle_desktop', 'العربية', 'English', route('portal.language', $isAr ? 'en' : 'ar')))
                                <a href="{{ $langToggle['url'] }}" class="px-2 py-1 rounded hover:bg-black/5 no-underline text-gray-700">
                                    {{ $langToggle['label'] }}
                                </a>
                            </div>

                            {{-- Mobile hamburger --}}
                            <div class="flex items-center md:hidden">
                                <button type="button" @click="mobileOpen = !mobileOpen" class="flex items-center border-none bg-transparent px-1.5" aria-label="{{ portal_content('helpcenter', 'header', 'open_menu_aria', 'Open menu', 'فتح القائمة') }}">
                                    <svg width="22" height="22" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" class="fill-current">
                                        <path d="M1.86861 2C1.38889 2 1 2.3806 1 2.85008C1 3.31957 1.38889 3.70017 1.86861 3.70017H14.1314C14.6111 3.70017 15 3.31957 15 2.85008C15 2.3806 14.6111 2 14.1314 2H1.86861Z"></path>
                                        <path d="M1 8C1 7.53051 1.38889 7.14992 1.86861 7.14992H14.1314C14.6111 7.14992 15 7.53051 15 8C15 8.46949 14.6111 8.85008 14.1314 8.85008H1.86861C1.38889 8.85008 1 8.46949 1 8Z"></path>
                                        <path d="M1 13.1499C1 12.6804 1.38889 12.2998 1.86861 12.2998H14.1314C14.6111 12.2998 15 12.6804 15 13.1499C15 13.6194 14.6111 14 14.1314 14H1.86861C1.38889 14 1 13.6194 1 13.1499Z"></path>
                                    </svg>
                                </button>
                            </div>

                            {{-- Desktop nav --}}
                            <nav class="hidden items-center md:flex">
                                @php($registerLink = portal_link('helpcenter', 'header', 'register_now', 'Register now', 'سجّل الآن', route('portal.register')))
                                <a href="{{ $registerLink['url'] }}" class="mx-3 no-underline hover:text-[#0B6866]">{{ $registerLink['label'] }}</a>
                                @php($contactLink = portal_link('helpcenter', 'header', 'contact_us', 'Contact us', 'تواصل معنا', 'mailto:seller@noon.com'))
                                <a href="{{ $contactLink['url'] }}" class="mx-3 rounded-full bg-[#0F807E] px-4 py-2 text-white no-underline hover:bg-[#0B6866]">{{ $contactLink['label'] }}</a>
                            </nav>
                        </div>
                    </div>
                </div>

                {{-- Mobile menu --}}
                <div x-show="mobileOpen" x-cloak x-transition class="fixed inset-0 z-50 h-full w-full md:hidden">
                    <div class="flex h-full w-full {{ $isAr ? 'justify-start' : 'justify-end' }} bg-black bg-opacity-30" @click="mobileOpen = false">
                        <div class="flex h-fit w-full flex-col bg-white opacity-100 sm:w-1/2" @click.stop>
                            <button type="button" @click="mobileOpen = false" class="flex items-center self-end border-none bg-transparent pe-6 pt-6" aria-label="{{ portal_content('helpcenter', 'header', 'close_menu_aria', 'Close menu', 'إغلاق القائمة') }}">✕</button>
                            <nav class="flex flex-col ps-6 pb-6 text-black">
                                @php($registerLinkMobile = portal_link('helpcenter', 'header', 'register_now', 'Register now', 'سجّل الآن', route('portal.register')))
                                <a href="{{ $registerLinkMobile['url'] }}" class="mb-4 no-underline hover:text-[#0B6866]">{{ $registerLinkMobile['label'] }}</a>
                                @php($contactLinkMobile = portal_link('helpcenter', 'header', 'contact_us', 'Contact us', 'تواصل معنا', 'mailto:seller@noon.com'))
                                <a href="{{ $contactLinkMobile['url'] }}" class="mb-4 no-underline hover:text-[#0B6866]">{{ $contactLinkMobile['label'] }}</a>
                                @php($langToggleMobile = portal_link('helpcenter', 'header', 'language_toggle_mobile', 'العربية', 'English', route('portal.language', $isAr ? 'en' : 'ar')))
                                <a href="{{ $langToggleMobile['url'] }}" class="mb-4 no-underline hover:text-[#0B6866]">{{ $langToggleMobile['label'] }}</a>
                            </nav>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Hero / search --}}
            <section class="relative mx-4 flex h-full w-full flex-col items-center px-4 sm:px-8">
                <div class="flex h-full max-w-full flex-col w-full lg:w-[1000px] justify-center">
                    @if($variant === 'home')
                        <h1 class="text-2xl sm:text-4xl mb-6 font-bold text-black text-center">{{ portal_content('helpcenter', 'header', 'hero_title', 'Hi, how can we help you?', 'مرحباً، كيف يمكننا مساعدتك؟') }}</h1>
                    @endif

                    <div class="relative w-full">
                        <form action="{{ route('portal.helpcenter.search', $country) }}" method="GET" autocomplete="off">
                            <div class="flex w-full flex-col items-center">
                                <div class="relative flex w-full sm:w-[640px]">
                                    <label for="hc-search-input" class="sr-only">{{ portal_content('helpcenter', 'header', 'search_placeholder', 'Search for articles...', 'ابحث في المقالات...') }}</label>
                                    <input id="hc-search-input" type="text" name="q" autocomplete="off" value="{{ request('q') }}"
                                           placeholder="{{ portal_content('helpcenter', 'header', 'search_placeholder', 'Search for articles...', 'ابحث في المقالات...') }}"
                                           class="peer w-full rounded-[10px] border border-black/10 bg-white/80 p-4 ps-12 text-lg text-black shadow-sm outline-none transition ease-linear placeholder:text-gray-400 hover:bg-white focus:border-[#0F807E] focus:bg-white focus:shadow-md">
                                    <div class="absolute inset-y-0 start-0 flex items-center fill-gray-400 pointer-events-none ps-5">
                                        <svg width="20" height="20" viewBox="0 0 22 21" xmlns="http://www.w3.org/2000/svg" class="fill-inherit" aria-hidden="true">
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
