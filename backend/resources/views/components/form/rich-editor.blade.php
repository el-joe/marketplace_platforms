@php
    $hasError = $errors->has($name);
    $safeValue = old($name, $value ?? '');
@endphp

<div class="space-y-1">
    @if($label)
        <label class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-danger-500 ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{-- Summernote attaches to this textarea --}}
    <textarea id="{{ $name }}" name="{{ $name }}" data-rich-editor="{{ $profile }}" data-min-height="{{ $minHeight }}"
        @if($uploadUrl) data-upload-url="{{ $uploadUrl }}" @endif
        class="w-full {{ $hasError ? 'border-danger-500' : '' }}">{!! $safeValue !!}</textarea>

    @if($helpText && !$hasError)
        <p class="text-xs text-gray-500">{{ $helpText }}</p>
    @endif

    @error($name)
        <p class="flex items-center gap-1 text-xs text-danger-600">
            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            {{ $message }}
        </p>
    @enderror
</div>