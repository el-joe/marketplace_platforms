<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('common.helpcenter_title'))</title>
    <meta name="description" content="@yield('description', __('common.helpcenter_title'))">
    <meta name="robots" content="index,follow">

    <link rel="icon" href="{{ asset('images/helpcenter/favicon.svg') }}">

    {{-- Figtree matches the rest of the admin/portal typography --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
        .hc-fade-header {
            background-image: radial-gradient(333.38% 100% at 50% 0%, #fff8f0 0%, #fff2e2 40%, #ffffff 100%);
        }
        .hc-table table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .hc-table th, .hc-table td { border: 1px solid #eceef1; padding: .5rem .75rem; text-align: start; vertical-align: top; }
        .hc-table th { background: #f7f8fa; font-weight: 600; }
        .hc-callout { border-radius: .5rem; padding: 1rem 1.25rem; margin: 1rem 0; }
        .hc-callout-note { background: #fff7e6; border: 1px solid #ffe4b0; }
        .hc-callout-info { background: #eef2ff; border: 1px solid #dbe3ff; }
        .hc-article-body h2 { font-size: 1.25rem; font-weight: 700; margin-top: 2rem; margin-bottom: .75rem; }
        .hc-article-body h3 { font-size: 1.1rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: .5rem; }
        .hc-article-body p { margin-bottom: .85rem; line-height: 1.75; color: #374151; }
        .hc-article-body ul, .hc-article-body ol { margin-bottom: .85rem; padding-inline-start: 1.5rem; }
        .hc-article-body li { margin-bottom: .35rem; line-height: 1.7; }
        .hc-article-body a { color: #f97316; text-decoration: underline; }
        .hc-article-body hr { border: 0; border-top: 1px solid #eceef1; margin: 1.5rem 0; }
    </style>

    @stack('head')
</head>

<body class="bg-white text-gray-900 antialiased" style="font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif;">

    <div class="flex min-h-screen flex-col">
        @yield('header')

        <div class="z-10 flex shrink-0 grow basis-auto justify-center px-4 sm:px-8">
            <section class="w-full max-w-full lg:w-[1000px]">
                @yield('content')
            </section>
        </div>

        @include('portal.partials.helpcenter-footer')
    </div>

    @stack('scripts')
</body>

</html>
