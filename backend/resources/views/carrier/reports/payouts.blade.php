@extends('layouts.carrier')
@section('title', __('carrier.reports.payouts_title'))
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
    <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.reports.payouts_title') }}</h1>
    <p class="text-sm text-gray-500 mt-0.5">{{ __('carrier.reports.payouts_subtitle') }}</p>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-black text-emerald-600">
            {{ number_format($stats['total_net_paid']) }}
            <span class="text-sm font-medium text-gray-400">{{ $currency }}</span>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.stat_total_paid') }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-black text-amber-500">{{ $stats['pending_count'] }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ __('carrier.reports.stat_pending_payouts') }}</div>
    </div>
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
            @foreach(['pending','approved','paid','failed'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2 rounded-lg text-sm transition">
        {{ __('carrier.reports.filter') }}
    </button>
    <a href="{{ route('carrier.reports.payouts') }}" class="text-gray-500 text-sm hover:underline px-2 py-2">{{ __('carrier.reports.reset') }}</a>
</form>

{{-- Table --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($payouts->isEmpty())
    <div class="py-16 text-center text-gray-400 text-sm">{{ __('carrier.reports.no_data') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_payout_number') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_agent') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('carrier.reports.col_period') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.col_deliveries') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.col_gross') }} ({{ $currency }})</th>
                    <th class="px-4 py-3 text-right">{{ __('carrier.reports.col_deductions') }} ({{ $currency }})</th>
                    <th class="px-4 py-3 text-right font-bold">{{ __('carrier.reports.col_net') }} ({{ $currency }})</th>
                    <th class="px-4 py-3 text-center">{{ __('carrier.reports.col_status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($payouts as $payout)
                @php
                    $sc = ['pending'=>'amber','approved'=>'blue','paid'=>'emerald','failed'=>'red'][$payout->status] ?? 'gray';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $payout->payout_number }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $payout->agent_name }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ \Carbon\Carbon::parse($payout->period_start)->format('d M Y') }}
                        &rarr; {{ \Carbon\Carbon::parse($payout->period_end)->format('d M Y') }}
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $payout->total_deliveries }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($payout->gross_earnings) }}</td>
                    <td class="px-4 py-3 text-right text-red-500">
                        {{ $payout->deductions > 0 ? '−' . number_format($payout->deductions) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($payout->net_amount) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-{{ $sc }}-100 text-{{ $sc }}-700 capitalize">
                            {{ $payout->status }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-100">{{ $payouts->links() }}</div>
    @endif
</div>
@endsection
