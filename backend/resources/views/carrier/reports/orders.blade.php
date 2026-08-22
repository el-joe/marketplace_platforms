@extends('layouts.carrier')
@section('title', __('carrier.reports.orders_title'))
@section('content')

<div class="mb-4 flex items-center gap-1 border-b border-gray-200">
    <a href="{{ route('carrier.reports.orders') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.orders*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_orders') }}
    </a>
    <a href="{{ route('carrier.reports.earnings') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.earnings*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_earnings') }}
    </a>
    <a href="{{ route('carrier.reports.payouts') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.payouts*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_payouts') }}
    </a>
    <a href="{{ route('carrier.reports.cod-settlements') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.cod-settlements*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_cod_settlements') }}
    </a>
    <a href="{{ route('carrier.reports.performance') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.performance*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_performance') }}
    </a>
    <a href="{{ route('carrier.reports.claims') }}"
       class="px-4 py-2 text-sm font-medium {{ request()->routeIs('carrier.reports.claims*') ? 'text-indigo-600 font-bold border-b-2 border-indigo-600' : 'text-gray-500 hover:text-indigo-600' }}">
        {{ __('carrier.reports.tab_claims') }}
    </a>
</div>

<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.reports.orders_title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('carrier.reports.orders_subtitle') }}</p>
    </div>
    @if(auth('shipping_supervisor')->user()->hasPermission('view_reports'))
    <a href="{{ route('carrier.reports.orders.export', request()->query()) }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold px-4 py-2 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        {{ __('carrier.reports.export_excel') }}
    </a>
    @endif
</div>

{{-- Summary Stats --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
    @foreach([
        ['label' => __('carrier.reports.stat_total'),           'value' => number_format($stats['total']),     'color' => 'gray'],
        ['label' => __('carrier.reports.stat_delivered'),       'value' => number_format($stats['delivered']), 'color' => 'emerald'],
        ['label' => __('carrier.reports.stat_failed'),          'value' => number_format($stats['failed']),    'color' => 'red'],
        ['label' => __('carrier.reports.stat_shipping_rev'),    'value' => number_format($stats['shipping_revenue']) . ' ' . $currency, 'color' => 'indigo'],
        ['label' => __('carrier.reports.stat_cod_unremitted'),  'value' => number_format($stats['cod_unremitted']) . ' ' . $currency,   'color' => 'amber'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-xl font-black text-{{ $stat['color'] }}-600">{{ $stat['value'] }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('carrier.reports.filter_agent') }}</label>
        <select name="agent_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            <option value="">{{ __('carrier.reports.all_agents') }}</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" @selected(request('agent_id') == $agent->id)>{{ $agent->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('carrier.reports.filter_status') }}</label>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            <option value="">{{ __('carrier.reports.all_statuses') }}</option>
            @foreach(['assigned','accepted','picked_up','delivered','failed'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('carrier.reports.date_from') }}</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('carrier.reports.date_to') }}</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-lg text-sm transition">
        {{ __('carrier.reports.filter') }}
    </button>
    <a href="{{ route('carrier.reports.orders') }}" class="text-gray-500 text-sm hover:underline px-2 py-2">
        {{ __('carrier.reports.reset') }}
    </a>
</form>

{{-- Table --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($rows->isEmpty())
    <div class="py-16 text-center text-gray-400 text-sm">{{ __('carrier.reports.no_data') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_suborder') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_agent') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_status') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_assigned_at') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_delivered_at') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.col_carrier_cost') }} ({{ $currency }})</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.col_cod') }} ({{ $currency }})</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.col_remitted') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($rows as $row)
                @php
                    $sc = ['delivered'=>'emerald','failed'=>'red','picked_up'=>'blue','accepted'=>'indigo','assigned'=>'amber'][$row->status] ?? 'gray';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $row->sub_order_number }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $row->agent_name }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700 capitalize">
                            {{ str_replace('_', ' ', $row->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ $row->assigned_at ? \Carbon\Carbon::parse($row->assigned_at)->format('d M Y H:i') : '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ $row->delivered_at ? \Carbon\Carbon::parse($row->delivered_at)->format('d M Y H:i') : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right font-medium text-gray-800">
                        {{ number_format($row->carrier_shipping_cost) }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600">
                        {{ $row->cod_amount_collected !== null ? number_format($row->cod_amount_collected) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($row->cod_amount_collected !== null)
                            @if($row->cod_remittance_confirmed)
                                <span class="text-emerald-600 font-bold text-xs">✓</span>
                            @else
                                <span class="text-amber-500 font-bold text-xs">⏳</span>
                            @endif
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-100">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
