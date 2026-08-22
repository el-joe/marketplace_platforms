<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-danger-500 ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    @php
        $hasError = $errors->has($name);
        $oldValue = old($name, $value);
        $oldLabel = old($name . '_label', $valueLabel);
    @endphp

    {{--
    Select2 AJAX replaces this element.
    Pre-selected value: inject an <option> so Select2 shows it on load.
        --}}
        <select name="{{ $name }}{{ $multiple ? '[]' : '' }}" id="{{ $name }}" data-async-select
            data-config='{{ $configJson }}' placeholder="{{ $placeholder }}" @if($required) required @endif
            @if($multiple) multiple @endif class="block w-full rounded-lg border text-sm
               {{ $hasError
    ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
    : 'border-gray-300 focus:border-primary-500 focus:ring-primary-200' }}"
            aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if($hasError) aria-describedby="{{ $name }}-error"
            @endif>
            @if($oldValue)
                <option value="{{ $oldValue }}" selected>
                    {{ $oldLabel ?? $oldValue }}
                </option>
            @endif
        </select>

        @if($helpText && !$hasError)
            <p class="text-xs text-gray-500">{{ $helpText }}</p>
        @endif

        @error($name)
            <p id="{{ $name }}-error" class="flex items-center gap-1 text-xs text-danger-600">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                {{ $message }}
            </p>
        @enderror
</div>