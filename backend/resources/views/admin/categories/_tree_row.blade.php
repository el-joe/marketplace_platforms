@php
    $hasChildren = $category->children->isNotEmpty();
    $searchText = mb_strtolower(trim($category->name_en . ' ' . $category->name_ar . ' ' . $category->slug));
@endphp
<tr class="hover:bg-gray-50 transition-colors" data-id="{{ $category->id }}"
    data-parent="{{ $category->parent_id ?? '' }}" data-depth="{{ $depth }}"
    data-search="{{ $searchText }}" @if($depth > 0) style="display:none"
    @endif>

    {{-- Expand toggle --}}
    <td class="px-2 py-2 w-8">
        @if($hasChildren)
            <button type="button"
                class="expand-btn w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded"
                data-id="{{ $category->id }}">
                <x-heroicon name="chevron-right" class="w-4 h-4 transition-transform duration-150" />
            </button>
        @endif
    </td>

    {{-- Name with indent --}}
    <td class="px-4 py-2">
        <div class="flex items-center gap-2" style="padding-left: {{ $depth * 20 }}px">
            <input type="checkbox" class="category-cb rounded border-gray-300 text-primary-600"
                value="{{ $category->id }}" />
            <div>
                <span class="font-medium text-gray-900">{{ $category->name_en }}</span>
                @if($category->name_ar)
                    <span class="text-xs text-gray-400 mx-1" dir="rtl">{{ $category->name_ar }}</span>
                @endif
                @if($category->slug)
                    <span class="text-xs text-gray-300">/{{ $category->slug }}</span>
                @endif
            </div>
        </div>
    </td>

    <td class="px-4 py-2 text-end text-gray-600 text-sm">
        {{ number_format($category->product_count ?? 0) }}
    </td>

    <td class="px-4 py-2 text-end text-gray-600 text-sm">
        <div class="text-xs space-y-0.5">
            <div>
                <span class="text-blue-500 font-medium">FBP</span>
                {{ number_format((float) $category->commission_fbp_pct, 2) }}%
                @if($category->commission_fbp_fixed > 0)
                    + {{ number_format($category->commission_fbp_fixed, 2) }}
                @endif
            </div>
            <div>
                <span class="text-green-500 font-medium">FBN</span>
                {{ number_format((float) $category->commission_fbn_pct, 2) }}%
                @if($category->commission_fbn_fixed > 0)
                    + {{ number_format($category->commission_fbn_fixed, 2) }}
                @endif
            </div>
        </div>
    </td>

    <td class="px-4 py-2">
        @php $dsm = $defaultShippingByCategory[$category->id] ?? null; @endphp
        @if($dsm)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold"
                style="background:{{ $dsm->badge_color_hex }}; color:{{ $dsm->badge_text_color_hex }}">
                {{ $dsm->badge_label_en ?: $dsm->name }}
            </span>
        @else
            <span class="text-xs text-gray-300">—</span>
        @endif
    </td>

    <td class="px-4 py-2">
        @if($category->is_active)
            <button type="button"
                class="visible-btn flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full transition-colors
                    {{ $category->is_visible
                        ? 'bg-green-100 text-green-700 hover:bg-red-50 hover:text-red-600'
                        : 'bg-amber-100 text-amber-700 hover:bg-green-50 hover:text-green-600' }}"
                data-id="{{ $category->id }}"
                data-visible="{{ $category->is_visible ? '1' : '0' }}"
                data-url="{{ route('admin.categories.toggle-visible', $category->id) }}">
                <x-heroicon name="eye" class="visible-icon-on w-3.5 h-3.5 {{ $category->is_visible ? '' : 'hidden' }}" />
                <x-heroicon name="eye-slash" class="visible-icon-off w-3.5 h-3.5 {{ $category->is_visible ? 'hidden' : '' }}" />
                <span>{{ $category->is_visible ? __('common.visible') : __('admin.categories.hidden') }}</span>
            </button>
        @else
            <x-badge color="gray">{{ __('common.inactive') }}</x-badge>
        @endif
    </td>

    <td class="px-4 py-2">
        <button type="button"
            class="featured-btn flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full transition-colors
                {{ $category->is_featured ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500 hover:bg-amber-50 hover:text-amber-600' }}"
            data-id="{{ $category->id }}" data-featured="{{ $category->is_featured ? '1' : '0' }}"
            data-url="{{ route('admin.categories.toggle-featured', $category->id) }}">
            <x-heroicon name="star" class="w-3.5 h-3.5" />
            <span>{{ $category->is_featured ? __('admin.categories.featured') : __('admin.add') }}</span>
        </button>
    </td>

    <td class="px-4 py-2 text-end">
        <div class="flex items-center justify-end gap-1">
            <a href="{{ route('admin.categories.edit', $category->id) }}" class="btn btn-ghost btn-xs">{{ __('common.edit') }}</a>
            <button type="button" class="btn btn-ghost btn-xs text-red-600 hover:bg-red-50 delete-cat-btn"
                data-id="{{ $category->id }}" data-name="{{ addslashes($category->name_en) }}"
                data-url="{{ route('admin.categories.destroy', $category->id) }}">
                {{ __('common.delete') }}
            </button>
        </div>
    </td>
</tr>

@if($hasChildren)
    @foreach($category->children as $child)
        @include('admin.categories._tree_row', ['category' => $child, 'depth' => $depth + 1, 'defaultShippingByCategory' => $defaultShippingByCategory])
    @endforeach
@endif