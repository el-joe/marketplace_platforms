@php
    use App\Services\NavigationService;
    /** @var array $navGroups */
@endphp

<aside id="sidebar"
    class="bg-gray-900 text-gray-100 flex flex-col fixed inset-y-0 {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }} z-40 md:relative md:translate-x-0 overflow-hidden">
    {{-- Logo + collapse toggle --}}
    <div class="h-16 flex items-center justify-between px-3 border-b border-gray-800 flex-shrink-0">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 min-w-0">
            <span id="sidebar-logo-icon"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary-600 text-white font-bold">M</span>
            <span id="sidebar-logo-full" class="text-base font-semibold tracking-tight truncate">{{ config('app.name') }}</span>
        </a>
        <button id="sidebar-toggle" type="button"
            class="text-gray-400 hover:text-white p-1.5 rounded-lg hover:bg-gray-800" aria-label="{{ __('admin.nav.toggle_sidebar') }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    {{-- Menu search: filters items by label (both languages) or route name; Ctrl+K or "/" focuses it --}}
    <div class="px-2 pt-3 sidebar-label" id="sidebar-search-wrap">
        <label for="sidebar-search" class="sr-only">{{ __('admin.nav.search_menu') }}</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5 text-gray-500">
                <x-heroicon name="magnifying-glass" class="w-4 h-4" />
            </span>
            <input id="sidebar-search" type="search" autocomplete="off" spellcheck="false"
                placeholder="{{ __('admin.nav.search_menu') }}"
                aria-describedby="sidebar-search-hint"
                aria-controls="sidebar-nav"
                class="w-full rounded-lg border border-gray-700 bg-gray-800 py-1.5 ps-8 pe-12 text-sm text-gray-100 placeholder-gray-500 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <kbd id="sidebar-search-hint" class="pointer-events-none absolute inset-y-0 end-0 my-1.5 me-2 hidden items-center rounded border border-gray-700 px-1.5 text-[10px] text-gray-400 md:inline-flex"
                title="{{ __('admin.nav.search_menu_hint') }}">Ctrl K</kbd>
        </div>
        <p id="sidebar-search-empty" class="hidden px-1 pt-3 text-xs text-gray-500" role="status">{{ __('admin.nav.search_menu_empty') }}</p>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-1" id="sidebar-nav">
        @foreach($navGroups as $groupIndex => $group)
            @if(!empty($group['flat']))
                @foreach($group['items'] as $item)
                    @php
                        $isActive = NavigationService::isItemActive($item, $group['items']);
                        $url = \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route'], $item['route_params'] ?? []) : '#';
                    @endphp
                    <a href="{{ $url }}" class="nav-item {{ $isActive ? 'is-active' : '' }}" title="{{ $item['label'] }}"
                        data-nav-search="{{ $item['search'] ?? $item['label'] }}">
                        <x-heroicon :name="$item['icon']" class="nav-item-icon" />
                        <span class="sidebar-label truncate flex-1">{{ $item['label'] }}</span>
                        @if(!empty($item['badge']))
                            <span class="sidebar-badge">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            @else
                @php
                    $groupIsActive = NavigationService::isGroupActive($group);
                    $groupBadgeTotal = collect($group['items'])->sum(fn($i) => (int) ($i['badge'] ?? 0));
                @endphp
                <div class="nav-group {{ $groupIsActive ? 'is-open' : '' }}" data-group-id="{{ $groupIndex }}"
                    data-nav-search="{{ $group['search'] ?? $group['group'] }}">
                    <button type="button"
                        class="nav-group-header {{ $groupIsActive ? 'is-active' : '' }}"
                        aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}"
                        title="{{ $group['group'] }}">
                        <x-heroicon :name="$group['icon']" class="nav-item-icon" />
                        <span class="sidebar-label truncate flex-1 text-start">{{ $group['group'] }}</span>
                        @if($groupBadgeTotal > 0)
                            <span class="sidebar-badge sidebar-label">{{ $groupBadgeTotal }}</span>
                        @endif
                        <svg class="nav-group-chevron sidebar-label" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div class="nav-group-items">
                        <div class="nav-group-items-inner">
                            @foreach($group['items'] as $item)
                                @php
                                    $isActive = NavigationService::isItemActive($item, $group['items']);
                                    $url = \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route'], $item['route_params'] ?? []) : '#';
                                @endphp
                                <a href="{{ $url }}" class="nav-item nav-subitem {{ $isActive ? 'is-active' : '' }}" title="{{ $item['label'] }}"
                                    data-nav-search="{{ $item['search'] ?? $item['label'] }}">
                                    <x-heroicon :name="$item['icon']" class="nav-item-icon" />
                                    <span class="sidebar-label truncate">{{ $item['label'] }}</span>
                                    @if(!empty($item['badge']))
                                        <span class="sidebar-badge">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Bottom section --}}
    <div class="border-t border-gray-800 px-2 py-3 space-y-1 flex-shrink-0">
        <a href="{{ \Illuminate\Support\Facades\Route::has('admin.settings.index') ? route('admin.settings.index') : '#' }}"
            class="nav-item {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">
            <x-heroicon name="cog-6-tooth" class="nav-item-icon" />
            <span class="sidebar-label">{{ __('common.settings') }}</span>
        </a>
        {{-- Portal Content lives in the permission-filtered NavigationService menu above (portal_content.view). --}}
        <a href="{{ \Illuminate\Support\Facades\Route::has('admin.profile.edit') ? route('admin.profile.edit') : '#' }}"
            class="nav-item">
            <x-heroicon name="user-circle" class="nav-item-icon" />
            <span class="sidebar-label">{{ __('common.profile') }}</span>
        </a>
        <form method="POST"
            action="{{ \Illuminate\Support\Facades\Route::has('admin.logout') ? route('admin.logout') : '#' }}">
            @csrf
            <button type="submit" class="nav-item w-full text-start">
                <x-heroicon name="arrow-right-on-rectangle" class="nav-item-icon" />
                <span class="sidebar-label">{{ __('common.logout') }}</span>
            </button>
        </form>
    </div>
</aside>