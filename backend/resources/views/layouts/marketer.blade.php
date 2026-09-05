<!DOCTYPE html>
<html lang="{{ session('locale', 'ar') }}" dir="{{ session('locale', 'ar') === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة الماركتر') | نون</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/partner/app.js'])
    @stack('head')
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-gray-100 antialiased" style="font-family: 'Cairo', sans-serif;">

    @php
        $marketerAdmin = auth()->guard('marketer')->user();
        $marketer = $marketerAdmin?->marketer;
        $pendingInvitations = $marketer
            ? \App\Models\MarketerCampaignInvitation::where('marketer_id', $marketer->id)->where('status', 'pending')->count()
            : 0;
    @endphp

    <div class="flex h-screen overflow-hidden">

        {{-- Sidebar --}}
        <aside class="w-60 shrink-0 bg-gray-800 border-e border-gray-700 flex flex-col h-full overflow-y-auto">

            {{-- Logo --}}
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-700">
                <div class="w-8 h-8 rounded-lg bg-yellow-400 flex items-center justify-center font-black text-gray-900 text-sm">M</div>
                <div>
                    <div class="text-white font-bold text-sm leading-none">بوابة الماركتر</div>
                    <div class="text-gray-400 text-xs mt-0.5">{{ $marketer?->name }}</div>
                </div>
            </div>

            {{-- Marketer type badge --}}
            @if($marketer)
            <div class="px-5 py-2 border-b border-gray-700">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold
                    {{ $marketer->isInfluencer() ? 'bg-purple-900 text-purple-200' : 'bg-blue-900 text-blue-200' }}">
                    {{ $marketer->isInfluencer() ? '🎬 مؤثر (Influencer)' : '🔗 أفيليت (Affiliate)' }}
                </span>
            </div>
            @endif

            {{-- Nav --}}
            <nav class="flex-1 px-3 py-4 space-y-1">

                <a href="{{ route('marketer.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium
                          {{ request()->routeIs('marketer.dashboard') ? 'bg-yellow-500 text-gray-900' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/></svg>
                    الإحصائيات
                </a>

                <a href="{{ route('marketer.invitations.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium
                          {{ request()->routeIs('marketer.invitations.*') ? 'bg-yellow-500 text-gray-900' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    دعوات الحملات
                    @if($pendingInvitations > 0)
                        <span class="ms-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">{{ $pendingInvitations }}</span>
                    @endif
                </a>

                <a href="{{ route('marketer.campaigns.active') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium
                          {{ request()->routeIs('marketer.campaigns.active') ? 'bg-yellow-500 text-gray-900' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    الحملات النشطة
                </a>

                <a href="{{ route('marketer.campaigns.finished') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium
                          {{ request()->routeIs('marketer.campaigns.finished') ? 'bg-yellow-500 text-gray-900' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    الحملات المنتهية
                </a>

                <a href="{{ route('marketer.samples.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium
                          {{ request()->routeIs('marketer.samples.*') ? 'bg-yellow-500 text-gray-900' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                    العينات
                </a>

                <a href="{{ route('marketer.profile') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium
                          {{ request()->routeIs('marketer.profile') ? 'bg-yellow-500 text-gray-900' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    البروفايل
                </a>

                <a href="{{ route('marketer.reports.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium
                          {{ request()->routeIs('marketer.reports.*') ? 'bg-yellow-500 text-gray-900' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0120 9.414V19a2 2 0 01-2 2z"/></svg>
                    التقارير
                </a>
            </nav>

            {{-- Logout --}}
            <div class="px-3 py-4 border-t border-gray-700">
                <form method="POST" action="{{ route('marketer.logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:bg-gray-700 hover:text-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        تسجيل الخروج
                    </button>
                </form>
            </div>

        </aside>

        {{-- Main Content --}}
        <div class="flex flex-col flex-1 overflow-hidden">

            {{-- Topbar --}}
            <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-800">@yield('page-title', 'لوحة الماركتر')</h1>
                <div class="flex items-center gap-3">
                    {{-- Pending status badge --}}
                    @if($marketer && (string)$marketer->global_status === 'pending')
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                            ⏳ حسابك قيد المراجعة
                        </span>
                    @endif
                    <span class="text-sm text-gray-500">{{ $marketerAdmin?->name }}</span>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-100 text-red-800 rounded-lg">{{ session('error') }}</div>
                @endif
                @if(session('status'))
                    <div class="mb-4 p-4 bg-blue-100 text-blue-800 rounded-lg">{{ session('status') }}</div>
                @endif

                @yield('content')
            </main>
        </div>

    </div>

    @stack('scripts')
</body>
</html>
