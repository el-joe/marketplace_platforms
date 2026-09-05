@php
    $isAr = session('locale', 'ar') === 'ar';
    $vendorAdmin = auth()->guard('vendor')->user();
    $vendor = $vendorAdmin?->vendor;
    $vendorTypeLabel = $vendor?->business_type ? __('partner.profile.business_type_' . $vendor->business_type->value) : null;
@endphp

<header class="h-16 bg-white border-b border-gray-200 flex items-center px-6 gap-4 shrink-0">

    {{-- Mobile sidebar toggle (placeholder) --}}
    <button class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
    </button>

    {{-- Page title slot --}}
    <div class="flex-1">
        <h2 class="text-sm font-bold text-gray-700">@yield('page-title', __('partner.dashboard.title'))</h2>
    </div>

    {{-- Right actions --}}
    <div class="flex items-center gap-3">

        {{-- Language switcher --}}
        <div class="flex items-center">

            {{-- Hidden forms for each locale --}}
            <form id="form-locale-ar" method="POST" action="{{ route('partner.locale.switch') }}" class="hidden">
                @csrf
                <input type="hidden" name="locale" value="ar">
            </form>
            <form id="form-locale-en" method="POST" action="{{ route('partner.locale.switch') }}" class="hidden">
                @csrf
                <input type="hidden" name="locale" value="en">
            </form>

            {{-- Pill toggle --}}
            <div class="flex items-center bg-gray-100 rounded-xl p-0.5 gap-0.5">
                <button
                    type="button"
                    onclick="document.getElementById('form-locale-ar').submit()"
                    id="lang-btn-ar"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition-all duration-200
                           {{ $isAr
                                ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200/60'
                                : 'text-gray-400 hover:text-gray-600' }}"
                >ع</button>
                <button
                    type="button"
                    onclick="document.getElementById('form-locale-en').submit()"
                    id="lang-btn-en"
                    class="px-3 py-1 rounded-lg text-xs font-bold transition-all duration-200
                           {{ !$isAr
                                ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200/60'
                                : 'text-gray-400 hover:text-gray-600' }}"
                >EN</button>
            </div>
        </div>

        {{-- Notifications --}}
        <x-notification-bell guard="vendor" />

        {{-- User dropdown --}}
        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
            <button @click="open = !open"
                class="flex items-center gap-2 py-1.5 px-3 rounded-xl hover:bg-gray-100 transition-colors">
                <div class="w-7 h-7 rounded-full bg-yellow-400/20 flex items-center justify-center
                            text-yellow-600 font-bold text-xs shrink-0">
                    {{ mb_substr($vendorAdmin?->name ?? 'V', 0, 1) }}
                </div>
                <div class="{{ $isAr ? 'text-right' : 'text-left' }} hidden sm:block">
                    <div class="text-xs font-bold text-gray-800">{{ $vendorAdmin?->name }}</div>
                    <div class="text-xs text-gray-400 capitalize">{{ $vendorTypeLabel }}</div>
                </div>
                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </button>

            <div x-show="open" x-transition class="absolute end-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded-2xl
                        shadow-xl overflow-hidden z-50 py-1">
                <a href="#" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    {{ __('partner.nav.my_profile') }}
                </a>
                <a href="#" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <circle cx="12" cy="12" r="3" />
                        <path d="M19.07 4.93l-1.41 1.41M12 2v2" />
                    </svg>
                    {{ __('partner.nav.settings') }}
                </a>
                <div class="border-t border-gray-100 my-1"></div>
                <form method="POST" action="{{ route('partner.logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 {{ $isAr ? 'flex-row-reverse text-right' : '' }}">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16,17 21,12 16,7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        {{ __('partner.nav.logout') }}
                    </button>
                </form>
            </div>
        </div>

    </div>
</header>