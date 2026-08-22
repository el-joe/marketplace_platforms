@extends('layouts.carrier')
@section('title', __('carrier.reports.cod_title'))
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

<div class="mb-6">
    <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.reports.cod_title') }}</h1>
    <p class="text-sm text-gray-500 mt-0.5">{{ __('carrier.reports.cod_subtitle') }}</p>
</div>

{{-- Summary --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-amber-200 p-4 text-center">
        <div class="text-xl font-black text-amber-600">
            {{ number_format($stats['pending_cash']) }}
            <span class="text-sm font-medium text-gray-400">{{ $currency }}</span>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.cod_pending_cash') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-emerald-200 p-4 text-center">
        <div class="text-xl font-black text-emerald-600">
            {{ number_format($stats['settled_month']) }}
            <span class="text-sm font-medium text-gray-400">{{ $currency }}</span>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.cod_settled_month') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-red-200 p-4 text-center">
        <div class="text-xl font-black text-red-600">{{ $stats['disputed'] }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.cod_disputed') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-orange-200 p-4 text-center">
        <div class="text-xl font-black text-orange-500">{{ $stats['discrepancies'] }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.cod_discrepancies') }}</div>
    </div>
</div>

{{-- Pending COD per agent (in custody) --}}
@if($agentPendingCod->isNotEmpty())
<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
    <h2 class="text-sm font-bold text-amber-800 mb-3">
        {{ __('carrier.reports.cod_in_custody') }}
    </h2>
    <div class="flex flex-wrap gap-3">
        @foreach($agentPendingCod as $agentId => $total)
        @php $agentName = $agents->firstWhere('id', $agentId)?->name ?? $agentId; @endphp
        <div class="bg-white border border-amber-200 rounded-lg px-4 py-2 text-sm">
            <span class="font-medium text-gray-800">{{ $agentName }}</span>
            <span class="ml-2 font-bold text-amber-700">{{ number_format($total) }} {{ $currency }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

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
            @foreach(['pending','settled','disputed'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-lg text-sm transition">
        {{ __('carrier.reports.filter') }}
    </button>
    <a href="{{ route('carrier.reports.cod-settlements') }}" class="text-gray-500 text-sm hover:underline px-2 py-2">{{ __('carrier.reports.reset') }}</a>
</form>

{{-- Settlement table --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($settlements->isEmpty())
    <div class="py-16 text-center text-gray-400 text-sm">{{ __('carrier.reports.no_data') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_agent') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_period') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.col_cod_collected') }} ({{ $currency }})</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.col_earnings_owed') }} ({{ $currency }})</th>
                    <th class="px-4 py-3 text-right font-bold">{{ __('carrier.reports.col_net_to_remit') }} ({{ $currency }})</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.col_status') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.col_discrepancy') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($settlements as $s)
                @php
                    $sc = ['pending'=>'amber','settled'=>'emerald','disputed'=>'red'][$s->status] ?? 'gray';
                @endphp
                <tr class="hover:bg-gray-50 {{ $s->has_collection_discrepancy ? 'bg-red-50' : '' }}">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $s->agent_name }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ \Carbon\Carbon::parse($s->period_start)->format('d M Y') }}
                        &rarr; {{ \Carbon\Carbon::parse($s->period_end)->format('d M Y') }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($s->total_cod_collected) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($s->total_earnings_owed) }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($s->net_to_remit) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700 capitalize">
                            {{ $s->status }}
                        </span>
                        @if($s->settled_at)
                        <div class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($s->settled_at)->format('d M Y') }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($s->has_collection_discrepancy)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">
                                &#9888; {{ number_format($s->discrepancy_amount) }}
                            </span>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-100">{{ $settlements->links() }}</div>
    @endif
</div>
@endsection
