@props([
    'color' => 'gray', // gray | primary | success | warning | danger
    'size' => 'sm',   // sm | md
])
@php
    $colorMap = [
        'gray' => 'bg-gray-100 text-gray-700',
        'primary' => 'bg-primary-100 text-primary-700',
        'success' => 'bg-success-100 text-success-700',
        'warning' => 'bg-warning-100 text-warning-800',
        'danger' => 'bg-danger-100 text-danger-700',
    ];
    $sizeMap = [
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-sm',
    ];
    $classes = ($colorMap[$color] ?? $colorMap['gray']) . ' ' . ($sizeMap[$size] ?? $sizeMap['sm']);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full font-medium ' . $classes]) }}>
    {{ $slot }}
</span>
