<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('common.adsupport_home_title'))</title>
    <meta name="description" content="@yield('description', __('common.adsupport_tagline'))">
    <meta name="robots" content="index,follow">

    <link rel="icon" href="https://intercom.help/noon-adsupport/assets/favicon">
    <link rel="apple-touch-icon" href="https://intercom.help/noon-adsupport/assets/favicon">

    {{-- Inter — same typeface noon's Knowledge Hub uses --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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

    <style>
        .adsupport-fade-header {
            background-image: radial-gradient(333.38% 100% at 50% 0%,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, .00925356) 11.67%,
                rgba(255, 255, 255, .0337355) 21.17%,
                rgba(255, 255, 255, .0718242) 28.85%,
                rgba(255, 255, 255, .121898) 35.03%,
                rgba(255, 255, 255, .182336) 40.05%,
                rgba(255, 255, 255, .251516) 44.25%,
                rgba(255, 255, 255, .327818) 47.96%,
                rgba(255, 255, 255, .409618) 51.51%,
                rgba(255, 255, 255, .495297) 55.23%,
                rgba(255, 255, 255, .583232) 59.47%,
                rgba(255, 255, 255, .671801) 64.55%,
                rgba(255, 255, 255, .759385) 70.81%,
                rgba(255, 255, 255, .84436) 78.58%,
                rgba(255, 255, 255, .9551) 88.2%,
                rgba(255, 255, 255, 1) 100%),
                url(https://downloads.intercomcdn.com/i/o/yba8j1xj/658728/3437377f61322d3a669607ae399d/e0dac85e46853ea09592337a020576a3.png);
            background-size: cover;
            background-position-x: center;
        }
    </style>

    @stack('head')
</head>

<body class="bg-white text-black antialiased font-inter" style="font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;">

    <div class="flex min-h-screen flex-col">
        @yield('header')

        <div class="z-1 flex shrink-0 grow basis-auto justify-center px-5 sm:px-10">
            <section class="w-full max-w-full lg:w-[960px]">
                @yield('content')
            </section>
        </div>

        @include('portal.partials.adsupport-footer')
    </div>

    @stack('scripts')
</body>

</html>
