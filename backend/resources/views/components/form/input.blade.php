<div class="space-y-1">
    @if($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-danger-500 ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="{{ ($prefix || $suffix) ? 'flex rounded-lg shadow-sm' : '' }}">
        @if($prefix)
            <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0
                         border-gray-300 bg-gray-50 text-gray-500 text-sm select-none">
                {{ $prefix }}
            </span>
        @endif

        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $id }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            @if($required)   required   @endif
            @if($disabled)   disabled   @endif
            @if($readonly)   readonly   @endif
            @if($maxlength)  maxlength="{{ $maxlength }}" @endif
            class="{{ $inputClasses }} {{ ($prefix && !$suffix) ? 'rounded-l-none' : '' }} {{ (!$prefix && $suffix) ? 'rounded-r-none' : '' }} {{ ($prefix && $suffix) ? 'rounded-none' : '' }}"
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            @if($hasError) aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->except(['class','name','id','type','value','placeholder','required','disabled','readonly','maxlength']) }}
        >

        @if($suffix)
            <span class="inline-flex items-center px-3 rounded-r-lg border border-l-0
                         border-gray-300 bg-gray-50 text-gray-500 text-sm select-none">
                {{ $suffix }}
            </span>
        @endif
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
