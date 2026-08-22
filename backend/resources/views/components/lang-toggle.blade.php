{{--
    Lang toggle — inline form POST to /locale/switch.
    Drop <x-lang-toggle /> anywhere in a navbar.
    Optional: pass :dark="true" for light-text variant (dark backgrounds).
--}}
@props(['dark' => false])

@php
    $current = app()->getLocale();
    $next    = $current === 'ar' ? 'en' : 'ar';
    $label   = $current === 'ar' ? 'English' : 'العربية';
    $btnClass = $dark
        ? 'flex items-center gap-1.5 text-sm font-medium text-gray-300 hover:text-white transition-colors px-3 py-1.5 rounded-lg hover:bg-white/10'
        : 'flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors px-3 py-1.5 rounded-lg hover:bg-gray-100';
@endphp

<form method="POST" action="{{ url('/locale/switch') }}" class="inline">
    @csrf
    <input type="hidden" name="locale" value="{{ $next }}">
    <button type="submit" class="{{ $btnClass }}">
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502"/>
        </svg>
        {{ $label }}
    </button>
</form>
