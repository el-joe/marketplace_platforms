@php($label = app()->getLocale() === 'ar' ? $category->name_ar : $category->name_en)
<label class="flex items-start gap-2 py-0.5 text-sm {{ $child ? 'ms-6' : 'font-medium text-gray-900' }}"
       :class="takenBy(@js($category->id)) ? 'opacity-60' : ''">
    <input type="checkbox" class="mt-0.5 rounded text-primary-600" x-model="picked" value="{{ $category->id }}"
           :disabled="!!takenBy(@js($category->id))">
    <span>
        @if ($child)<span class="text-gray-400">↳</span>@endif
        {{ $label }}
        @unless ($category->is_active)<span class="text-[10px] text-gray-400">({{ __('classified.admin.hidden_badge') }})</span>@endunless
        <span class="block text-[11px] font-normal text-amber-700" x-show="takenBy(@js($category->id))" x-cloak
              x-text="@js(__('contracts.sections.taken_by')).replace(':template', takenBy(@js($category->id)) || '')"></span>
    </span>
</label>
