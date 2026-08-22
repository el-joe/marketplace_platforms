@extends('layouts.carrier')
@section('title', __('carrier.reports.earnings_title'))
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
        <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.reports.earnings_title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('carrier.reports.earnings_subtitle') }}</p>
    </div>
    <a href="{{ route('carrier.reports.earnings.export', request()->query()) }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold px-4 py-2 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        {{ __('carrier.reports.export_excel') }}
    </a>
</div>

{{-- Summary by type --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['label' => __('carrier.reports.earning_base_fee'),   'key' => 'base_fee'],
        ['label' => __('carrier.reports.earning_cod'),        'key' => 'cod_handling'],
        ['label' => __('carrier.reports.earning_bonus'),      'key' => 'bonus'],
        ['label' => __('carrier.reports.earning_deductions'), 'key' => 'deduction'],
    ] as $item)
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-xl font-black text-gray-800">
            {{ number_format($stats['by_type'][$item['key']] ?? 0) }}
            <span class="text-sm font-medium text-gray-400">{{ $currency }}</span>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ $item['label'] }}</div>
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
    <a href="{{ route('carrier.reports.earnings') }}" class="text-gray-500 text-sm hover:underline px-2 py-2">
        {{ __('carrier.reports.reset') }}
    </a>
</form>

{{-- Per-agent table --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($agentSummary->isEmpty())
    <div class="py-16 text-center text-gray-400 text-sm">{{ __('carrier.reports.no_data') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_agent') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.earning_base_fee') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.earning_cod') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.earning_bonus') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.earning_deductions') }}</th>
                    <th class="px-4 py-3 text-right font-bold">{{ __('carrier.reports.col_total') }} ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($agentSummary as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $row->agent_name }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($row->base_fee) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($row->cod_handling) }}</td>
                    <td class="px-4 py-3 text-right text-emerald-600">{{ number_format($row->bonus) }}</td>
                    <td class="px-4 py-3 text-right text-red-500">{{ $row->deductions > 0 ? '−' . number_format($row->deductions) : '—' }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($row->total) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
