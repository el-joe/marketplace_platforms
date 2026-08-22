@php
    /**
     * Notification bell component — include in every panel layout.
     *
     * Usage:  <x-notification-bell guard="admin" />
     *         <x-notification-bell guard="vendor" />
     *         <x-notification-bell guard="marketer" />
     *         <x-notification-bell guard="shipping_supervisor" />
     *         <x-notification-bell guard="travel_agency" />
     *         <x-notification-bell guard="delivery" />
     *
     * Requires window.notificationBell() to be registered (via shared/echo-setup.js).
     * Requires Alpine.js to be loaded on the page.
     */

    $user = auth($guard)->user();
    if (!$user) {
        // Not authenticated — render nothing
        return;
    }

    $channelMap = [
        'admin'                => 'admin',
        'vendor'               => 'vendor',
        'marketer'             => 'marketer',
        'shipping_supervisor'  => 'carrier-supervisor',
        'travel_agency'        => 'travel-agency',
        'delivery'             => 'delivery-agent',
    ];

    $echoChannel = ($channelMap[$guard] ?? $guard) . '.' . $user->getKey();

    // Route name prefix to build "View all" link
    $routePrefixMap = [
        'admin'                => 'admin',
        'vendor'               => 'partner',
        'marketer'             => 'marketer',
        'shipping_supervisor'  => 'carrier',
        'travel_agency'        => 'travel-agency',
        'delivery'             => 'delivery',
    ];
    $routePrefix = $routePrefixMap[$guard] ?? $guard;
    $indexRouteName = $routePrefix . '.notifications.index';
    $viewAllUrl = \Illuminate\Support\Facades\Route::has($indexRouteName)
        ? route($indexRouteName)
        : '/notifications';
@endphp

<div x-data="notificationBell('{{ $echoChannel }}')" class="relative" @dropdown-opened.window="if ($event.detail?.source !== $el) open = false">
    {{-- Bell button --}}
    <button type="button"
        @click="open = !open; if (open && !loaded) fetchRecent(); if (open) $dispatch('dropdown-opened', { source: $event.currentTarget.closest('[x-data]') })"
        class="relative p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        {{-- Unread badge --}}
        <span x-show="unreadCount > 0"
            x-text="unreadCount > 99 ? '99+' : unreadCount"
            class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center
                   min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold leading-none">
        </span>
    </button>

    {{-- Dropdown --}}
    <div x-show="open"
        @click.outside="open = false"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute end-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50">

        {{-- Header --}}
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-900">{{ __('common.notifications') }}</h4>
            <button type="button"
                @click="markAllRead()"
                class="text-xs text-blue-600 hover:underline">
                {{ __('common.mark_all_read') }}
            </button>
        </div>

        {{-- List --}}
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            <template x-if="!loaded">
                <div class="p-6 text-center text-sm text-gray-500">{{ __('common.loading') }}</div>
            </template>
            <template x-if="loaded && items.length === 0">
                <div class="p-6 text-center text-sm text-gray-500">{{ __('common.no_notifications_yet') }}</div>
            </template>
            <template x-for="item in items" :key="item.id">
                <button type="button"
                    @click="markRead(item.id, item.url)"
                    class="w-full text-start px-4 py-3 hover:bg-gray-50 transition-colors block"
                    :class="item.read ? '' : 'bg-blue-50'">
                    <div class="flex items-start gap-2">
                        <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 transition-colors"
                            :class="item.read ? 'bg-gray-200' : 'bg-blue-500'"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate" x-text="item.title"></p>
                            <p x-show="item.message" class="text-xs text-gray-500 mt-0.5 line-clamp-2"
                                x-text="item.message"></p>
                            <p class="text-[10px] text-gray-400 mt-1" x-text="item.created_at"></p>
                        </div>
                    </div>
                </button>
            </template>
        </div>

        {{-- Footer --}}
        <div class="px-4 py-2 border-t border-gray-100 text-center">
            <a href="{{ $viewAllUrl }}" class="text-xs text-blue-600 hover:underline">{{ __('common.view_all_notifications') }}</a>
        </div>
    </div>
</div>
