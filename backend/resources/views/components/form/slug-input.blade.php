@props([
    'name',
    'label'       => null,
    'value'       => null,
    'sourceField' => null,  // name of the field to listen to for auto-generation
    'prefix'      => null,  // URL prefix shown before the slug input
    'editable'    => false, // if false, locked until user clicks unlock
    'required'    => false,
    'helpText'    => __('admin.product_form.seo_slug_help'), // help text shown below the input
])

@php
    $hasError = $errors->has($name);
    $slug     = old($name, $value ?? '');
@endphp

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required) <span class="text-danger-500 ml-0.5" aria-hidden="true">*</span> @endif
        </label>
    @endif

    <div class="flex rounded-lg shadow-sm"
         x-data="{ locked: {{ $editable ? 'false' : 'true' }} }">

        @if($prefix)
            <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0
                         border-gray-300 bg-gray-50 text-gray-500 text-xs select-none whitespace-nowrap
                         max-w-[180px] truncate">
                {{ rtrim($prefix, '/') }}/
            </span>
        @endif

        <input
            type="text"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ $slug }}"
            @if($required)    required    @endif
            :readonly="locked"
            :class="locked ? 'bg-gray-50 cursor-not-allowed text-gray-500' : 'bg-white'"
            data-slug-target
            @if($sourceField) data-slug-source-field="{{ $sourceField }}" @endif
            class="flex-1 min-w-0 block rounded-none border border-gray-300 px-3 py-2
                   text-sm text-gray-900 placeholder-gray-400 transition
                   focus:outline-none focus:ring-2 focus:ring-offset-0
                   {{ $hasError
                       ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                       : 'focus:border-primary-500 focus:ring-primary-200' }}
                   {{ !$prefix ? 'rounded-l-lg' : '' }}"
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
        >

        {{-- Lock / Unlock toggle --}}
        <button
            type="button"
            @click="locked = !locked; !locked && $nextTick(() => $el.previousElementSibling.focus())"
            :title="locked ? @js(__('common.click_to_edit_slug')) : @js(__('common.lock_slug'))"
            class="inline-flex items-center px-3 rounded-r-lg border border-l-0
                   border-gray-300 bg-gray-50 text-gray-500 hover:bg-gray-100
                   hover:text-gray-700 transition-colors">
            <template x-if="locked">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
            </template>
            <template x-if="!locked">
                <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.5 10.5V6.75a4.5 4.5 0 0 1 4.5-4.5 4.5 4.5 0 0 1 4.5 4.5v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
            </template>
        </button>
    </div>

    @if($helpText && !$hasError)
        <p class="text-xs text-gray-500">{{ $helpText }}</p>
    @endif

    @error($name)
        <p class="flex items-center gap-1 text-xs text-danger-600">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>
