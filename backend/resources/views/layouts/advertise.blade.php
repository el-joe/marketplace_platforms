<!DOCTYPE html>
<html lang="{{ session('locale', 'ar') }}" dir="{{ session('locale', 'ar') === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('common.advertise_title'))</title>
    <meta name="description" content="@yield('description', __('common.advertise_description'))">
    <meta name="robots" content="index,follow">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
    {{-- Cairo for Arabic --}}
    <link href="https://fonts.bunny.net/css?family=cairo:200,300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

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

<body class="bg-white text-gray-900 antialiased" style="font-family: 'Figtree', 'Cairo', sans-serif;">

    @include('portal.partials.advertise-nav')

    <main>
        @yield('content')
    </main>

    @include('portal.partials.advertise-footer')

    @stack('scripts')
</body>

</html>
