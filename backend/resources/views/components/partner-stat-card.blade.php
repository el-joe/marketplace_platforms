@props([
    'title' => '',
    'value' => '0',
    'suffix' => null,
    'icon' => 'squares-2x2',
    'color' => 'gray',   // green | blue | warning | danger | success | primary | yellow | gray
    'link' => null,
])
@php
    $colorMap = [
        'green' => ['bg' => 'bg-green-50', 'icon' => 'bg-green-100 text-green-600', 'text' => 'text-green-700'],
        'blue' => ['bg' => 'bg-blue-50', 'icon' => 'bg-blue-100 text-blue-600', 'text' => 'text-blue-700'],
        'warning' => ['bg' => 'bg-yellow-50', 'icon' => 'bg-yellow-100 text-yellow-600', 'text' => 'text-yellow-700'],
        'danger' => ['bg' => 'bg-red-50', 'icon' => 'bg-red-100 text-red-600', 'text' => 'text-red-700'],
        'success' => ['bg' => 'bg-green-50', 'icon' => 'bg-green-100 text-green-600', 'text' => 'text-green-700'],
        'primary' => ['bg' => 'bg-primary-50', 'icon' => 'bg-primary-100 text-primary-600', 'text' => 'text-primary-700'],
        'yellow' => ['bg' => 'bg-yellow-50', 'icon' => 'bg-yellow-100 text-yellow-600', 'text' => 'text-yellow-700'],
        'gray' => ['bg' => 'bg-gray-50', 'icon' => 'bg-gray-100 text-gray-500', 'text' => 'text-gray-700'],
    ];
    $c = $colorMap[$color] ?? $colorMap['gray'];
    $tag = $link ? 'a' : 'div';
@endphp

<{{ $tag }} @if($link) href="{{ $link }}" @endif
    class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4 {{ $link ? 'hover:border-gray-300 hover:shadow-sm transition-all' : '' }}">

    <div class="w-11 h-11 rounded-xl {{ $c['icon'] }} flex items-center justify-center shrink-0">
        <x-heroicon :name="$icon" class="w-5 h-5" />
</div>

    <div class="min-w-0">
        <p class="text-xs font-medium text-gray-500 mb-0.5">{{ $title }}</p>
        <p class="text-xl font-bold text-gray-900 leading-tight">
            {{ $value }}
            @if($suffix)
                <span class="text-sm font-medium text-gray-500">{{ $suffix }}</span>
            @endif
        </p>
    </div>

</{{ $tag }}>
