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

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-1" id="sidebar-nav">
        @foreach($navGroups as $groupIndex => $group)
            @if(!empty($group['flat']))
                @foreach($group['items'] as $item)
                    @php
                        $isActive = NavigationService::isActive($item['route']);
                        $url = \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#';
                    @endphp
                    <a href="{{ $url }}" class="nav-item {{ $isActive ? 'is-active' : '' }}" title="{{ $item['label'] }}">
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
                <div class="nav-group {{ $groupIsActive ? 'is-open' : '' }}" data-group-id="{{ $groupIndex }}">
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
                                    $isActive = NavigationService::isActive($item['route']);
                                    $url = \Illuminate\Support\Facades\Route::has($item['route']) ? route($item['route']) : '#';
                                @endphp
                                <a href="{{ $url }}" class="nav-item nav-subitem {{ $isActive ? 'is-active' : '' }}" title="{{ $item['label'] }}">
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
        <a href="{{ \Illuminate\Support\Facades\Route::has('admin.portal-content.index') ? route('admin.portal-content.index') : '#' }}"
            class="nav-item {{ request()->routeIs('admin.portal-content.*') ? 'is-active' : '' }}">
            <x-heroicon name="document-text" class="nav-item-icon" />
            <span class="sidebar-label">{{ __('common.portal_content') }}</span>
        </a>
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