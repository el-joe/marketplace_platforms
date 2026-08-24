{{-- Per-tab manual product picker for mega_deals — AJAX-loaded product list scoped by tab_index --}}
@php /** @var \App\Models\PageBlock|null $block */ @endphp

<section class="pt-3 mt-1 border-t border-gray-200" data-mega-tab-products data-tab-index="{{ $tabIndex }}" data-block-id="{{ $block?->id }}">
    <div class="relative mb-2">
        <input type="search"
            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:outline-none focus:ring-2 focus:ring-primary-500"
            placeholder="Search products…" data-action="search-mega-tab-products" data-tab-index="{{ $tabIndex }}" data-block-id="{{ $block?->id }}" />
        <svg class="w-4 h-4 absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none"
            stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
        </svg>
    </div>

    <div data-mega-tab-product-search-results data-tab-index="{{ $tabIndex }}" data-block-id="{{ $block?->id }}"
        class="hidden mb-2 rounded-lg border border-gray-200 bg-white shadow-sm text-sm divide-y divide-gray-100 max-h-48 overflow-y-auto">
    </div>

    <div data-mega-tab-products-list data-tab-index="{{ $tabIndex }}" data-block-id="{{ $block?->id }}" class="space-y-1 text-sm text-gray-500">
        <div class="text-xs text-gray-400 px-2 py-3 text-center">Loading products…</div>
    </div>
</section>
