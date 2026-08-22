<!DOCTYPE html>
<html lang="{{ session('locale', 'ar') }}" dir="{{ session('locale', 'ar') === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('common.partner_dashboard_title')) | {{ __('common.partner_brand_title') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet" />

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


    @vite(['resources/css/app.css', 'resources/js/partner/app.js'])
    @stack('head')
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
</head>

<body class="bg-gray-100 antialiased" style="font-family: 'Cairo', sans-serif;">

    {{-- Payout hold warning banner --}}
    @if(auth()->guard('vendor')->user()?->vendor?->payout_hold_active)
        <div class="w-full bg-red-600 text-white text-sm text-center py-2 px-4 font-semibold z-50">
            {{ __('common.partner_payout_hold_banner') }}
        </div>
    @endif

    <div class="flex h-screen overflow-hidden">

        {{-- Sidebar (240px) --}}
        @include('partner.partials.sidebar')

        {{-- Main content --}}
        <div class="flex flex-col flex-1 overflow-hidden">
            @include('partner.partials.topbar')

            <main class="flex-1 overflow-y-auto p-6">
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-lg">{{ session('error') }}</div>
                @endif

                @yield('content')
            </main>
        </div>

    </div>

    @stack('scripts')
</body>

</html>