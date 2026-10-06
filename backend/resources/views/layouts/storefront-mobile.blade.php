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
                    <span class="text-[10px] leading-none">{{ __('common.nav.now_nawy') }}</span>
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

    @stack('scripts')
</body>
</html>
