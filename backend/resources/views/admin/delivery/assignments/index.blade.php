@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/leaflet.js', 'resources/js/components/select2.js'])
@endpush

@section('title', __('admin.delivery_section.assignments'))

@section('content')

    {{-- ─── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.delivery_section.assignments') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.delivery_section.assignments_desc') }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" id="manual-assign-btn" class="btn btn-secondary btn-sm">{{ __('admin.delivery_section.manual_assign') }}</button>
            <button type="button" id="auto-assign-btn" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.auto_assign') }}</button>
        </div>
    </div>

    <div x-data="{ tab: 'map' }">

        <div class="flex gap-1 border-b border-gray-200 pb-px mb-6">
            @foreach(['map' => __('admin.delivery_section.tab_live_map'), 'assignments' => __('admin.delivery_section.tab_all_assignments')] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}'
                                ? 'border-b-2 border-primary-600 text-primary-700 font-semibold'
                                : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm whitespace-nowrap transition-colors focus:outline-none">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ── Live Map ─────────────────────────────────────────────────────────── --}}
        <div x-show="tab === 'map'">
            <x-card>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-4 text-xs text-gray-600">
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span> {{ __('admin.delivery_section.available_legend') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span> {{ __('admin.delivery_section.on_delivery_legend') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-3 h-3 rounded-full bg-gray-400"></span> {{ __('admin.delivery_section.offline_legend') }}
                        </span>
                    </div>
                    <button type="button" id="refresh-map-btn" class="btn btn-xs btn-ghost">{{ __('admin.delivery_section.refresh_map') }}</button>
                </div>
                <div id="live-map" style="height: 480px;" class="rounded-lg z-0"></div>
            </x-card>
        </div>

        {{-- ── Assignments DataTable ─────────────────────────────────────────────── --}}
        <div x-show="tab === 'assignments'" x-cloak>
            <x-card class="mb-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="w-40">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                        <select id="filter-status" class="form-input w-full text-sm">
                            <option value="">{{ __('common.all') }}</option>
                            <option value="assigned">{{ __('admin.delivery_section.assigned') }}</option>
                            <option value="accepted">{{ __('admin.delivery_section.accepted') }}</option>
                            <option value="picked_up">{{ __('admin.delivery_section.picked_up') }}</option>
                            <option value="delivered">{{ __('admin.delivery_section.delivered') }}</option>
                            <option value="failed">{{ __('admin.delivery_section.failed') }}</option>
                        </select>
                    </div>
                    <div class="w-48">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.agent_col') }}</label>
                        <select id="filter-agent" class="form-input w-full text-sm">
                            <option value="">{{ __('admin.delivery_section.all_agents') }}</option>
                            @foreach($agents as $ag)
                                <option value="{{ $ag->id }}">{{ $ag->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-44">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.zone_col') }}</label>
                        <select id="filter-zone" class="form-input w-full text-sm">
                            <option value="">{{ __('admin.delivery_section.all_zones') }}</option>
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.from_label') }}</label>
                        <input type="date" id="filter-date-from" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.to_label') }}</label>
                        <input type="date" id="filter-date-to" class="form-input text-sm">
                    </div>
                    <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('admin.delivery_section.reset') }}</button>
                </div>
            </x-card>

            <x-card>
                <div class="overflow-x-auto">
                <table id="assignments-table" class="w-full text-sm" style="width:100%">
                    <thead>
                        <tr>
                            <th>{{ __('admin.delivery_section.order_number_col') }}</th>
                            <th>{{ __('admin.delivery_section.agent_col') }}</th>
                            <th>{{ __('admin.delivery_section.zone_col') }}</th>
                            <th>{{ __('common.status') }}</th>
                            <th>{{ __('admin.delivery_section.assigned_at_col') }}</th>
                            <th>{{ __('admin.delivery_section.delivered_at_col') }}</th>
                            <th>{{ __('admin.delivery_section.duration_col') }}</th>
                        </tr>
                    </thead>
                </table>
                </div>
            </x-card>
        </div>

    </div>

    {{-- ─── Auto Assign Modal ───────────────────────────────────────────────────── --}}
    <div id="auto-assign-modal" class="modal-backdrop hidden">
        <div class="modal-box max-w-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">{{ __('admin.delivery_section.auto_assign') }}</h3>
                <button type="button" onclick="document.getElementById('auto-assign-modal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form id="auto-assign-form" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.sub_order_id_required') }} <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="sub_order_id" class="form-input w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.shipment_id_required') }} <span
                            class="text-red-500">*</span></label>
                    <input type="text" name="shipment_id" class="form-input w-full" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.pickup_latitude') }}</label>
                        <input type="number" name="pickup_latitude" class="form-input w-full" step="any">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.pickup_longitude') }}</label>
                        <input type="number" name="pickup_longitude" class="form-input w-full" step="any">
                    </div>
                </div>
                <div class="pt-4 border-t flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('auto-assign-modal').classList.add('hidden')"
                        class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.auto_assign') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Manual Assign Modal ─────────────────────────────────────────────────── --}}
    <div id="manual-assign-modal" class="modal-backdrop hidden">
        <div class="modal-box max-w-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">{{ __('admin.delivery_section.manual_assign') }}</h3>
                <button type="button" onclick="document.getElementById('manual-assign-modal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form id="manual-assign-form" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.sub_order_id_required') }} <span
                            class="text-red-500">*</span></label>
                    <select name="sub_order_id" id="manual-sub-order-select" data-async-select
                        data-config='{{ json_encode(['url' => route('admin.delivery.assignments.search.sub-orders'), 'param' => 'q', 'minLength' => 0, 'delay' => 300]) }}'
                        class="form-input w-full" required>
                        <option value=""></option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.shipment_id_required') }} <span
                            class="text-red-500">*</span></label>
                    <select name="shipment_id" id="manual-shipment-select" class="form-input w-full" required disabled>
                        <option value="">{{ __('admin.delivery_section.select_sub_order_first') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.agent_required') }} <span
                            class="text-red-500">*</span></label>
                    <select name="agent_id" class="form-input w-full" required>
                        <option value="">{{ __('admin.delivery_section.select_agent_option') }}</option>
                        @foreach($agents as $ag)
                            <option value="{{ $ag->id }}">{{ $ag->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pt-4 border-t flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('manual-assign-modal').classList.add('hidden')"
                        class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.assign') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            autoAssignFailed: @json(__('admin.delivery_section.auto_assign_failed')),
            assignmentFailed: @json(__('admin.delivery_section.assignment_failed')),
        });

        function boot() {
            const DATATABLE_URL = @json(route('admin.delivery.assignments.datatable'));
            const AUTO_ASSIGN_URL = @json(route('admin.delivery.assignments.auto-assign'));
            const MANUAL_URL = @json(route('admin.delivery.assignments.manual-assign'));
            const LIVE_MAP_URL = @json(route('admin.delivery.assignments.live-map'));
            const token = () => $('meta[name=csrf-token]').attr('content');

            // ── DataTable ─────────────────────────────────────────────────────────────
            const table = $('#assignments-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: DATATABLE_URL,
                    type: 'POST',
                    data: d => Object.assign(d, {
                        _token: token(),
                        status: $('#filter-status').val(),
                        agent_id: $('#filter-agent').val(),
                        zone_id: $('#filter-zone').val(),
                        date_from: $('#filter-date-from').val(),
                        date_to: $('#filter-date-to').val(),
                    }),
                },
                columns: [
                    { data: 'sub_order_number' },
                    { data: 'agent_name', defaultContent: '—' },
                    { data: 'zone_name', defaultContent: '—' },
                    {
                        data: 'status',
                        render: v => {
                            const c = { assigned: 'gray', accepted: 'primary', picked_up: 'warning', delivered: 'success', failed: 'danger' }[v] ?? 'gray';
                            return `<span class="badge badge-${c} text-xs capitalize">${v.replace('_', ' ')}</span>`;
                        }
                    },
                    { data: 'assigned_at' },
                    { data: 'delivered_at', defaultContent: '—' },
                    { data: 'duration_minutes', render: v => v ? `${v} min` : '—' },
                ],
                order: [[4, 'desc']],
                pageLength: 25,
            });

            ['#filter-status', '#filter-agent', '#filter-zone'].forEach(sel =>
                $(sel).on('change', () => table.ajax.reload())
            );
            ['#filter-date-from', '#filter-date-to'].forEach(sel =>
                $(sel).on('change', () => table.ajax.reload())
            );
            $('#clear-filters').on('click', () => {
                $('#filter-status, #filter-agent, #filter-zone').val('');
                $('#filter-date-from, #filter-date-to').val('');
                table.ajax.reload();
            });

            // ── Live Map ──────────────────────────────────────────────────────────────
            const defaultCenter = [25.0, 30.0];
            let map = null, markers = [];

            function initMap() {
                if (map) return;
                map = L.map('live-map').setView(defaultCenter, 4);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18,
                }).addTo(map);
                loadMapData();
            }

            function loadMapData() {
                markers.forEach(m => m.remove());
                markers = [];
                $.getJSON(LIVE_MAP_URL, data => {
                    const colors = { available: '#22c55e', on_delivery: '#3b82f6', offline: '#9ca3af' };

                    (data.agents || []).forEach(agent => {
                        if (!agent.lat || !agent.lng) return;
                        const color = colors[agent.map_status] ?? colors.offline;
                        const icon = L.divIcon({
                            html: `<div style="width:14px;height:14px;border-radius:50%;background:${color};border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,.3)"></div>`,
                            className: '',
                            iconSize: [14, 14],
                        });
                        const m = L.marker([agent.lat, agent.lng], { icon })
                            .bindPopup(`<b>${agent.name}</b><br>${agent.phone}<br><em>${agent.status.replace('_', ' ')}</em>`)
                            .addTo(map);
                        markers.push(m);
                    });

                    (data.assignments || []).forEach(a => {
                        if (!a.pickup_lat || !a.pickup_lng) return;
                        const m = L.circleMarker([a.pickup_lat, a.pickup_lng], {
                            radius: 6, color: '#f59e0b', fillColor: '#fbbf24', fillOpacity: 0.8, weight: 2,
                        }).bindPopup(`Order: ${a.sub_order_number}<br>Status: ${a.status}`)
                            .addTo(map);
                        markers.push(m);
                    });
                });
            }

            // Init map when tab is visible
            document.querySelectorAll('[\\@click]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (btn.getAttribute('@click')?.includes("tab = 'map'")) {
                        setTimeout(() => { initMap(); map?.invalidateSize(); }, 100);
                    }
                });
            });

            // Init on page load (map is the default tab)
            setTimeout(() => { initMap(); }, 200);

            $('#refresh-map-btn').on('click', loadMapData);

            // ── Auto Assign ───────────────────────────────────────────────────────────
            $('#auto-assign-btn').on('click', () =>
                document.getElementById('auto-assign-modal').classList.remove('hidden')
            );
            $('#auto-assign-form').on('submit', function (e) {
                e.preventDefault();
                $.ajax({
                    url: AUTO_ASSIGN_URL,
                    method: 'POST',
                    data: $(this).serialize() + '&_token=' + token(),
                    success: res => {
                        if (res.success) {
                            window.Toast?.success(res.message);
                            document.getElementById('auto-assign-modal').classList.add('hidden');
                            this.reset();
                            table.ajax.reload();
                            loadMapData();
                        }
                    },
                    error: xhr => window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.autoAssignFailed),
                });
            });

            // ── Manual Assign ─────────────────────────────────────────────────────────
            const SHIPMENTS_SEARCH_URL = @json(route('admin.delivery.assignments.search.shipments'));
            const $subOrderSelect = $('#manual-sub-order-select');
            const $shipmentSelect = $('#manual-shipment-select');

            function initShipmentSelect2(subOrderId) {
                if ($shipmentSelect.data('select2')) {
                    $shipmentSelect.select2('destroy');
                }
                $shipmentSelect.empty();
                $shipmentSelect.select2({
                    width: '100%',
                    placeholder: @json(__('admin.delivery_section.shipment_id_required')),
                    allowClear: true,
                    minimumInputLength: 0,
                    ajax: {
                        url: SHIPMENTS_SEARCH_URL,
                        dataType: 'json',
                        delay: 300,
                        data: params => ({ q: params.term, sub_order_id: subOrderId, page: params.page || 1 }),
                        processResults: response => ({
                            results: response.data ?? [],
                            pagination: { more: response.meta?.current_page < response.meta?.last_page },
                        }),
                        headers: {
                            'X-CSRF-TOKEN': token(),
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                    },
                });
            }

            function resetShipmentSelect() {
                if ($shipmentSelect.data('select2')) {
                    $shipmentSelect.val(null).trigger('change');
                    $shipmentSelect.select2('destroy');
                }
                $shipmentSelect.empty()
                    .append(new Option(@json(__('admin.delivery_section.select_sub_order_first')), '', true, true))
                    .prop('disabled', true);
            }

            $('#manual-assign-btn').on('click', () => {
                document.getElementById('manual-assign-modal').classList.remove('hidden');
                window.initSelect2?.($('#manual-assign-modal'));
                resetShipmentSelect();
            });

            $subOrderSelect.on('change', function () {
                const subOrderId = $(this).val();
                resetShipmentSelect();
                if (subOrderId) {
                    $shipmentSelect.prop('disabled', false);
                    initShipmentSelect2(subOrderId);
                }
            });

            $('#manual-assign-form').on('submit', function (e) {
                e.preventDefault();
                $.ajax({
                    url: MANUAL_URL,
                    method: 'POST',
                    data: $(this).serialize() + '&_token=' + token(),
                    success: res => {
                        if (res.success) {
                            window.Toast?.success(res.message);
                            document.getElementById('manual-assign-modal').classList.add('hidden');
                            this.reset();
                            $subOrderSelect.val(null).trigger('change');
                            resetShipmentSelect();
                            table.ajax.reload();
                            loadMapData();
                        }
                    },
                    error: xhr => window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.assignmentFailed),
                });
            });
        }

        // ES modules loaded via Vite are deferred — DOMContentLoaded fires
        // after all deferred scripts, so jQuery ($) is guaranteed to exist.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    </script>
@endpush
