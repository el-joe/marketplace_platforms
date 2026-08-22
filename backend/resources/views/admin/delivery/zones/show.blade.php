@extends('layouts.admin')

@section('title', $zone->name)

@section('content')

{{-- Header --}}
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <a href="{{ route('admin.delivery.zones.index') }}"
           class="text-sm text-indigo-600 hover:underline">
            ← {{ __('admin.delivery_section.zones') }}
        </a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $zone->name }}</h1>
        <div class="flex items-center gap-3 mt-1">
            <code class="text-xs font-mono text-gray-400 bg-gray-100 px-2 py-0.5 rounded">{{ $zone->code }}</code>
            <span class="text-sm text-gray-500">{{ $zone->country?->name_en }}</span>
            <span class="inline-flex items-center gap-1 text-xs font-medium
                {{ $zone->is_active ? 'text-emerald-600' : 'text-gray-400' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $zone->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                {{ $zone->is_active ? __('common.active') : __('common.inactive') }}
            </span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Left Column: Zone Info + Cities ──────────────────────────────── --}}
    <div class="space-y-5">

        {{-- Stats --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">
                {{ __('admin.delivery_section.zone_details') }}
            </h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.delivery_section.delivery_fee_label') }}</dt>
                    <dd class="font-semibold text-gray-800">
                        {{ number_format($zone->base_delivery_fee) }} {{ $zone->country?->currency_code }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">{{ __('admin.delivery_section.cod_fee_label') }}</dt>
                    <dd class="font-semibold text-gray-800">
                        {{ number_format($zone->cod_fee) }} {{ $zone->country?->currency_code }}
                    </dd>
                </div>
                @if($zone->max_active_agents)
                    @php
                        $agentCount = $agents->count();
                        $pct = $zone->max_active_agents > 0
                            ? min(100, round($agentCount / $zone->max_active_agents * 100))
                            : 0;
                        $barColor = $pct >= 100 ? 'bg-red-500' : ($pct >= 75 ? 'bg-amber-400' : 'bg-emerald-500');
                    @endphp
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.delivery_section.max_agents_label') }}</dt>
                        <dd class="font-semibold {{ $pct >= 100 ? 'text-red-600' : 'text-gray-800' }}">
                            {{ $agentCount }} / {{ $zone->max_active_agents }}
                        </dd>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                        <div class="{{ $barColor }} h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                    </div>
                @else
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.delivery_section.agents_count_label') }}</dt>
                        <dd class="font-semibold text-gray-800">{{ $agents->count() }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Cities --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">
                {{ __('admin.delivery_section.cities_label') }}
                <span class="ml-1 text-gray-400 font-normal">({{ $cities->count() }})</span>
            </h2>
            @if($cities->isEmpty())
                <p class="text-sm text-gray-400">{{ __('admin.delivery_section.no_cities_assigned') }}</p>
            @else
                <ul class="space-y-1.5">
                    @foreach($cities as $city)
                        <li class="text-sm text-gray-700 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 flex-shrink-0"></span>
                            {{ $city->name_en }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

    </div>

    {{-- ── Right Column: Agents ────────────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Assign agents --}}
        @if($availableAgents->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">
                {{ __('admin.delivery_section.assign_agents_to_zone') }}
            </h2>
            <form id="bulk-assign-form" class="flex gap-3 items-end flex-wrap">
                @csrf
                <div class="flex-1 min-w-48">
                    <label class="block text-xs text-gray-500 mb-1">{{ __('admin.delivery_section.select_agents') }}</label>
                    <select name="agent_ids[]" id="agent-assign-select" multiple
                            class="form-input w-full" style="min-height:100px;">
                        @foreach($availableAgents as $a)
                            <option value="{{ $a->id }}">{{ $a->name }}
                                ({{ $a->zone_id ? __('admin.delivery_section.has_zone') : __('admin.delivery_section.no_zone') }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm self-end">
                    {{ __('admin.delivery_section.assign_selected') }}
                </button>
            </form>
        </div>
        @endif

        {{-- Current agents table --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">
                    {{ __('admin.delivery_section.zone_agents') }}
                    <span class="ml-1 text-gray-400 text-sm font-normal">({{ $agents->count() }})</span>
                </h2>
            </div>

            @if($agents->isEmpty())
                <div class="px-5 py-10 text-center text-gray-400 text-sm">
                    {{ __('admin.delivery_section.no_agents_in_zone') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-5 py-3 text-left">{{ __('admin.delivery_section.agent_col_name') }}</th>
                                <th class="px-5 py-3 text-left">{{ __('admin.delivery_section.phone_col') }}</th>
                                <th class="px-5 py-3 text-left">{{ __('admin.delivery_section.vehicle_col') }}</th>
                                <th class="px-5 py-3 text-left">{{ __('common.status') }}</th>
                                <th class="px-5 py-3 text-center">{{ __('admin.delivery_section.available_col') }}</th>
                                <th class="px-5 py-3 text-left">{{ __('admin.delivery_section.last_login_col') }}</th>
                                <th class="px-5 py-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($agents as $agent)
                                @php
                                    $sc = match($agent->status->value) {
                                        'active'   => 'emerald',
                                        'on_shift' => 'blue',
                                        'suspended'=> 'red',
                                        default    => 'gray',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('admin.delivery.agents.show', $agent) }}"
                                           class="font-medium text-gray-900 hover:text-indigo-600">
                                            {{ $agent->name }}
                                        </a>
                                        <p class="text-xs text-gray-400">{{ $agent->email }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-gray-600">{{ $agent->phone }}</td>
                                    <td class="px-5 py-3 text-gray-600 capitalize">{{ $agent->vehicle_type->value }}</td>
                                    <td class="px-5 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                            bg-{{ $sc }}-100 text-{{ $sc }}-700 capitalize">
                                            {{ $agent->status->value }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="w-2 h-2 rounded-full inline-block
                                            {{ $agent->is_available ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-500 text-xs">
                                        {{ $agent->last_login_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <button type="button"
                                                class="btn-remove-from-zone text-xs text-red-500 hover:text-red-700 font-medium"
                                                data-agent-id="{{ $agent->id }}"
                                                data-agent-name="{{ $agent->name }}">
                                            {{ __('admin.delivery_section.remove_from_zone') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
const ZONE_ID    = @json($zone->id);
const ASSIGN_URL = @json(route('admin.delivery.zones.assign-agents', $zone->id));
const CSRFTOKEN  = () => document.querySelector('meta[name="csrf-token"]').content;

// ── Bulk assign ──────────────────────────────────────────────────────────
document.getElementById('bulk-assign-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const selected = [...document.getElementById('agent-assign-select').selectedOptions].map(o => o.value);
    if (!selected.length) return;

    const res  = await fetch(ASSIGN_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRFTOKEN(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ agent_ids: selected }),
    });
    const data = await res.json();
    if (data.success) { window.Toast?.success(data.message); setTimeout(() => location.reload(), 800); }
    else window.Toast?.error(data.message ?? 'Assignment failed.');
});

// ── Remove from zone ──────────────────────────────────────────────────────
document.querySelectorAll('.btn-remove-from-zone').forEach(btn => {
    btn.addEventListener('click', async () => {
        const name = btn.dataset.agentName;
        if (!confirm(`Remove ${name} from this zone?`)) return;

        const assignUrl = `{{ url('delivery/agents') }}/${btn.dataset.agentId}/assign-zone`;
        const res = await fetch(assignUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRFTOKEN(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ zone_id: null }),
        });
        const data = await res.json();
        if (data.success) { window.Toast?.success(`${name} removed from zone.`); btn.closest('tr').remove(); }
        else window.Toast?.error(data.message ?? 'Failed.');
    });
});
</script>
@endpush
