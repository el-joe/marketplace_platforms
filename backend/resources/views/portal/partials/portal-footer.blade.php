@php
    $isAr = session('locale', 'ar') === 'ar';
    $locale = $isAr ? 'ar' : 'en';

    // Admin-managed static pages (Admin > Static Pages), cached as plain arrays.
    $staticPageGroups = collect(\App\Models\StaticPage::footerLinksFor(\App\Models\StaticPage::AUDIENCE_PORTAL, \App\Models\StaticPage::portalCountryId()))
        ->groupBy(fn (array $link) => $link['footer_group'] ?? '')
        ->sortBy(fn ($links, string $group) => $group === '' ? PHP_INT_MAX : array_search($group, \App\Models\StaticPage::FOOTER_GROUPS, true));
@endphp

<footer data-portal-section="portal-footer" class="border-t border-white/10 bg-gray-950" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
    @if ($staticPageGroups->isNotEmpty())
        <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-2">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 text-start" data-portal-footer-links>
                @foreach ($staticPageGroups as $group => $links)
                    <div>
                        @if ($group !== '')
                            <h3 class="text-sm font-semibold text-white mb-3">{{ __('portal.static_pages.footer_groups.'.$group) }}</h3>
                        @endif
                        <ul class="space-y-2">
                            @foreach ($links as $link)
                                <li>
                                    <a href="{{ route('portal.pages.show', $link['slug']) }}"
                                       class="text-xs sm:text-sm text-gray-400 hover:text-white transition-colors">
                                        {{ $link['title'][$locale] ?: ($link['title'][$isAr ? 'en' : 'ar'] ?? '') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-8 py-4 text-center">
        <p class="text-xs sm:text-sm font-medium text-gray-400">
            {{ portal_content('footer', 'main', 'copyright', 'Nawy Platform - Empowering your digital business. © 2026 All Rights Reserved.', 'منصة ناوي - نمكّن أعمالك الرقمية. © 2026 جميع الحقوق محفوظة.') }}
        </p>
    </div>
</footer>
