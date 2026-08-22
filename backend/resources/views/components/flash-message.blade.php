@props([
    'type'    => 'success', // success | error | warning | info
    'message' => '',
])

@php
    $map = [
        'success' => ['bg' => 'bg-success-50',  'border' => 'border-success-200',  'text' => 'text-success-800',  'icon' => 'check-circle'],
        'error'   => ['bg' => 'bg-danger-50',   'border' => 'border-danger-200',   'text' => 'text-danger-800',   'icon' => 'x-circle'],
        'warning' => ['bg' => 'bg-warning-50',  'border' => 'border-warning-200',  'text' => 'text-warning-800',  'icon' => 'exclamation-triangle'],
        'info'    => ['bg' => 'bg-primary-50',  'border' => 'border-primary-200',  'text' => 'text-primary-800',  'icon' => 'information-circle'],
    ];
    $c = $map[$type] ?? $map['info'];
@endphp

<div data-flash-message
     class="fixed top-4 right-4 z-[70] max-w-sm rounded-lg border shadow-lg p-4 pr-3
            flex items-start gap-3 animate-slide-in-right
            {{ $c['bg'] }} {{ $c['border'] }} {{ $c['text'] }}">
    <x-heroicon :name="$c['icon']" class="w-5 h-5 flex-shrink-0 mt-0.5" />
    <div class="flex-1 text-sm">{{ $message }}</div>
    <button type="button" data-flash-dismiss
            class="ml-2 -mt-1 -mr-1 p-1 rounded hover:bg-black/5"
            aria-label="{{ __('common.dismiss') }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
