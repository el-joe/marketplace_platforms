@props([
    'name',
    'label'    => null,
    'checked'  => null,   // preferred: pass :checked="true/false"
    'value'    => false,  // legacy alias
    'helpText' => null,
    'size'     => 'md', // sm | md | lg
])

@php
    $checked   = (bool) old($name, $checked ?? $value);
    $hasError  = $errors->has($name);

    $trackSize = match($size) {
        'sm'  => 'w-8 h-4',
        'lg'  => 'w-14 h-7',
        default => 'w-11 h-6',
    };
    $thumbSize = match($size) {
        'sm'  => 'w-3 h-3',
        'lg'  => 'w-5 h-5',
        default => 'w-4 h-4',
    };
    $thumbOn   = match($size) {
        'sm'  => 'translate-x-4',
        'lg'  => 'translate-x-7',
        default => 'translate-x-5',
    };
@endphp

<div class="space-y-1">
    <label
        x-data="{ on: {{ $checked ? 'true' : 'false' }} }"
        class="inline-flex items-center gap-3 cursor-pointer group">

        {{-- Actual checkbox (screen-reader accessible) --}}

        <input
            type="checkbox"
            name="{{ $name }}"
            id="{{ $name }}"
            value="1"
            class="sr-only peer"
            x-model="on"
            @if($checked) checked @endif>

        {{-- Visual track --}}
        <div
            :class="on ? 'bg-primary-600' : 'bg-gray-300'"
            class="{{ $trackSize }} rounded-full transition-colors duration-200 relative
                   focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-offset-2">
            {{-- Thumb --}}
            <div
                :class="on ? '{{ $thumbOn }}' : 'translate-x-1'"
                class="{{ $thumbSize }} bg-white rounded-full shadow absolute top-1 left-0 transition-transform duration-200">
            </div>
        </div>

        @if($label)
            <span class="text-sm font-medium text-gray-700 group-hover:text-gray-900">{{ $label }}</span>
        @endif
    </label>

    @if($helpText && !$hasError)
        <p class="text-xs text-gray-500 ml-14">{{ $helpText }}</p>
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
