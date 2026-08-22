@php
    /** @var array $breadcrumbs */
    $user = auth('admin')->user() ?? auth()->user();
@endphp

<header id="topbar" class="bg-white border-b border-gray-200 px-4 lg:px-6 flex items-center gap-4">
    {{-- Mobile hamburger --}}
    <button id="mobile-menu-btn" type="button" class="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100"
        aria-label="{{ __('admin.nav.open_menu') }}">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    {{-- Breadcrumb --}}
    <nav aria-label="{{ __('common.breadcrumb') }}" class="hidden sm:flex items-center text-sm min-w-0">
        <ol class="flex items-center gap-1 text-gray-500 truncate">
            <li>
                <a href="{{ \Illuminate\Support\Facades\Route::has('admin.dashboard') ? route('admin.dashboard') : '/' }}"
                    class="hover:text-gray-700 inline-flex items-center">
                    <x-heroicon name="home" class="w-4 h-4" />
                </a>
            </li>
            @foreach($breadcrumbs as $i => $crumb)
                <li class="inline-flex items-center">
                    <svg class="w-4 h-4 mx-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    @if($i === count($breadcrumbs) - 1 || empty($crumb['url']))
                        <span class="text-gray-900 font-medium">{{ $crumb['label'] }}</span>
                    @else
                        <a href="{{ $crumb['url'] }}" class="hover:text-gray-700">{{ $crumb['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="{{ app()->getLocale() == 'ar' ? 'mr' : 'ml' }}-auto flex items-center gap-2">
        {{-- Global search trigger --}}
        {{--<button type="button" id="global-search-btn" class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg
                       border border-gray-200 text-sm text-gray-500 hover:bg-gray-50">
            <x-heroicon name="magnifying-glass" class="w-4 h-4" />
            <span>Search…</span>
            <kbd class="ml-2 px-1.5 py-0.5 text-[10px] bg-gray-100 rounded border border-gray-200">⌘K</kbd>
        </button>--}}

        {{-- Notifications --}}
        <x-notification-bell guard="admin" />

        {{-- Country selector --}}
        {{-- <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="hidden md:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg
                           text-sm text-gray-700 hover:bg-gray-100">
                <x-heroicon name="globe-alt" class="w-4 h-4" />
                <span>{{ session('admin_country', 'EG') }}</span>
                <x-heroicon name="chevron-down" class="w-4 h-4" />
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak
                class="absolute right-0 mt-2 w-44 bg-white rounded-lg shadow-lg border border-gray-200 z-50 py-1">
                @foreach(['EG' => 'Egypt', 'SA' => 'Saudi Arabia', 'AE' => 'UAE'] as $code => $name)
                <button type="button" data-country="{{ $code }}"
                    class="country-switch w-full text-start px-3 py-1.5 text-sm hover:bg-gray-50">
                    {{ $name }} <span class="text-gray-400 text-xs">({{ $code }})</span>
                </button>
                @endforeach
            </div>
        </div>--}}

        {{-- Language / direction switcher --}}
        <div class="relative" x-data="{ open: false }" @dropdown-opened.window="if ($event.detail?.source !== $el) open = false">
            <button type="button" @click="open = !open; if (open) $dispatch('dropdown-opened', { source: $event.currentTarget.closest('[x-data]') })"
                class="inline-flex items-center gap-1.5 sm:gap-2 px-2 sm:px-3 py-1.5 sm:py-2 rounded-xl text-sm font-medium
                       text-gray-700 bg-white border border-gray-200 shadow-sm hover:shadow hover:bg-gray-50 hover:border-gray-300
                       transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                :class="{ 'bg-gray-50 shadow-md ring-2 ring-primary-500/20 border-gray-300': open }">
                <span class="text-base sm:text-lg leading-none drop-shadow-sm">{{ app()->getLocale() === 'en' ? '🇺🇸' : '🇸🇦' }}</span>
                <span class="hidden sm:inline">{{ app()->getLocale() === 'en' ? 'English' : 'العربية' }}</span>
                <x-heroicon name="chevron-down" class="w-3.5 h-3.5 text-gray-400 transition-transform duration-300" x-bind:class="open ? 'rotate-180' : ''" />
            </button>

            <div x-show="open" @click.outside="open = false" x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                class="absolute end-0 mt-2.5 w-48 bg-white/95 backdrop-blur-xl rounded-2xl shadow-xl border border-gray-100 z-50 p-1.5 ring-1 ring-black/5">

                <form method="POST" action="{{ url('/locale/switch') }}">
                    @csrf
                    <input type="hidden" name="locale" value="en">
                    <button type="submit" class="w-full group text-start px-3 py-2.5 rounded-xl text-sm transition-all duration-200
                               flex items-center justify-between {{ app()->getLocale() === 'en' ? 'bg-primary-50/80 text-primary-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg leading-none drop-shadow-sm">🇺🇸</span>
                            <span class="{{ app()->getLocale() === 'en' ? 'font-semibold' : 'font-medium' }}">English</span>
                        </div>
                        @if(app()->getLocale() === 'en')
                            <div class="flex-shrink-0 w-5 h-5 rounded-full bg-primary-600 text-white flex items-center justify-center shadow-sm">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        @else
                            <div class="flex-shrink-0 w-5 h-5 rounded-full border border-gray-300 group-hover:border-gray-400 transition-colors"></div>
                        @endif
                    </button>
                </form>

                <form method="POST" action="{{ url('/locale/switch') }}" class="mt-1">
                    @csrf
                    <input type="hidden" name="locale" value="ar">
                    <button type="submit" class="w-full group text-start px-3 py-2.5 rounded-xl text-sm transition-all duration-200
                               flex items-center justify-between {{ app()->getLocale() === 'ar' ? 'bg-primary-50/80 text-primary-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg leading-none drop-shadow-sm">🇸🇦</span>
                            <span class="font-cairo {{ app()->getLocale() === 'ar' ? 'font-semibold' : 'font-medium' }}">العربية</span>
                        </div>
                        @if(app()->getLocale() === 'ar')
                            <div class="flex-shrink-0 w-5 h-5 rounded-full bg-primary-600 text-white flex items-center justify-center shadow-sm">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        @else
                            <div class="flex-shrink-0 w-5 h-5 rounded-full border border-gray-300 group-hover:border-gray-400 transition-colors"></div>
                        @endif
                    </button>
                </form>
            </div>
        </div>

        {{-- User dropdown --}}
        <div class="relative" x-data="{ open: false }" @dropdown-opened.window="if ($event.detail?.source !== $el) open = false">
            <button type="button" @click="open = !open; if (open) $dispatch('dropdown-opened', { source: $event.currentTarget.closest('[x-data]') })"
                class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-gray-100">
                @if($user && $user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                        class="w-8 h-8 rounded-full object-cover bg-white border border-gray-200">
                @else
                    <div
                        class="w-8 h-8 rounded-full bg-primary-600 text-white inline-flex items-center justify-center text-sm font-semibold">
                        {{ strtoupper(mb_substr($user?->name ?? 'A', 0, 1)) }}
                    </div>
                @endif
                <span
                    class="hidden lg:inline text-sm text-gray-700 truncate max-w-[140px]">{{ $user?->name ?? 'Admin' }}</span>
                <x-heroicon name="chevron-down" class="w-4 h-4 text-gray-400" />
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak
                class="absolute end-0 mt-2 w-52 bg-white rounded-lg shadow-lg border border-gray-200 z-50 py-1">
                <div class="px-3 py-2 border-b border-gray-100">
                    <div class="text-sm font-medium text-gray-900 truncate">{{ $user?->name ?? 'Admin' }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ $user?->email ?? '' }}</div>
                </div>
                <a href="{{ \Illuminate\Support\Facades\Route::has('admin.profile.edit') ? route('admin.profile.edit') : '#' }}"
                    class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('common.profile') }}</a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('vendor.dashboard') ? route('vendor.dashboard') : '#' }}"
                    class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('admin.switch_to_vendor') }}</a>
                <div class="border-t border-gray-100 my-1"></div>
                <form method="POST"
                    action="{{ \Illuminate\Support\Facades\Route::has('admin.logout') ? route('admin.logout') : '#' }}">
                    @csrf
                    <button type="submit"
                        class="w-full text-start px-3 py-2 text-sm text-danger-600 hover:bg-danger-50">
                        {{ __('common.logout') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
