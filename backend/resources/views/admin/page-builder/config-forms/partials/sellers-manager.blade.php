{{-- Sellers manager (AJAX-loaded inside brand_strip) --}}
@php /** @var \App\Models\PageBlock $block */ @endphp

<section class="pt-4 mt-4 border-t border-gray-200">
    <div class="flex items-center justify-between mb-2">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.page_builder.config_forms.brand_strip.vendors') }}</h4>
    </div>

    <div class="relative mb-3">
        <input type="search"
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="{{ __('admin.page_builder.config_forms.brand_strip.add_vendor') }}" data-action="search-block-sellers" data-block-id="{{ $block?->id }}" />
        <svg class="w-4 h-4 absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none"
            stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
        </svg>
    </div>

    <div data-block-seller-search-results data-block-id="{{ $block?->id }}"
        class="hidden mb-2 rounded-lg border border-gray-200 bg-white shadow-sm text-sm divide-y divide-gray-100 max-h-48 overflow-y-auto">
    </div>

    <div data-block-sellers-list data-block-id="{{ $block?->id }}" class="space-y-1 text-sm text-gray-500">
        <div class="text-xs text-gray-400 px-2 py-3 text-center">…</div>
    </div>
</section>
