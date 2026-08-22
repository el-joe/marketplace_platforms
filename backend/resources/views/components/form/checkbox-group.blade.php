@props([
    'name',
    'label'    => null,
    'options'  => [],
    'values'   => [],  // pre-selected values
    'columns'  => 1,   // 1 | 2 | 3
    'helpText' => null,
])

@php
    $selected  = (array) old($name, $values);
    $hasError  = $errors->has($name);
    $colClass  = match((int)$columns) {
        2       => 'grid grid-cols-2 gap-2',
        3       => 'grid grid-cols-2 sm:grid-cols-3 gap-2',
        default => 'flex flex-col gap-2',
    };
@endphp

<fieldset class="space-y-1">
    @if($label)
        <legend class="block text-sm font-medium text-gray-700">{{ $label }}</legend>
    @endif

    <div class="{{ $colClass }}">
        @foreach($options as $optValue => $optLabel)
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    name="{{ $name }}[]"
                    value="{{ $optValue }}"
                    @checked(in_array((string)$optValue, array_map('strval', $selected)))
                    class="rounded text-primary-600 border-gray-300 focus:ring-primary-500
                           {{ $hasError ? 'border-danger-500' : '' }}">
                <span class="text-sm text-gray-700">{{ $optLabel }}</span>
            </label>
        @endforeach
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
</fieldset>
