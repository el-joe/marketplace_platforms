<div class="js-nav-slot flex-1 min-w-0 border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-primary-400 hover:shadow-sm transition-colors"
     data-position="{{ $position }}"
     data-item-id="{{ optional($item)->id }}"
     data-nav-type="{{ optional($item)->nav_type }}"
     data-label-en="{{ optional($item)->label_en }}"
     data-label-ar="{{ optional($item)->label_ar }}"
     data-icon-name="{{ optional($item)->icon_name }}"
     data-deep-link="{{ optional($item)->deep_link }}"
     data-is-center-featured="{{ optional($item)->is_center_featured ? '1' : '0' }}">
    <div class="flex items-center justify-center h-11 w-11 mx-auto rounded-xl {{ optional($item)->is_center_featured ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600' }}">
        <span class="text-xs font-mono">{{ optional($item)->icon_name ?: '—' }}</span>
    </div>
    <div class="text-center text-xs font-medium text-gray-700 mt-2 truncate">
        {{ optional($item)->label_en ?: __('admin.app_contexts.position') . ' ' . $position }}
    </div>
    <div class="text-center text-[11px] text-gray-400 truncate">{{ optional($item)->nav_type ?: '—' }}</div>
</div>
