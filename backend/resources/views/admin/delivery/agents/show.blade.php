@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@section('title', $agent->name . ' — ' . __('admin.delivery_section.agents'))

@section('content')

@php
    $statusColors = [
        'active'    => 'success',
        'inactive'  => 'gray',
        'on_shift'  => 'primary',
        'suspended' => 'danger',
    ];
    $docStatusColors = [
        'pending'  => 'warning',
        'verified' => 'success',
        'rejected' => 'danger',
        'expired'  => 'danger',
    ];
@endphp

{{-- ─── Page Header ─────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex items-start justify-between gap-4">
    <div class="flex items-center gap-4 min-w-0">
        <div class="w-14 h-14 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center text-xl font-bold flex-shrink-0">
            {{ strtoupper(substr($agent->name, 0, 1)) }}
        </div>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900 truncate">{{ $agent->name }}</h1>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
                <x-badge :color="$statusColors[$agent->status->value] ?? 'gray'">
                    {{ ucwords(str_replace('_', ' ', $agent->status->value)) }}
                </x-badge>
                <x-badge color="gray">{{ $agent->agent_type->label() }}</x-badge>
                <x-badge color="gray">{{ $agent->vehicle_type->label() }}</x-badge>
                @if($agent->is_available)
                    <x-badge color="success">{{ __('admin.delivery_section.available_badge') }}</x-badge>
                @endif
            </div>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        @if($agent->status === \App\Enums\DeliveryAgentStatus::Active || $agent->status === \App\Enums\DeliveryAgentStatus::OnShift)
            <button type="button" id="suspend-btn" class="btn btn-warning btn-sm">{{ __('admin.delivery_section.suspend') }}</button>
        @else
            <button type="button" id="activate-btn" class="btn btn-success btn-sm">{{ __('common.active') }}</button>
        @endif
        <button type="button" id="reset-pwd-btn" class="btn btn-secondary btn-sm">{{ __('admin.delivery_section.reset_password') }}</button>
        <a href="{{ route('admin.delivery.agents.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('admin.delivery_section.back') }}</a>
    </div>
</div>

{{-- ─── Stats Strip ─────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['label' => __('admin.delivery_section.total_deliveries_stat'), 'value' => number_format($agent->total_deliveries)],
        ['label' => __('admin.delivery_section.rating_col'),           'value' => number_format($agent->rating_avg, 1) . ' ★'],
        ['label' => __('admin.delivery_section.delivered_stat'),        'value' => number_format($assignmentStats->delivered ?? 0)],
        ['label' => __('admin.delivery_section.failed_stat'),           'value' => number_format($assignmentStats->failed ?? 0)],
    ] as $stat)
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-800">{{ $stat['value'] }}</p>
        </div>
    @endforeach
</div>

{{-- ─── Tabs Layout ─────────────────────────────────────────────────────────── --}}
<div x-data="{ tab: 'profile' }">
    <div class="flex gap-1 border-b border-gray-200 overflow-x-auto pb-px mb-6">
        @foreach([
            'profile'     => __('admin.delivery_section.tab_profile'),
            'assignments' => __('admin.delivery_section.tab_assignments'),
            'earnings'    => __('admin.delivery_section.tab_earnings'),
            'documents'   => __('admin.delivery_section.tab_documents'),
            'shifts'      => __('admin.delivery_section.tab_shifts'),
        ] as $key => $label)
            <button type="button"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'border-b-2 border-primary-600 text-primary-700 font-semibold'
                        : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm whitespace-nowrap transition-colors focus:outline-none">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ── Profile Tab ──────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'profile'">
        <div class="grid grid-cols-12 gap-6">

            {{-- Main profile info --}}
            <div class="col-span-12 lg:col-span-8">
                <x-card title="{{ __('admin.delivery_section.agent_profile') }}">
                    <x-slot name="actions">
                        <button type="button" id="edit-profile-btn" class="btn btn-secondary btn-sm text-xs">{{ __('admin.delivery_section.edit') }}</button>
                    </x-slot>

                    <div id="profile-view" class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        @foreach([
                            __('admin.delivery_section.field_name')                    => $agent->name,
                            __('admin.delivery_section.field_email')                   => $agent->email,
                            __('admin.delivery_section.field_phone')                   => $agent->phone,
                            __('admin.delivery_section.field_country')                 => $agent->country?->name_en ?? '—',
                            __('admin.delivery_section.field_zone')                    => $agent->zone?->name ?? '—',
                            __('admin.delivery_section.field_agent_type')              => $agent->agent_type->label(),
                            __('admin.delivery_section.field_vehicle_type')            => $agent->vehicle_type->label(),
                            __('admin.delivery_section.field_national_id')             => $agent->national_id ?? '—',
                            __('admin.delivery_section.field_vehicle_plate')           => $agent->vehicle_plate ?? '—',
                            __('admin.delivery_section.field_emergency_contact')       => $agent->emergency_contact_name ?? '—',
                            __('admin.delivery_section.field_emergency_phone')         => $agent->emergency_contact_phone ?? '—',
                            __('admin.delivery_section.field_base_salary')             => $agent->base_salary ? number_format($agent->base_salary, 2) : '—',
                            __('admin.delivery_section.field_per_delivery_fee')        => number_format($agent->per_delivery_fee, 2),
                            __('admin.delivery_section.field_last_login')              => $agent->last_login_at?->format('d M Y H:i') ?? __('admin.delivery_section.never'),
                            __('admin.delivery_section.field_last_login_ip')           => $agent->last_login_ip ?? '—',
                            __('admin.delivery_section.field_joined')                  => $agent->created_at->format('d M Y'),
                        ] as $label => $value)
                            <div>
                                <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $label }}</dt>
                                <dd class="mt-0.5 text-gray-800 font-medium">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </div>

                    {{-- Edit form (hidden by default) --}}
                    <form id="profile-edit-form" class="hidden mt-4 border-t pt-4">
                        @csrf @method('PUT')
                        <div class="grid grid-cols-2 gap-4">
                            @foreach([
                                ['name' => 'name',                    'label' => __('admin.delivery_section.field_name'),              'type' => 'text',   'value' => $agent->name],
                                ['name' => 'phone',                   'label' => __('admin.delivery_section.field_phone'),             'type' => 'text',   'value' => $agent->phone],
                                ['name' => 'national_id',             'label' => __('admin.delivery_section.field_national_id'),       'type' => 'text',   'value' => $agent->national_id],
                                ['name' => 'vehicle_plate',           'label' => __('admin.delivery_section.field_vehicle_plate'),     'type' => 'text',   'value' => $agent->vehicle_plate],
                                ['name' => 'emergency_contact_name',  'label' => __('admin.delivery_section.emergency_name'),    'type' => 'text',   'value' => $agent->emergency_contact_name],
                                ['name' => 'emergency_contact_phone', 'label' => __('admin.delivery_section.emergency_phone'),   'type' => 'text',   'value' => $agent->emergency_contact_phone],
                                ['name' => 'base_salary',       'label' => __('admin.delivery_section.base_salary_label'),'type' => 'number','value' => $agent->base_salary],
                                ['name' => 'per_delivery_fee',  'label' => __('admin.delivery_section.per_delivery_fee_label'),'type' => 'number','value' => $agent->per_delivery_fee],
                            ] as $f)
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $f['label'] }}</label>
                                    <input type="{{ $f['type'] }}" name="{{ $f['name'] }}" value="{{ $f['value'] }}" class="form-input w-full text-sm">
                                </div>
                            @endforeach
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.field_vehicle_type') }}</label>
                                <select name="vehicle_type" class="form-input w-full text-sm">
                                    @foreach(['motorcycle','car','van','bicycle'] as $vt)
                                        <option value="{{ $vt }}" {{ $agent->vehicle_type->value === $vt ? 'selected' : '' }}>{{ ucfirst($vt) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="hidden" name="country_id" value="{{ $agent->country_id }}">
                            <input type="hidden" name="agent_type" value="{{ $agent->agent_type->value }}">
                        </div>
                        <div class="mt-4 flex gap-3">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.save') }}</button>
                            <button type="button" id="cancel-edit-btn" class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                        </div>
                    </form>
                </x-card>
            </div>

            {{-- Zone assignment sidebar --}}
            <div class="col-span-12 lg:col-span-4 space-y-4">
                <x-card title="{{ __('admin.delivery_section.zone_assignment') }}">
                    <form id="zone-form">
                        @csrf
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.assign_to_zone') }}</label>
                        <select name="zone_id" id="zone-select" class="form-input w-full text-sm mb-3">
                            <option value="">{{ __('admin.delivery_section.no_zone') }}</option>
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}" {{ $agent->zone_id === $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }}
                                </option>
                            @endforeach
                        </select>
                        <div id="zone-capacity-info" class="text-xs text-gray-400 mb-3 hidden">
                            <span id="zone-capacity-text"></span>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm w-full">{{ __('admin.delivery_section.update_zone') }}</button>
                    </form>
                </x-card>

                @if($agent->current_latitude && $agent->current_longitude)
                    <x-card title="{{ __('admin.delivery_section.last_known_location') }}">
                        <p class="text-xs text-gray-500 mb-2">{{ __('admin.delivery_section.updated_at_label', ['time' => $agent->last_location_at?->diffForHumans() ?? '—']) }}</p>
                        <div id="agent-mini-map" class="h-48 rounded-lg bg-gray-100 flex items-center justify-center">
                            <span class="text-gray-400 text-sm">
                                {{ $agent->current_latitude }}, {{ $agent->current_longitude }}
                            </span>
                        </div>
                    </x-card>
                @endif
            </div>

        </div>
    </div>

    {{-- ── Assignments Tab ──────────────────────────────────────────────────── --}}
    <div x-show="tab === 'assignments'" x-cloak>
        <x-card>
            <div class="mb-4 flex gap-3">
                <select id="assignment-status-filter" class="form-input text-sm w-40">
                    <option value="">{{ __('admin.delivery_section.all_statuses') }}</option>
                    <option value="assigned">{{ __('admin.delivery_section.assigned') }}</option>
                    <option value="accepted">{{ __('admin.delivery_section.accepted') }}</option>
                    <option value="picked_up">{{ __('admin.delivery_section.picked_up') }}</option>
                    <option value="delivered">{{ __('admin.delivery_section.delivered') }}</option>
                    <option value="failed">{{ __('admin.delivery_section.failed') }}</option>
                </select>
            </div>
            <div class="overflow-x-auto">
            <table id="assignments-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr>
                        <th>{{ __('admin.delivery_section.order_number_col') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('admin.delivery_section.assigned_at_col') }}</th>
                        <th>{{ __('admin.delivery_section.delivered_at_col') }}</th>
                        <th>{{ __('admin.delivery_section.duration_col') }}</th>
                        <th>{{ __('admin.delivery_section.rating_col') }}</th>
                    </tr>
                </thead>
            </table>
            </div>
        </x-card>
    </div>

    {{-- ── Earnings Tab ─────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'earnings'" x-cloak>
        <div class="grid grid-cols-3 gap-4 mb-6" id="earnings-summary-row">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.delivery_section.this_month') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-800" id="earnings-this-month">—</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.delivery_section.last_month') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-800" id="earnings-last-month">—</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.delivery_section.year_to_date') }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-800" id="earnings-ytd">—</p>
            </div>
        </div>
        <x-card title="{{ __('admin.delivery_section.monthly_earnings') }}">
            <canvas id="earnings-chart" height="80"></canvas>
        </x-card>
    </div>

    {{-- ── Documents Tab ────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'documents'" x-cloak>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($agent->documents as $doc)
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="font-medium text-gray-800 text-sm">{{ $doc->label }}</p>
                            @if($doc->expires_at)
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ __('admin.delivery_section.expires_label', ['date' => $doc->expires_at->format('d M Y')]) }}
                                    @if($doc->isExpired())
                                        <span class="text-red-500">{{ __('admin.delivery_section.expired_badge') }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                        <x-badge :color="$docStatusColors[$doc->status->value] ?? 'gray'">
                            {{ $doc->status->label() }}
                        </x-badge>
                    </div>

                    <a href="{{ Storage::url($doc->file_path) }}" target="_blank"
                       class="block w-full text-center text-xs text-primary-600 hover:underline mb-3">
                        {{ __('admin.delivery_section.view_document') }}
                    </a>

                    @if($doc->rejection_reason)
                        <p class="text-xs text-red-600 bg-red-50 rounded p-2 mb-3">{{ $doc->rejection_reason }}</p>
                    @endif

                    @if($doc->status === \App\Enums\DeliveryAgentDocumentStatus::Pending)
                        <div class="flex gap-2">
                            <button type="button" data-doc-id="{{ $doc->id }}" data-action="verify"
                                class="doc-action-btn btn btn-xs btn-success flex-1">{{ __('admin.delivery_section.verify') }}</button>
                            <button type="button" data-doc-id="{{ $doc->id }}" data-action="reject"
                                class="doc-action-btn btn btn-xs btn-danger flex-1">{{ __('admin.delivery_section.reject') }}</button>
                        </div>
                    @endif

                    @if($doc->verified_at)
                        <p class="text-xs text-gray-400 mt-2">
                            {{ __('admin.delivery_section.verified_on', ['date' => $doc->verified_at->format('d M Y')]) }}
                        </p>
                    @endif
                </div>
            @empty
                <div class="col-span-3 text-center py-12 text-gray-400">
                    {{ __('admin.delivery_section.no_documents') }}
                </div>
            @endforelse
        </div>
    </div>

    {{-- ── Shifts Tab ───────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'shifts'" x-cloak>
        <x-card>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase border-b">
                    <tr>
                        <th class="pb-2 text-start">{{ __('admin.delivery_section.date_col') }}</th>
                        <th class="pb-2 text-start">{{ __('admin.delivery_section.zone_col') }}</th>
                        <th class="pb-2 text-start">{{ __('admin.delivery_section.scheduled_col') }}</th>
                        <th class="pb-2 text-start">{{ __('admin.delivery_section.actual_col') }}</th>
                        <th class="pb-2 text-start">{{ __('common.status') }}</th>
                        <th class="pb-2 text-end">{{ __('admin.delivery_section.deliveries_col') }}</th>
                        <th class="pb-2 text-end">{{ __('admin.delivery_section.earnings_col') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($agent->shifts as $shift)
                        @php
                            $shiftColors = ['scheduled'=>'gray','active'=>'primary','completed'=>'success','no_show'=>'warning','cancelled'=>'danger'];
                        @endphp
                        <tr>
                            <td class="py-2">{{ $shift->shift_date->format('d M Y') }}</td>
                            <td class="py-2">{{ $shift->zone?->name ?? '—' }}</td>
                            <td class="py-2 text-xs">{{ $shift->scheduled_start }} – {{ $shift->scheduled_end }}</td>
                            <td class="py-2 text-xs">
                                @if($shift->actual_start)
                                    {{ \Carbon\Carbon::parse($shift->actual_start)->format('H:i') }} –
                                    {{ $shift->actual_end ? \Carbon\Carbon::parse($shift->actual_end)->format('H:i') : '…' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2">
                                <x-badge :color="$shiftColors[$shift->status->value] ?? 'gray'" class="text-xs">
                                    {{ ucfirst(str_replace('_', ' ', $shift->status->value)) }}
                                </x-badge>
                            </td>
                            <td class="py-2 text-end">{{ $shift->total_deliveries }}</td>
                            <td class="py-2 text-end">{{ number_format($shift->total_earnings, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-400">{{ __('admin.delivery_section.no_shifts') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </x-card>
    </div>

</div>

{{-- ─── Reject Document Modal ───────────────────────────────────────────────── --}}
<div id="reject-doc-modal" class="modal-backdrop hidden">
    <div class="modal-box max-w-sm">
        <h3 class="text-lg font-semibold mb-4">{{ __('admin.delivery_section.reject_document') }}</h3>
        <form id="reject-doc-form">
            @csrf
            <input type="hidden" id="reject-doc-id">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.rejection_reason_required') }} <span class="text-red-500">*</span></label>
            <textarea name="reason" rows="3" class="form-input w-full" required placeholder="{{ __('admin.delivery_section.reject_reason_placeholder') }}"></textarea>
            <div class="mt-4 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('reject-doc-modal').classList.add('hidden')"
                    class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                <button type="submit" class="btn btn-danger btn-sm">{{ __('admin.delivery_section.reject') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    updateFailed: @json(__('admin.delivery_section.update_failed')),
    failedGeneric: @json(__('admin.delivery_section.failed_generic')),
    suspensionReasonPrompt: @json(__('admin.delivery_section.suspension_reason_prompt')),
    resetPasswordConfirm: @json(__('admin.delivery_section.reset_password_confirm')),
    tempPasswordAlert: @json(__('admin.delivery_section.temp_password_alert')),
    updateZone: @json(__('admin.delivery_section.update_zone')),
});

document.addEventListener('DOMContentLoaded', function () {
    const agentId        = @json($agent->id);
    const UPDATE_URL     = @json(route('admin.delivery.agents.update', $agent->id));
    const SUSPEND_URL    = @json(route('admin.delivery.agents.suspend', $agent->id));
    const ACTIVATE_URL   = @json(route('admin.delivery.agents.activate', $agent->id));
    const RESET_PWD_URL  = @json(route('admin.delivery.agents.reset-password', $agent->id));
    const ZONE_URL       = @json(route('admin.delivery.agents.assign-zone', $agent->id));
    const ASSIGN_DT_URL  = @json(route('admin.delivery.agents.assignments.datatable', $agent->id));
    const EARNINGS_URL   = @json(route('admin.delivery.agents.earnings-summary', $agent->id));
    const VERIFY_BASE    = @json(url('delivery/documents'));
    const token          = () => $('meta[name=csrf-token]').attr('content');

    // ── Assignments DataTable ────────────────────────────────────────────────
    const assignTable = $('#assignments-table').DataTable({
        processing : true,
        serverSide : true,
        ajax: {
            url    : ASSIGN_DT_URL,
            type   : 'POST',
            data   : d => Object.assign(d, { _token: token(), status: $('#assignment-status-filter').val() }),
        },
        columns: [
            { data: 'sub_order_number' },
            {
                data: 'status',
                render: v => {
                    const c = { assigned:'gray', accepted:'primary', picked_up:'warning', delivered:'success', failed:'danger' }[v] ?? 'gray';
                    return `<span class="badge badge-${c} text-xs capitalize">${v.replace('_',' ')}</span>`;
                }
            },
            { data: 'assigned_at' },
            { data: 'delivered_at' },
            { data: 'duration_minutes', render: v => v ? `${v} min` : '—' },
            { data: 'customer_rating', render: v => v ? `${'★'.repeat(v)}` : '—' },
        ],
        order: [[2, 'desc']],
    });
    $('#assignment-status-filter').on('change', () => assignTable.ajax.reload());

    // ── Earnings ─────────────────────────────────────────────────────────────
    let earningsChartInstance = null;
    $('[\\@click="tab = \'earnings\'"]').on('click', loadEarnings);
    function loadEarnings () {
        if (earningsChartInstance) return;
        $.getJSON(EARNINGS_URL, data => {
            const fmt = amount => Number(amount).toFixed(2);
            $('#earnings-this-month').text(fmt(data.summary.this_month));
            $('#earnings-last-month').text(fmt(data.summary.last_month));
            $('#earnings-ytd').text(fmt(data.summary.ytd));

            const labels = data.monthly.map(r => `${r.year}-${String(r.month).padStart(2,'0')}`);
            const values = data.monthly.map(r => r.total);

            earningsChartInstance = new Chart(document.getElementById('earnings-chart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Earnings',
                        data: values,
                        backgroundColor: 'rgba(79,70,229,0.7)',
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } },
                },
            });
        });
    }

    // ── Profile Edit ─────────────────────────────────────────────────────────
    $('#edit-profile-btn').on('click', () => {
        $('#profile-view').addClass('hidden');
        $('#profile-edit-form').removeClass('hidden');
    });
    $('#cancel-edit-btn').on('click', () => {
        $('#profile-view').removeClass('hidden');
        $('#profile-edit-form').addClass('hidden');
    });
    $('#profile-edit-form').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url    : UPDATE_URL,
            method : 'POST',
            data   : $(this).serialize() + '&_method=PUT&_token=' + token(),
            success: res => { if (res.success) { window.Toast?.success(res.message); location.reload(); } },
            error  : xhr => window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.updateFailed),
        });
    });

    // ── Zone Form ─────────────────────────────────────────────────────────────
    const zoneCapacities = @json(
        $zones->mapWithKeys(fn($z) => [
            $z->id => [
                'max'   => $z->max_active_agents,
                'count' => $z->agents_count,
            ]
        ])
    );

    $('#zone-select').on('change', function () {
        const id   = this.value;
        const info = zoneCapacities[id];
        const $box = $('#zone-capacity-info');
        if (!id || !info) { $box.addClass('hidden'); return; }
        const full = info.max && info.count >= info.max;
        $box.removeClass('hidden')
            .find('#zone-capacity-text')
            .html(info.max
                ? `${info.count} / ${info.max} agents${full ? ' — <span class="text-red-500 font-semibold">at capacity</span>' : ''}`
                : `${info.count} agent(s) — no cap`
            );
    }).trigger('change');

    $('#zone-form').on('submit', function (e) {
        e.preventDefault();
        const btn = $(this).find('button[type=submit]');
        btn.prop('disabled', true).text('...');
        $.ajax({
            url    : ZONE_URL,
            method : 'POST',
            data   : { _token: token(), zone_id: $('#zone-select').val() },
            success: res => {
                if (res.success) {
                    window.Toast?.success(res.message);
                    setTimeout(() => location.reload(), 800);
                }
            },
            error  : xhr => {
                window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.failedGeneric);
                btn.prop('disabled', false).text(window.TRANSLATIONS.updateZone);
            },
        });
    });

    // ── Suspend / Activate ────────────────────────────────────────────────────
    $('#suspend-btn').on('click', () => {
        const reason = prompt(window.TRANSLATIONS.suspensionReasonPrompt);
        if (!reason) return;
        $.ajax({
            url    : SUSPEND_URL,
            method : 'POST',
            data   : { _token: token(), reason },
            success: res => { if (res.success) { window.Toast?.success(res.message); location.reload(); } },
        });
    });
    $('#activate-btn').on('click', () => {
        $.ajax({
            url    : ACTIVATE_URL,
            method : 'POST',
            data   : { _token: token() },
            success: res => { if (res.success) { window.Toast?.success(res.message); location.reload(); } },
        });
    });

    // ── Reset Password ────────────────────────────────────────────────────────
    $('#reset-pwd-btn').on('click', () => {
        if (!confirm(window.TRANSLATIONS.resetPasswordConfirm)) return;
        $.ajax({
            url    : RESET_PWD_URL,
            method : 'POST',
            data   : { _token: token() },
            success: res => {
                if (res.success) {
                    alert(window.TRANSLATIONS.tempPasswordAlert.replace(':password', res.temp_password));
                }
            },
        });
    });

    // ── Document Actions ──────────────────────────────────────────────────────
    $(document).on('click', '.doc-action-btn', function () {
        const docId  = $(this).data('doc-id');
        const action = $(this).data('action');

        if (action === 'verify') {
            $.ajax({
                url    : `${VERIFY_BASE}/${docId}/verify`,
                method : 'POST',
                data   : { _token: token() },
                success: res => { if (res.success) { window.Toast?.success(res.message); location.reload(); } },
            });
        } else {
            $('#reject-doc-id').val(docId);
            document.getElementById('reject-doc-modal').classList.remove('hidden');
        }
    });

    $('#reject-doc-form').on('submit', function (e) {
        e.preventDefault();
        const docId = $('#reject-doc-id').val();
        $.ajax({
            url    : `${VERIFY_BASE}/${docId}/reject`,
            method : 'POST',
            data   : $(this).serialize() + '&_token=' + token(),
            success: res => {
                if (res.success) {
                    window.Toast?.success(res.message);
                    document.getElementById('reject-doc-modal').classList.add('hidden');
                    location.reload();
                }
            },
        });
    });
})();
</script>
@endpush
