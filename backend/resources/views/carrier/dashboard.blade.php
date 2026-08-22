@extends('layouts.carrier')

@section('title', __('carrier.nav.home'))

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.dashboard.welcome', ['name' => auth('shipping_supervisor')->user()->name]) }}</h1>
    <p class="text-sm text-gray-500 mt-0.5">{{ $company->name }} — {{ __('carrier.dashboard.last_update') }}: {{ now()->format('H:i') }}</p>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-8">
    @foreach([
        ['label' => __('carrier.dashboard.total_agents'),    'value' => $stats['total_agents'],    'color' => 'gray'],
        ['label' => __('carrier.dashboard.active_agents'),   'value' => $stats['active_agents'],   'color' => 'emerald'],
        ['label' => __('carrier.dashboard.pending'),         'value' => $stats['pending'],         'color' => 'amber'],
        ['label' => __('carrier.dashboard.in_progress'),     'value' => $stats['in_progress'],     'color' => 'blue'],
        ['label' => __('carrier.dashboard.delivered_today'), 'value' => $stats['delivered_today'], 'color' => 'indigo'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <div class="text-2xl font-black text-{{ $stat['color'] }}-600">{{ $stat['value'] }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- Recent Assignments --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-bold text-gray-900">{{ __('carrier.dashboard.recent_assignments') }}</h2>
        @if(auth('shipping_supervisor')->user()->hasPermission('view_orders'))
        <a href="{{ route('carrier.assignments.index') }}" class="text-sm text-indigo-600 hover:underline">{{ __('carrier.dashboard.view_all') }}</a>
        @endif
    </div>

    @if($recentAssignments->isEmpty())
    <div class="px-6 py-10 text-center text-gray-400 text-sm">{{ __('carrier.dashboard.no_assignments_yet') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.dashboard.order_number') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.dashboard.agent') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.dashboard.status') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.dashboard.assigned_date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($recentAssignments as $a)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ substr($a->id, 0, 8) }}…</td>
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $a->agent?->name ?? '—' }}</td>
                    <td class="px-6 py-3">
                        @php
                            $colors = ['assigned'=>'amber','accepted'=>'blue','picked_up'=>'purple','delivered'=>'emerald','failed'=>'red'];
                            $labels = [
                                'assigned'  => __('carrier.assignments.status_assigned'),
                                'accepted'  => __('carrier.assignments.status_accepted'),
                                'picked_up' => __('carrier.assignments.status_picked_up'),
                                'delivered' => __('carrier.assignments.status_delivered'),
                                'failed'    => __('carrier.assignments.status_failed'),
                            ];
                            $c = $colors[$a->status->value] ?? 'gray';
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                            {{ $labels[$a->status->value] ?? $a->status->value }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-gray-500 text-xs">{{ $a->assigned_at?->format('Y-m-d H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
