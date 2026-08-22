<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0284c7">

    <title>@yield('title', config('app.name'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

    <script>
    window.trans = @json(__('js'));
    window.t = window.t || function (path, vars) {
        const parts = String(path).split('.');
        let cur = window.trans || {};
        for (const part of parts) {
            cur = cur == null ? undefined : cur[part];
            if (cur === undefined) return path;
        }
        if (typeof cur === 'string' && vars) {
            return cur.replace(/\{(\w+)\}/g, (_, key) => (vars[key] ?? ''));
        }
        return cur;
    };
    </script>


    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @stack('head')
</head>

<body class="bg-gray-50 font-figtree text-gray-900 antialiased">

    {{-- App shell: full viewport height, column layout --}}
    <div class="flex flex-col h-dvh overflow-hidden">

        {{-- Scrollable content area --}}
        <main class="flex-1 overflow-hidden">
            @yield('content')
        </main>

        {{-- ─── Bottom Navigation ───────────────────────────────────────── --}}
        <nav class="shrink-0 bg-white border-t border-gray-100 safe-area-pb">
            <div class="flex items-stretch justify-around h-14">

                {{-- Home --}}
                <a href="{{ url('/') }}"
                   class="flex flex-col items-center justify-center gap-0.5 flex-1
                          {{ request()->is('/') ? 'text-primary-600' : 'text-gray-400' }}">
                    <x-heroicon name="home" class="w-6 h-6" />
                    <span class="text-[10px] leading-none">{{ __('common.storefront_nav.home') }}</span>
                </a>

                {{-- Categories --}}
                <a href="{{ url('/categories') }}"
                   class="flex flex-col items-center justify-center gap-0.5 flex-1
                          {{ request()->is('*/categories*') ? 'text-primary-600' : 'text-gray-400' }}">
                    <x-heroicon name="squares-2x2" class="w-6 h-6" />
                    <span class="text-[10px] leading-none">{{ __('common.storefront_nav.categories') }}</span>
                </a>

                {{-- Now Nawy (replaces "Now Watch") --}}
                <a href="{{ isset($countryModel) ? route('nawy.index', $countryModel->slug ?? $countryModel->iso_code_2) : '#' }}"
                   class="flex flex-col items-center justify-center gap-0.5 flex-1
                          {{ request()->routeIs('nawy.*') ? 'text-primary-600' : 'text-gray-400' }}">
                    <x-heroicon name="sparkles" class="w-6 h-6" />
                    <span class="text-[10px] leading-none">Now Nawy</span>
                </a>

                {{-- Cart --}}
                <a href="{{ url('/cart') }}"
                   class="flex flex-col items-center justify-center gap-0.5 flex-1
                          {{ request()->is('*/cart*') ? 'text-primary-600' : 'text-gray-400' }}">
                    <x-heroicon name="shopping-cart" class="w-6 h-6" />
                    <span class="text-[10px] leading-none">{{ __('common.storefront_nav.cart') }}</span>
                </a>

                {{-- Account --}}
                <a href="{{ url('/account') }}"
                   class="flex flex-col items-center justify-center gap-0.5 flex-1
                          {{ request()->is('*/account*') ? 'text-primary-600' : 'text-gray-400' }}">
                    <x-heroicon name="user-circle" class="w-6 h-6" />
                    <span class="text-[10px] leading-none">{{ __('common.storefront_nav.account') }}</span>
                </a>

            </div>
        </nav>

    </div>

    {{-- ─── Radio Mini-Player ─────────────────────────────────────────────── --}}
    <div id="radio-mini-player"
         x-data="radioMiniPlayer()"
         x-init="init()"
         x-show="visible"
         x-cloak
         class="fixed bottom-16 inset-x-0 z-50 px-3 pointer-events-none">
        <div class="bg-white/95 backdrop-blur-md border border-gray-200 rounded-2xl shadow-xl
                    flex items-center gap-3 px-4 py-3 pointer-events-auto"
             @click="goToPlayer()">

            {{-- Thumbnail / icon --}}
            <div class="w-10 h-10 rounded-xl shrink-0 overflow-hidden bg-indigo-100 flex items-center justify-center">
                <template x-if="thumbnail">
                    <img :src="thumbnail" class="w-full h-full object-cover">
                </template>
                <template x-if="!thumbnail">
                    <span class="text-xl" x-text="isVideo ? '📺' : '📻'"></span>
                </template>
            </div>

            {{-- Info --}}
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-gray-900 truncate" x-text="channelName"></div>
                <div class="text-[10px] text-gray-400" x-text="nowPlaying || (isLive ? @js(__('common.storefront_nav.live')) : @js(__('common.storefront_nav.playing')))"></div>
            </div>

            {{-- Live badge --}}
            <template x-if="isLive">
                <span class="text-[9px] font-bold px-1.5 py-0.5 bg-red-500 text-white rounded-full uppercase animate-pulse shrink-0">
                    LIVE
                </span>
            </template>

            {{-- Close --}}
            <button @click.stop="dismiss()"
                    class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-sm hover:bg-gray-200 shrink-0">
                ✕
            </button>
        </div>
    </div>

    @stack('scripts')
    <script>
    function radioMiniPlayer() {
        return {
            visible:     false,
            channelId:   null,
            channelName: '',
            channelSlug: '',
            nowPlaying:  '',
            isLive:      false,
            isVideo:     false,
            thumbnail:   null,
            dismissed:   false,

            init() {
                const saved = localStorage.getItem('radio_mini_player');
                if (saved) {
                    try {
                        const data = JSON.parse(saved);
                        Object.assign(this, data);
                        // Show mini player only if not on the full player page
                        this.visible = !window.location.pathname.includes('/radio/') && !this.dismissed;
                    } catch(_) {}
                }

                // Listen for player events dispatched from full player page
                window.addEventListener('radio:playing', (e) => {
                    Object.assign(this, e.detail);
                    this.dismissed = false;
                    this.visible   = !window.location.pathname.includes('/radio/');
                    localStorage.setItem('radio_mini_player', JSON.stringify({
                        channelId:   this.channelId,
                        channelName: this.channelName,
                        channelSlug: this.channelSlug,
                        nowPlaying:  this.nowPlaying,
                        isLive:      this.isLive,
                        isVideo:     this.isVideo,
                        thumbnail:   this.thumbnail,
                        dismissed:   false,
                    }));
                });

                window.addEventListener('radio:stopped', () => {
                    this.visible = false;
                    localStorage.removeItem('radio_mini_player');
                });
            },

            goToPlayer() {
                if (this.channelSlug) window.location.href = this.channelSlug;
            },

            dismiss() {
                this.visible   = false;
                this.dismissed = true;
                const saved    = localStorage.getItem('radio_mini_player');
                if (saved) {
                    try {
                        const data     = JSON.parse(saved);
                        data.dismissed = true;
                        localStorage.setItem('radio_mini_player', JSON.stringify(data));
                    } catch(_) {}
                }
            },
        };
    }
    </script>
</body>
</html>
