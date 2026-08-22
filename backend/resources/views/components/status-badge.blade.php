@props(['status'])

@php
    $map = [
        'placed' => ['color' => 'bg-blue-100 text-blue-700'],
        'confirmed' => ['color' => 'bg-indigo-100 text-indigo-700'],
        'processing' => ['color' => 'bg-purple-100 text-purple-700'],
        'ready_to_ship' => ['color' => 'bg-cyan-100 text-cyan-700'],
        'shipped' => ['color' => 'bg-yellow-100 text-yellow-700'],
        'out_for_delivery' => ['color' => 'bg-orange-100 text-orange-700'],
        'delivered' => ['color' => 'bg-green-100 text-green-700'],
        'completed' => ['color' => 'bg-green-100 text-green-800'],
        'cancelled' => ['color' => 'bg-red-100 text-red-700'],
        'return_requested' => ['color' => 'bg-pink-100 text-pink-700'],
        'returned' => ['color' => 'bg-gray-100 text-gray-700'],
        'refunded' => ['color' => 'bg-gray-100 text-gray-600'],
    ];
    $entry = $map[$status] ?? ['color' => 'bg-gray-100 text-gray-600'];
    $label = trans()->has("common.order_status.$status") ? __("common.order_status.$status") : $status;
@endphp

<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $entry['color'] }}">
    {{ $label }}
</span>