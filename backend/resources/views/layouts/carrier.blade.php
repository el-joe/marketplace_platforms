<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('carrier.panel_title')) | {{ auth('shipping_supervisor')->user()?->company?->name ?? __('carrier.panel_title') }}</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800&display=swap" rel="stylesheet">
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
<body class="bg-gray-100 min-h-screen" style="font-family: 'Cairo', sans-serif;">

    {{-- Top Nav --}}
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
        <style>[x-cloak] { display: none !important; }</style>
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="bg-indigo-600 text-white font-black text-lg px-2.5 py-1 rounded">🚚</span>
                <div class="leading-tight">
                    <div class="font-bold text-gray-900 text-sm">{{ auth('shipping_supervisor')->user()?->company?->name }}</div>
                    <div class="text-xs text-gray-500">{{ auth('shipping_supervisor')->user()?->name }}</div>
                </div>
            </div>

            <div class="flex items-center gap-4 text-sm font-medium">
                <x-notification-bell guard="shipping_supervisor" />
                <a href="{{ route('carrier.dashboard') }}"
                   class="text-gray-600 hover:text-indigo-600 transition {{ request()->routeIs('carrier.dashboard') ? 'text-indigo-600 font-bold' : '' }}">
                    {{ __('carrier.nav.home') }}
                </a>
                <a href="{{ route('carrier.agents.index') }}"
                   class="text-gray-600 hover:text-indigo-600 transition {{ request()->routeIs('carrier.agents.*') ? 'text-indigo-600 font-bold' : '' }}">
                    {{ __('carrier.nav.agents') }}
                </a>
                @if(auth('shipping_supervisor')->user()?->hasPermission('view_orders'))
                <a href="{{ route('carrier.assignments.unassigned') }}"
                   class="text-gray-600 hover:text-indigo-600 transition {{ request()->routeIs('carrier.assignments.unassigned') ? 'text-indigo-600 font-bold' : '' }}">
                    {{ __('carrier.nav.unassigned') }}
                </a>
                <a href="{{ route('carrier.assignments.index') }}"
                   class="text-gray-600 hover:text-indigo-600 transition {{ request()->routeIs('carrier.assignments.index') || request()->routeIs('carrier.assignments.show') ? 'text-indigo-600 font-bold' : '' }}">
                    {{ __('carrier.nav.assignments') }}
                </a>
                @endif
                @if(auth('shipping_supervisor')->user()?->hasPermission('manage_agents'))
                <a href="{{ route('carrier.supervisors.index') }}"
                   class="text-gray-600 hover:text-indigo-600 transition {{ request()->routeIs('carrier.supervisors.*') ? 'text-indigo-600 font-bold' : '' }}">
                    {{ __('carrier.nav.supervisors') }}
                </a>
                @endif
                @if(auth('shipping_supervisor')->user()?->hasPermission('view_reports'))
                <a href="{{ route('carrier.reports.index') }}"
                   class="text-gray-600 hover:text-indigo-600 transition {{ request()->routeIs('carrier.reports.*') ? 'text-indigo-600 font-bold' : '' }}">
                    {{ __('carrier.nav.reports') }}
                </a>
                @endif

                {{-- Language switcher (AR / EN) --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-sm font-medium
                               text-gray-700 hover:bg-gray-100 border border-gray-200 transition-colors">
                        {{-- Globe icon --}}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10
                                   5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        <span>{{ strtoupper(app()->getLocale()) }}</span>
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute end-0 mt-2 w-44 bg-white rounded-lg shadow-lg border border-gray-200 z-50 py-1">
                        {{-- English --}}
                        <form method="POST" action="{{ route('carrier.locale.switch') }}">
                            @csrf
                            <input type="hidden" name="locale" value="en">
                            <button type="submit"
                                class="w-full text-start px-3 py-2 text-sm text-gray-700 hover:bg-gray-50
                                       flex items-center justify-between">
                                <span>{{ __('carrier.nav.lang_en') }}</span>
                                @if(app()->getLocale() === 'en')
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </button>
                        </form>
                        {{-- Arabic --}}
                        <form method="POST" action="{{ route('carrier.locale.switch') }}">
                            @csrf
                            <input type="hidden" name="locale" value="ar">
                            <button type="submit"
                                class="w-full text-start px-3 py-2 text-sm text-gray-700 hover:bg-gray-50
                                       flex items-center justify-between">
                                <span>{{ __('carrier.nav.lang_ar') }}</span>
                                @if(app()->getLocale() === 'ar')
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('carrier.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-red-500 hover:text-red-700 transition font-medium">{{ __('carrier.nav.logout') }}</button>
                </form>
            </div>
        </div>
    </nav>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="bg-emerald-50 border-b border-emerald-200 px-6 py-3 text-sm text-emerald-700 text-center font-medium">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error') || $errors->any())
    <div class="bg-red-50 border-b border-red-200 px-6 py-3 text-sm text-red-700 text-center font-medium">
        {{ session('error') ?? $errors->first() }}
    </div>
    @endif

    <main class="max-w-7xl mx-auto px-6 py-8">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
