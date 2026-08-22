<!DOCTYPE html>
<html lang="{{ session('locale', 'ar') }}" dir="{{ session('locale', 'ar') === 'ar' ? 'rtl' : 'ltr' }}" class="scroll-smooth scroll-pt-[72px] overflow-x-hidden">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('common.sell_on_noon_title')) | {{ __('common.partner_brand_title') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    {{-- Cairo for Arabic --}}
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800,900&display=swap" rel="stylesheet" />

    @stack('head')
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

    @vite(['resources/css/app.css', 'resources/js/portal/app.js'])
</head>

<body class="bg-black text-white antialiased overflow-x-hidden" style="font-family: 'Cairo', 'Figtree', sans-serif;">

    @unless(View::hasSection('hide_nav'))
        @include('portal.partials.nav')
    @endunless

    <main>
        @yield('content')
    </main>


    {{-- Scroll-to-top --}}
    <button id="scroll-top" class="fixed bottom-6 end-6 z-50 p-3 bg-[#feee00] text-gray-950 rounded-full shadow-lg
               opacity-0 pointer-events-none transition-opacity duration-300 hover:bg-[#e5d600]">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="m18 15-6-6-6 6" />
        </svg>
    </button>

    @stack('scripts')
</body>

</html>