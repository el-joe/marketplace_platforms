<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('common.travel_agency_portal_title')) | {{ __('common.travel_agency_portal_title') }}</title>
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
    <style>
        [x-cloak] { display: none !important; }

        #ta-sidebar {
            width: 240px;
            background: #0f172a;
            color: #f1f5f9;
            min-height: 100vh;
            position: fixed;
            top: 0;
            inset-inline-start: 0;
            display: flex;
            flex-direction: column;
            z-index: 40;
        }

        #ta-sidebar .brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        #ta-sidebar .brand .brand-icon {
            background: #3b82f6;
            color: #fff;
            font-weight: 900;
            font-size: 1.1rem;
            padding: 0.35rem 0.6rem;
            border-radius: 8px;
        }

        #ta-sidebar .brand span.brand-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        #ta-sidebar nav {
            flex: 1;
            padding: 1rem 0;
            overflow-y: auto;
        }

        #ta-sidebar nav a {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 1.5rem;
            font-size: 0.875rem;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.15s;
            position: relative;
        }

        #ta-sidebar nav a:hover,
        #ta-sidebar nav a.active {
            color: #60a5fa;
            background: rgba(59, 130, 246, 0.1);
        }

        #ta-sidebar nav a svg {
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
        }

        #ta-sidebar .nav-badge {
            position: absolute;
            inset-inline-end: 1.25rem;
            background: #f59e0b;
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            min-width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 3px;
            line-height: 1;
        }

        #ta-sidebar .nav-badge.rose {
            background: #f43f5e;
        }

        #ta-sidebar .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.75rem;
        }

        #ta-content {
            margin-inline-start: 240px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        #ta-topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        #ta-main {
            flex: 1;
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            #ta-sidebar {
                transform: translateX(-100%);
                transition: transform 0.25s;
            }

            html[dir="rtl"] #ta-sidebar {
                transform: translateX(100%);
            }

            #ta-sidebar.open {
                transform: translateX(0);
            }

            #ta-content {
                margin-inline-start: 0;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-100" style="font-family: 'Cairo', sans-serif;">

    {{-- Sidebar --}}
    <aside id="ta-sidebar">
        <div class="brand">
            <span class="brand-icon">✈</span>
            <span class="brand-name">{{ auth()->guard('travel_agency')->user()->name }}</span>
        </div>

        <nav>
            <a href="{{ route('travel-agency.dashboard') }}"
                class="{{ request()->routeIs('travel-agency.dashboard') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>
                {{ __('travel.nav.dashboard') }}
            </a>

            <a href="{{ route('travel-agency.packages.index') }}"
                class="{{ request()->routeIs('travel-agency.packages.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
                {{ __('travel.nav.packages') }}
            </a>

            <a href="{{ route('travel-agency.bookings.index') }}"
                class="{{ request()->routeIs('travel-agency.bookings.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="4" width="18" height="17" rx="2" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4M16 2v4M3 10h18" />
                </svg>
                {{ __('travel.nav.bookings') }}
                @if(($pendingBookingsCount ?? 0) > 0)
                    <span class="nav-badge">{{ $pendingBookingsCount > 99 ? '99+' : $pendingBookingsCount }}</span>
                @endif
            </a>

            <a href="{{ route('travel-agency.inquiries.index') }}"
                class="{{ request()->routeIs('travel-agency.inquiries.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                {{ __('travel.inquiries.interested_clients') }}
                @if(($newInquiriesCount ?? 0) > 0)
                    <span class="nav-badge rose">{{ $newInquiriesCount > 99 ? '99+' : $newInquiriesCount }}</span>
                @endif
            </a>

            <a href="{{ route('travel-agency.campaigns.index') }}"
                class="{{ request()->routeIs('travel-agency.campaigns.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18a23.848 23.848 0 0 0 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535" />
                </svg>
                {{ __('travel.nav.campaigns') }}
            </a>

            @if (auth()->guard('travel_agency')->user()?->can('reports.view'))
                <a href="{{ route('travel-agency.reports.revenue') }}"
                    class="{{ request()->routeIs('travel-agency.reports.revenue') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l4-5 3 3 5-7" />
                    </svg>
                    {{ __('travel.nav.reports_revenue') }}
                </a>

                <a href="{{ route('travel-agency.reports.bookings') }}"
                    class="{{ request()->routeIs('travel-agency.reports.bookings') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V9m3 8V5m3 12v-4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    {{ __('travel.nav.reports_bookings') }}
                </a>

                <a href="{{ route('travel-agency.reports.packages') }}"
                    class="{{ request()->routeIs('travel-agency.reports.packages') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                    {{ __('travel.nav.reports_packages') }}
                </a>

                <a href="{{ route('travel-agency.performance.index') }}"
                    class="{{ request()->routeIs('travel-agency.performance.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 16l3-3 3 3 5-6" />
                        <circle cx="18" cy="10" r="1.5" />
                    </svg>
                    {{ __('travel.nav.performance') }}
                </a>
            @endif

            @if (auth()->guard('travel_agency')->user()?->can('support.view'))
                <a href="{{ route('travel-agency.support.index') }}"
                    class="{{ request()->routeIs('travel-agency.support.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18 10a6 6 0 00-12 0v4a2 2 0 002 2h1v-6H6v6a2 2 0 002 2h8a2 2 0 002-2v-6h-3v6h1a2 2 0 002-2v-4z" />
                    </svg>
                    {{ __('travel.nav.support') }}
                    @if(($openTicketsCount ?? 0) > 0)
                        <span class="nav-badge">{{ $openTicketsCount > 99 ? '99+' : $openTicketsCount }}</span>
                    @endif
                </a>
            @endif

            @if (auth()->guard('travel_agency')->user()?->can('reports.view'))
                <a href="{{ route('travel-agency.finance.revenue') }}"
                    class="{{ request()->routeIs('travel-agency.finance.revenue') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-6a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('travel.nav.finance_revenue') }}
                </a>

                <a href="{{ route('travel-agency.finance.payouts') }}"
                    class="{{ request()->routeIs('travel-agency.finance.payouts') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a4 4 0 00-8 0v2M5 9h14l1 11H4L5 9z" />
                    </svg>
                    {{ __('travel.nav.finance_payouts') }}
                </a>

                <a href="{{ route('travel-agency.finance.sales-report') }}"
                    class="{{ request()->routeIs('travel-agency.finance.sales-report') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V9m3 8V5m3 12v-4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    {{ __('travel.nav.finance_sales_report') }}
                </a>
            @endif

            @if (auth()->guard('travel_agency')->user()?->can('bank_accounts.view'))
                <a href="{{ route('travel-agency.finance.wallet') }}"
                    class="{{ request()->routeIs('travel-agency.finance.wallet') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12V7H5a2 2 0 010-4h14v4M3 5v14a2 2 0 002 2h16v-5M18 12a2 2 0 000 4h4v-4h-4z" />
                    </svg>
                    {{ __('travel.nav.finance_wallet') }}
                </a>
            @endif

            <a href="{{ route('travel-agency.profile.edit') }}"
                class="{{ request()->routeIs('travel-agency.profile.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ __('travel.nav.profile') }}
            </a>

            @if (auth()->guard('travel_agency')->user()?->can('bank_accounts.view'))
                <a href="{{ route('travel-agency.bank-accounts.index') }}"
                    class="hidden {{ request()->routeIs('travel-agency.bank-accounts.*') || request()->routeIs('travel-agency.change-requests.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 6l9-3 9 3M3 6v14l9 3 9-3V6M3 6l9 3 9-3"/>
                    </svg>
                    {{ __('travel.nav.bank_accounts') }}
                </a>
            @endif

            @if (auth()->guard('travel_agency')->user()?->isOwner())
                <a href="{{ route('travel-agency.team.index') }}"
                    class="{{ request()->routeIs('travel-agency.team.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" />
                    </svg>
                    {{ __('travel.nav.team') }}
                </a>

                <a href="{{ route('travel-agency.roles.index') }}"
                    class="{{ request()->routeIs('travel-agency.roles.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" />
                    </svg>
                    {{ __('travel.nav.roles') }}
                </a>
            @endif
        </nav>

        <div class="sidebar-footer">
            <form method="POST" action="{{ route('travel-agency.logout') }}">
                @csrf
                <button type="submit"
                    style="color:#f87171;font-size:0.75rem;background:none;border:none;cursor:pointer;padding:0;">
                    {{ __('travel.nav.logout') }}
                </button>
            </form>
        </div>
    </aside>

    {{-- Content --}}
    <div id="ta-content">

        <header id="ta-topbar">
            <div class="flex items-center gap-3">
                {{-- Mobile hamburger --}}
                <button class="md:hidden p-1" onclick="toggleMobileSidebar()" aria-label="{{ __('travel.nav.toggle_menu') }}">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h2 class="text-sm font-semibold text-gray-800">@yield('page-title', __('travel.nav.dashboard'))</h2>
            </div>

            <div class="flex items-center gap-3">
                <x-notification-bell guard="travel_agency" />

                {{-- Language Switcher (AR / EN) --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold
                               text-gray-600 hover:text-blue-600 hover:bg-blue-50 border border-gray-200
                               transition-colors duration-150">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10
                                   5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        <span>{{ strtoupper(app()->getLocale()) }}</span>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute end-0 mt-2 w-36 bg-white rounded-xl shadow-lg border border-gray-100 z-50 py-1 overflow-hidden">
                        <form method="POST" action="{{ route('travel-agency.locale.switch') }}">
                            @csrf
                            <input type="hidden" name="locale" value="en">
                            <button type="submit"
                                class="w-full text-start px-3 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600
                                       flex items-center justify-between transition-colors">
                                <span>{{ __('travel.nav.lang_en') }}</span>
                                @if(app()->getLocale() === 'en')
                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </button>
                        </form>
                        <form method="POST" action="{{ route('travel-agency.locale.switch') }}">
                            @csrf
                            <input type="hidden" name="locale" value="ar">
                            <button type="submit"
                                class="w-full text-start px-3 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600
                                       flex items-center justify-between transition-colors">
                                <span>{{ __('travel.nav.lang_ar') }}</span>
                                @if(app()->getLocale() === 'ar')
                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="ta-main">
            @if(session('success'))
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">
                {{ session('success') }}
            </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        function toggleMobileSidebar() {
            document.getElementById('ta-sidebar').classList.toggle('open');
        }
        document.addEventListener('click', function (e) {
            var sidebar = document.getElementById('ta-sidebar');
            var hamburger = e.target.closest('[aria-label="{{ __('travel.nav.toggle_menu') }}"]');
            if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !hamburger) {
                sidebar.classList.remove('open');
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
