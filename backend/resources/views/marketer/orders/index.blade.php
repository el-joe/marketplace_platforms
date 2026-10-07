@extends('layouts.marketer')
@section('title', __('marketer.orders.index_title'))
@section('page-title', __('marketer.orders.page_title'))

@section('content')
<div class="space-y-5">

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.orders.own_orders_count') }}</div>
            <div class="text-3xl font-black text-gray-900">{{ number_format($summary['own_orders_count']) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.orders.total_orders') }}</div>
            <div class="text-3xl font-black text-gray-900">{{ number_format($summary['total_orders']) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.orders.total_commission') }}</div>
            <div class="text-2xl font-black text-gray-900">{{ number_format($summary['total_commission']) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.orders.paid_commission') }}</div>
            <div class="text-2xl font-black text-green-600">{{ number_format($summary['paid_commission']) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.orders.pending_commission') }}</div>
            <div class="text-2xl font-black text-yellow-500">{{ number_format($summary['pending_commission']) }}</div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6" aria-label="Tabs">
            <a href="{{ route('marketer.orders.index', array_merge(request()->except('tab', 'own_page', 'campaign_page'), ['tab' => 'own'])) }}"
               class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-semibold
                      {{ $activeTab === 'own' ? 'border-gray-800 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                مبيعاتي
                <span class="ms-1 text-xs rounded-full px-1.5 py-0.5
                             {{ $activeTab === 'own' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-500' }}">
                    {{ $summary['own_orders_count'] }}
                </span>
            </a>
            <a href="{{ route('marketer.orders.index', array_merge(request()->except('tab', 'own_page', 'campaign_page'), ['tab' => 'campaign'])) }}"
               class="whitespace-nowrap pb-3 px-1 border-b-2 text-sm font-semibold
                      {{ $activeTab === 'campaign' ? 'border-gray-800 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                عمولاتي
                <span class="ms-1 text-xs rounded-full px-1.5 py-0.5
                             {{ $activeTab === 'campaign' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-500' }}">
                    {{ $summary['total_orders'] }}
                </span>
            </a>
        </nav>
    </div>

    {{-- ── Tab 1: My Sales (own listing orders) ── --}}
    @if($activeTab === 'own')

        {{-- Status filters --}}
        <div class="flex gap-2 flex-wrap">
            @foreach(['', 'placed', 'confirmed', 'processing', 'packed', 'shipped', 'delivered', 'completed', 'cancelled'] as $s)
                <a href="{{ route('marketer.orders.index', array_merge(request()->except('status', 'own_page'), array_filter(['tab' => 'own', 'status' => $s]))) }}"
                   class="px-3 py-1 rounded-full text-xs font-semibold border
                          {{ request('status', '') === $s ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 text-gray-600' }}">
                    {{ $s ?: __('marketer.orders.filter_all') }}
                </a>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border overflow-hidden">
            @if($ownOrders->isEmpty())
                <div class="p-12 text-center text-gray-400">{{ __('marketer.orders.no_own_orders') }}</div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('marketer.orders.order_number_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.location_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.date_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.items_count_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.total_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.status_header') }}</th>
                            <th class="px-4 py-3 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($ownOrders as $subOrder)
                        @php
                            $order = $subOrder->order;
                            $snap = $order->shipping_address_snapshot ?? [];
                            $city = $snap['city'] ?? null;
                            $country = $order->country?->{'name_' . app()->getLocale()} ?? $order->country?->name_en ?? null;
                            $location = collect([$city, $country])->filter()->implode('، ');

                            $statusMap = [
                                'placed'           => ['cls' => 'bg-blue-100 text-blue-700'],
                                'confirmed'        => ['cls' => 'bg-indigo-100 text-indigo-700'],
                                'processing'       => ['cls' => 'bg-purple-100 text-purple-700'],
                                'packed'           => ['cls' => 'bg-orange-100 text-orange-700'],
                                'shipped'          => ['cls' => 'bg-sky-100 text-sky-700'],
                                'out_for_delivery' => ['cls' => 'bg-teal-100 text-teal-700'],
                                'delivered'        => ['cls' => 'bg-green-100 text-green-700'],
                                'completed'        => ['cls' => 'bg-green-200 text-green-800'],
                                'cancelled'        => ['cls' => 'bg-red-100 text-red-700'],
                                'returned'         => ['cls' => 'bg-pink-100 text-pink-700'],
                                'refunded'         => ['cls' => 'bg-gray-100 text-gray-500'],
                            ];
                            $statusKey = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : (string) $subOrder->status;
                            $st = $statusMap[$statusKey] ?? ['cls' => 'bg-gray-100 text-gray-500'];
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $order->order_number }}</td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $location ?: '—' }}</td>
                            <td class="px-4 py-3 text-center text-xs text-gray-400">{{ $order->placed_at?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-center text-xs text-gray-700">{{ $subOrder->items->count() }}</td>
                            <td class="px-4 py-3 text-center font-bold text-sm">
                                {{ number_format($subOrder->subtotal) }}
                                <span class="text-xs text-gray-400">{{ $order->currency }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded text-xs {{ $st['cls'] }}">{{ $statusKey }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('marketer.orders.show', $subOrder->id) }}"
                                   class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">
                                    {{ __('marketer.orders.view_button') }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $ownOrders->links() }}</div>
            @endif
        </div>

    {{-- ── Tab 2: My Commissions (campaign conversions) ── --}}
    @else

        <div class="bg-white rounded-xl border overflow-hidden">
            @if($campaignOrders->isEmpty())
                <div class="p-12 text-center text-gray-400">{{ __('marketer.orders.no_orders') }}</div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('marketer.orders.order_number_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.status_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.commission_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.commission_status_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.campaign_header') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('marketer.orders.date_header') }}</th>
                            <th class="px-4 py-3 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($campaignOrders as $conv)
                        @php
                            $order = $conv->order;
                            $statusMap = [
                                'placed'    => ['label' => __('marketer.orders.status_placed'),    'cls' => 'bg-blue-100 text-blue-700'],
                                'confirmed' => ['label' => __('marketer.orders.status_confirmed'), 'cls' => 'bg-indigo-100 text-indigo-700'],
                                'delivered' => ['label' => __('marketer.orders.status_delivered'), 'cls' => 'bg-green-100 text-green-700'],
                                'completed' => ['label' => __('marketer.orders.status_completed'), 'cls' => 'bg-green-200 text-green-800'],
                                'cancelled' => ['label' => __('marketer.orders.status_cancelled'), 'cls' => 'bg-red-100 text-red-700'],
                            ];
                            $st = $statusMap[$order->status ?? ''] ?? ['label' => $order->status, 'cls' => 'bg-gray-100 text-gray-500'];
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $order->order_number }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded text-xs {{ $st['cls'] }}">{{ $st['label'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-center font-bold">
                                {{ number_format($conv->commission_amount) }}
                                <span class="text-xs text-gray-400">{{ $conv->currency }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($conv->commissioned)
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded">{{ __('marketer.orders.commission_collected') }}</span>
                                @else
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded">{{ __('marketer.orders.commission_pending') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-500">
                                {{ Str::limit($conv->invitation->campaign->getPromotedTitle(), 25) }}
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-400">
                                {{ $order->placed_at?->format('Y-m-d') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('marketer.orders.show', $order->id) }}"
                                   class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">
                                    {{ __('marketer.orders.view_button') }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <div class="px-4 py-3 border-t">{{ $campaignOrders->links() }}</div>
            @endif
        </div>

    @endif

</div>
@endsection
