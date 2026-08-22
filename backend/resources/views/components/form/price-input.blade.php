<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}-display" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-danger-500 ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="flex rounded-lg shadow-sm">
        <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0
                     border-gray-300 bg-gray-50 text-gray-600 text-sm font-medium select-none">
            {{ $currencySymbol }}
        </span>
        <input
            type="number"
            id="{{ $name }}-display"
            step="0.01"
            min="{{ $minDisplay() }}"
            @if($maxDisplay() !== null) max="{{ $maxDisplay() }}" @endif
            value="{{ $displayValue }}"
            placeholder="0.00"
            data-price-display="{{ $name }}"
            class="block w-full rounded-r-lg border border-gray-300 px-3 py-2 text-sm
                   text-gray-900 bg-white placeholder-gray-400
                   focus:outline-none focus:ring-2 focus:ring-offset-0
                   {{ $hasError
                       ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
                       : 'focus:border-primary-500 focus:ring-primary-200' }}"
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            @if($hasError) aria-describedby="{{ $name }}-error" @endif
        >
        {{-- Hidden cents input — this is what gets submitted --}}
        <input type="hidden"
               name="{{ $name }}"
               id="{{ $name }}"
               value="{{ $centsValue }}"
               data-price-cents="{{ $name }}">
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
