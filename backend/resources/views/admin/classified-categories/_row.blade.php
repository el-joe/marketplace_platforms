@php
    $locale = app()->getLocale();
    $payload = [
        'id' => $cat->id, 'name_en' => $cat->name_en, 'name_ar' => $cat->name_ar, 'slug' => $cat->slug, 'icon' => $cat->icon,
        'description_en' => $cat->description_en, 'description_ar' => $cat->description_ar,
        'image_url' => $cat->image_url, 'placements' => $cat->placementList(),
        'country_ids' => $cat->countries->pluck('id')->values(),
        'parent_id' => $cat->parent_id, 'has_children' => $cat->children->isNotEmpty(),
        'requires_location_map' => $cat->requires_location_map, 'requires_sketch_upload' => $cat->requires_sketch_upload,
        'contract_template_id' => $cat->contract_template_id, 'requires_contract' => $cat->requiresIndividualContract(),
        'country_contracts' => $cat->contractAssignments->pluck('contract_template_id', 'country_id'),
        'required_attachment_types' => $cat->required_attachment_types ?? [], 'is_active' => $cat->is_active, 'sort_order' => $cat->sort_order,
    ];
@endphp
<tr class="hover:bg-gray-50 {{ $isChild ? 'bg-gray-50/40' : 'bg-white' }} category-row {{ $cat->is_active ? '' : 'opacity-60' }}" data-id="{{ $cat->id }}">
    <td class="px-3 py-3 {{ $isChild ? 'text-gray-200' : 'text-gray-300 cursor-grab drag-handle' }}">
        @unless ($isChild)<x-heroicon name="bars-3" class="w-4 h-4" />@endunless
    </td>
    <td class="px-4 py-3 {{ $isChild ? 'ps-10' : '' }}">
        @if ($isChild)<span class="text-gray-400 me-1">↳</span>@endif
        <span class="{{ $isChild ? 'font-medium text-gray-800' : 'font-semibold text-gray-900' }}">{{ $cat->name_en }}</span>
        @unless ($cat->is_active)
            <span class="ms-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500">{{ __('classified.admin.hidden_badge') }}</span>
        @endunless
        <span class="block text-xs text-gray-400 {{ $isChild ? 'ps-4' : '' }}" dir="rtl">{{ $cat->name_ar }}</span>
        <span class="block text-xs text-gray-300 font-mono {{ $isChild ? 'ps-4' : '' }}">{{ $cat->slug }}</span>
    </td>
    <td class="px-4 py-3">
        <div class="flex items-center gap-2">
            @if ($cat->image_url)
                <img src="{{ $cat->image_url }}" alt="" class="h-8 w-8 rounded object-cover">
            @endif
            <span class="text-lg">{{ $cat->icon ?? ($cat->image_url ? '' : '—') }}</span>
        </div>
    </td>
    <td class="px-4 py-3 text-xs text-gray-600">
        @if ($isChild)
            <span class="text-gray-300">—</span>
        @else
            <div class="flex flex-wrap gap-1">
                @forelse ($cat->placementList() as $placement)
                    <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-medium text-primary-700">{{ \App\Enums\ClassifiedPlacement::from($placement)->label() }}</span>
                @empty
                    <span class="text-gray-400">{{ __('classified.admin.no_placement') }}</span>
                @endforelse
            </div>
        @endif
    </td>
    <td class="px-4 py-3 text-xs text-gray-600">
        @if ($cat->countries->isEmpty())
            <span class="text-gray-400">{{ __('classified.admin.all_countries') }}</span>
        @else
            {{ $cat->countries->map(fn ($c) => $locale === 'ar' ? $c->name_ar : $c->name_en)->implode(', ') }}
        @endif
    </td>
    <td class="px-4 py-3 text-gray-500 text-xs">
        {{ $cat->contractTemplate?->name ?? '—' }}
        @if ($cat->contractTemplate)<span class="text-gray-300">v{{ $cat->contractTemplate->version }}</span>@endif
    </td>
    <td class="px-4 py-3 text-center">
        @if ($cat->requires_location_map)
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('common.yes') }}</span>
        @else
            <span class="text-gray-300 text-xs">{{ __('common.no') }}</span>
        @endif
    </td>
    <td class="px-4 py-3 text-center">
        @if ($cat->requires_sketch_upload)
            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">{{ __('common.yes') }}</span>
        @else
            <span class="text-gray-300 text-xs">{{ __('common.no') }}</span>
        @endif
    </td>
    <td class="px-4 py-3 text-center">
        @if (! empty($cat->required_attachment_types))
            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ count($cat->required_attachment_types) }}</span>
        @else
            <span class="text-gray-300 text-xs">—</span>
        @endif
    </td>
    <td class="px-4 py-3 text-center text-gray-500 text-xs">{{ $cat->active_listing_count ?? 0 }}</td>
    <td class="px-4 py-3 text-center">
        <button type="button" dir="ltr"
                class="btn-toggle-active relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $cat->is_active ? 'bg-emerald-500' : 'bg-gray-200' }}"
                data-id="{{ $cat->id }}" data-active="{{ $cat->is_active ? '1' : '0' }}"
                data-has-children="{{ $cat->children->isNotEmpty() ? '1' : '0' }}"
                aria-label="{{ __('admin.classifieds.toggle_active') }}">
            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform {{ $cat->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
        </button>
    </td>
    <td class="px-4 py-3 text-end whitespace-nowrap">
        <button type="button" class="btn-edit-category text-xs text-primary-600 font-medium hover:underline" data-category="{{ json_encode($payload) }}">
            {{ __('common.edit') }}
        </button>
        <button type="button" class="btn-delete-category ms-2 text-xs text-red-500 hover:underline"
                data-id="{{ $cat->id }}" data-name="{{ $cat->name_en }}">
            {{ __('common.delete') }}
        </button>
    </td>
</tr>
