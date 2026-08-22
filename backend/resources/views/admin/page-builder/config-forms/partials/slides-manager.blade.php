{{-- Slides manager (AJAX-loaded inside hero_slider config) --}}
@php /** @var \App\Models\PageBlock $block */ @endphp

<section class="pt-4 mt-4 border-t border-gray-200">
    <div class="flex items-center justify-between mb-2">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.page_builder.config_forms.slides_manager.title') }}</h4>
        <button type="button"
                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 rounded"
                data-action="add-slide"
                data-block-id="{{ $block?->id }}">
            <x-heroicon name="plus" class="w-3.5 h-3.5" /> {{ __('admin.page_builder.config_forms.slides_manager.add_slide') }}
        </button>
    </div>

    <div data-slides-list
         data-block-id="{{ $block?->id }}"
         class="space-y-1 text-sm text-gray-500">
        <div class="text-xs text-gray-400 px-2 py-3 text-center">{{ __('admin.page_builder.config_forms.slides_manager.loading_slides') }}</div>
    </div>
</section>
