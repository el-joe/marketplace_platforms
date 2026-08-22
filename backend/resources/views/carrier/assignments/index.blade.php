@extends('layouts.carrier')

@section('title', __('carrier.assignments.title'))

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.assignments.title') }}</h1>
    <p class="text-sm text-gray-500 mt-0.5">{{ __('carrier.assignments.all_assignments') }} — {{ __('carrier.assignments.including_unaccepted') }}</p>
</div>

{{-- Filters --}}
<form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('carrier.assignments.status') }}</label>
        <select name="status" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">{{ __('carrier.assignments.all') }}</option>
            @foreach([
                'assigned'  => __('carrier.assignments.status_assigned'),
                'accepted'  => __('carrier.assignments.status_accepted'),
                'picked_up' => __('carrier.assignments.status_picked_up'),
                'delivered' => __('carrier.assignments.status_delivered'),
                'failed'    => __('carrier.assignments.status_failed'),
            ] as $val=>$label)
            <option value="{{ $val }}" @selected(request('status')===$val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    @if($assignments->isEmpty())
    <div class="py-16 text-center text-gray-400 text-sm">{{ __('carrier.assignments.no_assignments') }}</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.order_number') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.agent') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.status') }}</th>
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.assigned_date') }}</th>
                    @if(auth('shipping_supervisor')->user()->hasPermission('assign_orders'))
                    <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.reassign_agent') }}</th>
                    @endif
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($assignments as $a)
                @php
                    $colors = ['assigned'=>'amber','accepted'=>'blue','picked_up'=>'indigo','delivered'=>'emerald','failed'=>'red'];
                    $labels = [
                        'assigned'  => __('carrier.assignments.status_assigned'),
                        'accepted'  => __('carrier.assignments.status_accepted'),
                        'picked_up' => __('carrier.assignments.status_picked_up'),
                        'delivered' => __('carrier.assignments.status_delivered'),
                        'failed'    => __('carrier.assignments.status_failed'),
                    ];
                    $c = $colors[$a->status->value] ?? 'gray';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ substr($a->id, 0, 8) }}…</td>
                    <td class="px-6 py-3 font-medium text-gray-900">{{ $a->agent?->name ?? '—' }}</td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                            {{ $labels[$a->status->value] ?? $a->status->value }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-gray-500 text-xs">{{ $a->assigned_at?->format('Y-m-d H:i') }}</td>
                    @if(auth('shipping_supervisor')->user()->hasPermission('assign_orders'))
                    <td class="px-6 py-3">
                        @if(in_array($a->status, [\App\Enums\DeliveryAssignmentStatus::Assigned, \App\Enums\DeliveryAssignmentStatus::Accepted], true))
                        <button
                            onclick="openReassignModal('{{ $a->id }}', '{{ addslashes($a->agent?->name) }}')"
                            class="text-indigo-600 hover:underline text-xs font-medium">
                            {{ __('carrier.assignments.reassign_agent') }}
                        </button>
                        @endif
                    </td>
                    @endif
                    <td class="px-6 py-3 text-left">
                        <a href="{{ route('carrier.assignments.show', $a->id) }}"
                           class="text-gray-400 hover:text-indigo-600 text-xs transition">{{ __('carrier.assignments.details') }} ←</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $assignments->links() }}
    </div>
    @endif
</div>

{{-- Reassign Modal --}}
@if(auth('shipping_supervisor')->user()->hasPermission('assign_orders'))
<div id="reassign-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-lg font-bold text-gray-900">{{ __('carrier.assignments.reassign_agent') }}</h2>
            <button onclick="closeReassignModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>
        <p class="text-xs text-gray-400 mb-5">{{ __('carrier.assignments.current_agent') }}: <span id="reassign-current-agent" class="font-medium text-gray-700"></span></p>

        @if($agents->isEmpty())
        <p class="text-sm text-gray-500 text-center py-6">{{ __('carrier.assignments.no_available_agents') }}</p>
        @else
        <div class="space-y-2 max-h-72 overflow-y-auto mb-5">
            @foreach($agents as $agent)
            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-indigo-400 transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                <input type="radio" name="reassign_agent" value="{{ $agent->id }}" class="accent-indigo-600">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900">{{ $agent->name }}</p>
                    <p class="text-xs text-gray-500">{{ $agent->vehicle_type->label() }}</p>
                </div>
                @if($agent->rating_avg)
                <span class="text-xs text-amber-500 shrink-0">★ {{ number_format($agent->rating_avg, 1) }}</span>
                @endif
            </label>
            @endforeach
        </div>
        <button onclick="submitReassign()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl transition text-sm">
            {{ __('carrier.assignments.confirm_reassign') }}
        </button>
        @endif
    </div>
</div>
@endif

@push('scripts')
<script>
let currentAssignmentId = null;

function openReassignModal(assignmentId, currentAgent) {
    currentAssignmentId = assignmentId;
    document.getElementById('reassign-current-agent').textContent = currentAgent || '—';
    document.querySelectorAll('input[name="reassign_agent"]').forEach(r => r.checked = false);
    const el = document.getElementById('reassign-modal');
    el.classList.remove('hidden');
    el.style.display = 'flex';
}

function closeReassignModal() {
    const el = document.getElementById('reassign-modal');
    el.style.display = 'none';
    el.classList.add('hidden');
    currentAssignmentId = null;
}

function submitReassign() {
    const selected = document.querySelector('input[name="reassign_agent"]:checked');
    if (!selected) { alert('{{ __('carrier.assignments.please_select_agent') }}'); return; }

    fetch(`/assignments/${currentAssignmentId}/reassign`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ new_agent_id: selected.value }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { window.location.reload(); }
        else { alert(data.message); }
    });
}
</script>
@endpush

@endsection
