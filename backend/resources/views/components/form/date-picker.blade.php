@props([
    'name',
    'label'       => null,
    'value'       => null,
    'minDate'     => null,
    'maxDate'     => null,
    'enableTime'  => false,
    'mode'        => 'single',  // single | range | multiple
    'required'    => false,
    'placeholder' => null,
    'helpText'    => null,
])

@php
    $hasError    = $errors->has($name);
    $placeholder = $placeholder ?? ($enableTime ? 'YYYY-MM-DD HH:MM' : 'YYYY-MM-DD');
    $inputClass  = 'block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 '
        . 'placeholder-gray-400 bg-white cursor-pointer '
        . 'focus:outline-none focus:ring-2 focus:ring-offset-0 '
        . ($hasError
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200 '
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-200 ');
@endphp

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required) <span class="text-danger-500 ml-0.5" aria-hidden="true">*</span> @endif
        </label>
    @endif

    <div class="relative">
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            readonly
            @if($required) required @endif
            data-flatpickr
            @if($enableTime) data-enable-time="true" @endif
            @if($mode !== 'single') data-mode="{{ $mode }}" @endif
            @if($minDate) data-min-date="{{ $minDate }}" @endif
            @if($maxDate) data-max-date="{{ $maxDate }}" @endif
            class="{{ $inputClass }}"
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            @if($hasError) aria-describedby="{{ $name }}-error" @endif
        >
        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
            </svg>
        </span>
    </div>

    @if($helpText && !$hasError)
        <p class="text-xs text-gray-500">{{ $helpText }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="flex items-center gap-1 text-xs text-danger-600">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>
