@php
    $hasError = $errors->has($name);
    $selectClass = 'block w-full rounded-lg border py-2 pl-3 pr-9 text-sm text-gray-900 bg-white shadow-sm '
        . 'focus:outline-none focus:ring-2 focus:ring-offset-0 '
        . ($hasError
            ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-200'
            : 'border-gray-300 focus:border-primary-500 focus:ring-primary-200')
        . ($disabled ? ' bg-gray-50 cursor-not-allowed opacity-60' : '');
@endphp

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-danger-500 ml-0.5" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <select name="{{ $name }}{{ $multiple ? '[]' : '' }}" id="{{ $name }}" @if($required) required @endif @if($disabled)
    disabled @endif @if($multiple) multiple @endif @if($select2) data-select2-init @endif
        class="{{ $selectClass }}" aria-invalid="{{ $hasError ? 'true' : 'false' }}" @if($hasError)
        aria-describedby="{{ $name }}-error" @endif @if(!$slot->isEmpty() && $value !== null)
        data-selected-value="{{ $value }}" @endif {{ $attributes->except(['class', 'name', 'id']) }}>
        @if($placeholder && !$multiple)
            <option value="">{{ $placeholder }}</option>
        @endif

        @if(!$slot->isEmpty())
            {{ $slot }}
        @elseif($isGrouped)
            @foreach($normalizedOptions as $groupLabel => $groupItems)
                <optgroup label="{{ $groupLabel }}">
                    @foreach($groupItems as $optVal => $optLabel)
                        <option value="{{ $optVal }}" @selected($isSelected($optVal))>
                            {{ $optLabel }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        @else
            @foreach($normalizedOptions as $optVal => $optLabel)
                <option value="{{ $optVal }}" @selected($isSelected($optVal))>
                    {{ $optLabel }}
                </option>
            @endforeach
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