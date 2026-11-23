{{-- Header of one category-form section; the error line is filled by categories.js after a failed save. --}}
<header class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
    <h2 class="flex items-center gap-2 text-base font-semibold text-gray-900">
        <x-heroicon name="{{ $icon }}" class="h-5 w-5 text-gray-400" />
        {{ $title }}
    </h2>
    <span data-section-error-summary class="hidden text-xs font-medium text-danger-600"></span>
</header>
<div data-section-errors class="mx-6 mt-4 hidden rounded-lg border border-danger-200 bg-danger-50 p-3 text-xs text-danger-700"></div>
