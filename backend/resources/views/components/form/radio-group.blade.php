@props([
    'name',
    'label'       => null,
    'options'     => [],
    'value'       => null,
    'orientation' => 'vertical', // horizontal | vertical
    'required'    => false,
    'helpText'    => null,
])

@php
    $selected  = old($name, $value);
    $hasError  = $errors->has($name);
    $wrapClass = $orientation === 'horizontal'
        ? 'flex flex-wrap gap-x-6 gap-y-2'
        : 'flex flex-col gap-2';
@endphp

<fieldset class="space-y-1">
    @if($label)
        <legend class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required) <span class="text-danger-500 ml-0.5" aria-hidden="true">*</span> @endif
        </legend>
    @endif

    <div class="{{ $wrapClass }}">
        @foreach($options as $optValue => $optLabel)
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $optValue }}"
                    @checked((string)$selected === (string)$optValue)
                    @if($required) required @endif
                    class="text-primary-600 border-gray-300 focus:ring-primary-500
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
