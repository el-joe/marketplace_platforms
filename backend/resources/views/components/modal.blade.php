@props([
    'id'         => null,
    'title'      => null,
    'size'       => 'md',  // sm | md | lg | xl
    'persistent' => false, // true => backdrop click doesn't close
])

@php
    if (! $id) {
        $id = 'modal-' . \Illuminate\Support\Str::random(8);
    }
    $sizeClass = match($size) {
        'sm' => 'modal-sm',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        default => 'modal-md',
    };
@endphp

<div id="{{ $id }}"
     class="modal-backdrop hidden"
     role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
     @if($persistent) data-persistent="true" @endif>
    <div class="modal-panel {{ $sizeClass }}" data-modal-panel>
        @if($title)
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 id="{{ $id }}-title" class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                <button type="button" data-modal-close
                        class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100"
                        aria-label="{{ __('common.close') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <div class="px-6 py-4">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-end gap-2 rounded-b-xl">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
