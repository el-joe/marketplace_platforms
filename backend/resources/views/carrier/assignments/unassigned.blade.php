@extends('layouts.carrier')

@section('title', __('carrier.assignments.unassigned_title'))

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-black text-gray-900">{{ __('carrier.assignments.unassigned_title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('carrier.assignments.unassigned_subtitle') }}</p>
    </div>

    @if($noCarriersLinked)
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 mb-6">
            <div class="flex items-start gap-3">
                <div>
                    <p class="text-sm font-semibold text-amber-800">{{ __('carrier.assignments.no_carriers_linked_title') }}</p>
                    <p class="text-xs text-amber-700 mt-1">{{ __('carrier.assignments.no_carriers_linked_body') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($shipments->isEmpty())
            <div class="py-16 text-center text-gray-400 text-sm">{{ __('carrier.assignments.no_unassigned') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.tracking_number') }}</th>
                            <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.order_number') }}</th>
                            <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.city') }}</th>
                            <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.since') }}</th>
                            @if(auth('shipping_supervisor')->user()->hasPermission('assign_orders'))
                                <th class="px-6 py-3 text-right font-semibold">{{ __('carrier.assignments.assign_agent') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($shipments as $shipment)
                            @php
                                $address  = $shipment->subOrder?->order?->shipping_address_snapshot ?? [];
                                $rawCity  = $address['city'] ?? null;
                                $city     = is_array($rawCity)
                                    ? ($rawCity[app()->getLocale()] ?? $rawCity['en'] ?? $rawCity['ar'] ?? '—')
                                    : ($rawCity ?? '—');
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-mono text-xs text-gray-600">{{ $shipment->tracking_number }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-gray-600">
                                    {{ $shipment->subOrder?->sub_order_number ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-700">{{ $city }}</td>
                                <td class="px-6 py-3 text-gray-500 text-xs">{{ $shipment->created_at->diffForHumans() }}</td>
                                @if(auth('shipping_supervisor')->user()->hasPermission('assign_orders'))
                                    <td class="px-6 py-3">
                                        <button onclick="openAssignModal('{{ $shipment->id }}', '{{ $shipment->tracking_number }}')"
                                            class="text-indigo-600 hover:underline text-xs font-medium">
                                            {{ __('carrier.assignments.assign_agent') }}
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $shipments->links() }}
            </div>
        @endif
    </div>

    {{-- Agent Picker Modal --}}
    @if(auth('shipping_supervisor')->user()->hasPermission('assign_orders'))
        <div id="assign-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/40 px-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-lg font-bold text-gray-900">{{ __('carrier.assignments.assign_agent') }}</h2>
                    <button onclick="closeAssignModal()"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                </div>
                <p id="assign-modal-label" class="text-xs text-gray-400 mb-5 font-mono"></p>

                @if($agents->isEmpty())
                    <p class="text-sm text-gray-500 text-center py-6">{{ __('carrier.assignments.no_active_agents') }}</p>
                @else
                    <div class="space-y-2 max-h-72 overflow-y-auto mb-5">
                        @foreach($agents as $agent)
                            <label
                                class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-indigo-400 transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                                <input type="radio" name="assign_agent" value="{{ $agent->id }}" class="accent-indigo-600">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $agent->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $agent->vehicle_type->label() }}
                                        @if(!$agent->is_available)
                                            <span class="text-amber-500 mr-1">· {{ __('carrier.agents.unavailable') }}</span>
                                        @endif
                                    </p>
                                </div>
                                @if($agent->rating_avg)
                                    <span class="text-xs text-amber-500 shrink-0">★ {{ number_format($agent->rating_avg, 1) }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    <button onclick="submitAssign()"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl transition text-sm">
                        {{ __('carrier.assignments.confirm_assign') }}
                    </button>
                @endif
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            let currentShipmentId = null;

            function openAssignModal(shipmentId, trackingNumber) {
                currentShipmentId = shipmentId;
                document.getElementById('assign-modal-label').textContent = trackingNumber;
                document.querySelectorAll('input[name="assign_agent"]').forEach(r => r.checked = false);
                const el = document.getElementById('assign-modal');
                el.classList.remove('hidden');
                el.style.display = 'flex';
            }

            function closeAssignModal() {
                const el = document.getElementById('assign-modal');
                el.style.display = 'none';
                el.classList.add('hidden');
                currentShipmentId = null;
            }

            function submitAssign() {
                const selected = document.querySelector('input[name="assign_agent"]:checked');
                if (!selected) { alert('{{ __('carrier.assignments.please_select_agent') }}'); return; }

                const url = `/assignments/${currentShipmentId}/assign`;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ agent_id: selected.value }),
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
