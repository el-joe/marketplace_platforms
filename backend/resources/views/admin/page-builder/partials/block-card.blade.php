@php
    /** @var \App\Models\PageBlock $block */
    $icon = optional($block->blockType)->icon ?? 'cube';
    $label = optional($block->blockType)->label_en ?? $block->block_type;
@endphp

<div class="block-card group" data-block-id="{{ $block->id }}" data-block-type="{{ $block->block_type }}">
    <div class="drag-handle" title="{{ __('admin.page_builder.drag_to_reorder') }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </div>

    <div class="block-icon flex-shrink-0">
        <x-heroicon :name="$icon" class="w-5 h-5" />
    </div>

    <div class="flex-1 min-w-0">
        <div class="text-sm font-medium text-gray-900 truncate">{{ $label }}</div>
        <div class="text-xs text-gray-500 truncate" data-preview>{{ $block->getPreviewText() }}</div>
    </div>

    <div class="flex items-center gap-1.5">
        @if(! $block->is_visible)
            <x-badge color="gray" size="sm">{{ __('admin.page_builder.hidden') }}</x-badge>
        @endif
        @if($block->visible_from || $block->visible_until)
            <x-badge color="warning" size="sm">{{ __('admin.page_builder.scheduled') }}</x-badge>
        @endif
        @if($block->device_target && $block->device_target !== 'all')
            <x-badge color="primary" size="sm">{{ ucfirst($block->device_target) }}</x-badge>
        @endif
    </div>

    <div class="block-actions flex items-center gap-1">
        <button type="button" class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded"
                data-action="edit-block" title="{{ __('common.edit') }}">
            <x-heroicon name="cog-6-tooth" class="w-4 h-4" />
        </button>
        <button type="button" class="p-1.5 text-gray-400 hover:text-danger-600 hover:bg-danger-50 rounded"
                data-action="delete-block" title="{{ __('common.delete') }}">
            <x-heroicon name="trash" class="w-4 h-4" />
        </button>
    </div>
</div>
