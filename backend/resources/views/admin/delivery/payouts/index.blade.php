@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@section('title', __('admin.delivery_section.payouts'))

@section('content')

{{-- ─── Header ──────────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.delivery_section.payouts') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.delivery_section.payouts_desc') }}</p>
    </div>
    <button type="button" id="generate-btn" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.generate_payouts') }}</button>
</div>

{{-- ─── Stats Row ───────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['label' => __('admin.delivery_section.total_payouts'),  'value' => number_format($stats['total'])],
        ['label' => __('admin.delivery_section.pending'),        'value' => number_format($stats['pending'])],
        ['label' => __('admin.delivery_section.approved'),       'value' => number_format($stats['approved'])],
        ['label' => __('admin.delivery_section.paid'),           'value' => number_format($stats['paid'])],
    ] as $stat)
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-800">{{ $stat['value'] }}</p>
        </div>
    @endforeach
</div>

{{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
<x-card class="mb-5">
    <div class="flex flex-wrap gap-3 items-end">
        <div class="w-40">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
            <select id="filter-status" class="form-input w-full text-sm">
                <option value="">{{ __('common.all') }}</option>
                <option value="pending">{{ __('admin.delivery_section.pending') }}</option>
                <option value="approved">{{ __('admin.delivery_section.approved') }}</option>
                <option value="paid">{{ __('admin.delivery_section.paid') }}</option>
                <option value="failed">{{ __('admin.delivery_section.failed') }}</option>
            </select>
        </div>
        <div class="w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.agent_col') }}</label>
            <select id="filter-agent" class="form-input w-full text-sm">
                <option value="">{{ __('admin.delivery_section.all_agents') }}</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.period_from') }}</label>
            <input type="date" id="filter-from" class="form-input text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.period_to') }}</label>
            <input type="date" id="filter-to" class="form-input text-sm">
        </div>
        <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('common.reset') }}</button>
    </div>
</x-card>

{{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
<x-card>
    <div class="overflow-x-auto">
    <table id="payouts-table" class="w-full text-sm" style="width:100%">
        <thead>
            <tr>
                <th>{{ __('admin.delivery_section.payout_number_col') }}</th>
                <th>{{ __('admin.delivery_section.agent_col') }}</th>
                <th>{{ __('admin.delivery_section.period_col') }}</th>
                <th>{{ __('admin.delivery_section.deliveries_col') }}</th>
                <th>{{ __('admin.delivery_section.gross_col') }}</th>
                <th>{{ __('admin.delivery_section.deductions_col') }}</th>
                <th>{{ __('admin.delivery_section.net_col') }}</th>
                <th>{{ __('admin.delivery_section.status_col') }}</th>
                <th>{{ __('admin.delivery_section.approved_by_col') }}</th>
                <th>{{ __('admin.delivery_section.processed_at_col') }}</th>
                <th>{{ __('admin.delivery_section.actions_col') }}</th>
            </tr>
        </thead>
    </table>
    </div>
</x-card>

{{-- ─── Generate Payouts Modal ──────────────────────────────────────────────── --}}
<div id="generate-modal" class="modal-backdrop hidden">
    <div class="modal-box max-w-md">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">{{ __('admin.delivery_section.generate_payouts_title') }}</h3>
            <button type="button" onclick="document.getElementById('generate-modal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <form id="generate-form" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.period_start_required') }} <span class="text-red-500">*</span></label>
                    <input type="date" name="period_start" class="form-input w-full" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.period_end_required') }} <span class="text-red-500">*</span></label>
                    <input type="date" name="period_end" class="form-input w-full" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.agent_col') }}</label>
                <select name="agent_id" class="form-input w-full">
                    <option value="">{{ __('admin.delivery_section.all_eligible_agents') }}</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">{{ __('admin.delivery_section.generate_agent_hint') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.currency_required') }} <span class="text-red-500">*</span></label>
                <select name="currency" class="form-input w-full" required>
                    @foreach($currencies as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pt-4 border-t flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('generate-modal').classList.add('hidden')"
                    class="btn btn-ghost btn-sm">{{ __('admin.delivery_section.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.generate') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- ─── Process Payout Modal ────────────────────────────────────────────────── --}}
<div id="process-modal" class="modal-backdrop hidden">
    <div class="modal-box max-w-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">{{ __('admin.delivery_section.process_payout') }}</h3>
            <button type="button" onclick="document.getElementById('process-modal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <form id="process-form" class="space-y-4">
            @csrf
            <input type="hidden" id="process-payout-id">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.payment_method_required') }} <span class="text-red-500">*</span></label>
                <select name="payment_method" class="form-input w-full" required>
                    <option value="">{{ __('admin.delivery_section.select_method') }}</option>
                    <option value="bank_transfer">{{ __('admin.delivery_section.bank_transfer') }}</option>
                    <option value="cash">{{ __('admin.delivery_section.cash') }}</option>
                    <option value="mobile_wallet">{{ __('admin.delivery_section.mobile_wallet') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.payment_reference') }}</label>
                <input type="text" name="payment_reference" class="form-input w-full" placeholder="{{ __('admin.delivery_section.payment_reference_placeholder') }}">
            </div>
            <div class="pt-4 border-t flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('process-modal').classList.add('hidden')"
                    class="btn btn-ghost btn-sm">{{ __('admin.delivery_section.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.mark_as_paid') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    approve: @json(__('admin.delivery_section.approve_btn')),
    process: @json(__('admin.delivery_section.process_btn')),
    generationFailed: @json(__('admin.delivery_section.generation_failed')),
    failedGeneric: @json(__('admin.delivery_section.failed_generic')),
    approvePayoutConfirm: @json(__('admin.delivery_section.approve_payout_confirm')),
    processingFailed: @json(__('admin.delivery_section.processing_failed')),
});
</script>
<script type="module">
(function () {
    const DATATABLE_URL = @json(route('admin.delivery.payouts.datatable'));
    const GENERATE_URL  = @json(route('admin.delivery.payouts.generate'));
    const BASE_URL      = @json(url('delivery/payouts'));
    const token         = () => $('meta[name=csrf-token]').attr('content');

    // ── DataTable ─────────────────────────────────────────────────────────────
    const table = $('#payouts-table').DataTable({
        processing : true,
        serverSide : true,
        ajax: {
            url  : DATATABLE_URL,
            type : 'POST',
            data : d => Object.assign(d, {
                _token   : token(),
                status   : $('#filter-status').val(),
                agent_id : $('#filter-agent').val(),
                from     : $('#filter-from').val(),
                to       : $('#filter-to').val(),
            }),
        },
        columns: [
            { data: 'payout_number', render: v => `<code class="text-xs font-mono">${v}</code>` },
            { data: 'agent_name' },
            { data: 'period', render: (v, t, r) => `${r.period_start} – ${r.period_end}` },
            { data: 'total_deliveries' },
            { data: 'gross_earnings',  render: v => (v).toFixed(2) },
            { data: 'deductions',      render: v => (v).toFixed(2) },
            { data: 'net_amount',      render: v => `<strong>${(v).toFixed(2)}</strong>` },
            {
                data: 'status',
                render: (v, t, r) => {
                    const c = { pending:'warning', approved:'primary', paid:'success', failed:'danger' }[v] ?? 'gray';
                    return `<span class="badge badge-${c} text-xs">${r.status_label ?? v}</span>`;
                }
            },
            { data: 'approved_by', defaultContent: '—' },
            { data: 'processed_at', defaultContent: '—' },
            {
                data: null, orderable: false,
                render: r => {
                    const btns = [];
                    if (r.status === 'pending') {
                        btns.push(`<button type="button" class="approve-btn btn btn-xs btn-success" data-id="${r.id}">${window.TRANSLATIONS.approve}</button>`);
                    }
                    if (r.status === 'approved') {
                        btns.push(`<button type="button" class="process-btn btn btn-xs btn-primary" data-id="${r.id}">${window.TRANSLATIONS.process}</button>`);
                    }
                    return btns.join(' ') || '—';
                }
            },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
    });

    // Filters
    ['#filter-status','#filter-agent','#filter-from','#filter-to'].forEach(sel =>
        $(sel).on('change', () => table.ajax.reload())
    );
    $('#clear-filters').on('click', () => {
        $('#filter-status, #filter-agent').val('');
        $('#filter-from, #filter-to').val('');
        table.ajax.reload();
    });

    // ── Generate Payouts ──────────────────────────────────────────────────────
    $('#generate-btn').on('click', () =>
        document.getElementById('generate-modal').classList.remove('hidden')
    );
    $('#generate-form').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url    : GENERATE_URL,
            method : 'POST',
            data   : $(this).serialize() + '&_token=' + token(),
            success: res => {
                if (res.success) {
                    window.Toast?.success(res.message);
                    document.getElementById('generate-modal').classList.add('hidden');
                    this.reset();
                    table.ajax.reload();
                }
            },
            error: xhr => window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.generationFailed),
        });
    });

    // ── Approve ───────────────────────────────────────────────────────────────
    $(document).on('click', '.approve-btn', function () {
        const id = $(this).data('id');
        if (!confirm(window.TRANSLATIONS.approvePayoutConfirm)) return;
        $.ajax({
            url    : `${BASE_URL}/${id}/approve`,
            method : 'POST',
            data   : { _token: token() },
            success: res => { if (res.success) { window.Toast?.success(res.message); table.ajax.reload(); } },
            error  : xhr => window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.failedGeneric),
        });
    });

    // ── Process ───────────────────────────────────────────────────────────────
    $(document).on('click', '.process-btn', function () {
        $('#process-payout-id').val($(this).data('id'));
        document.getElementById('process-modal').classList.remove('hidden');
    });
    $('#process-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#process-payout-id').val();
        $.ajax({
            url    : `${BASE_URL}/${id}/process`,
            method : 'POST',
            data   : $(this).serialize() + '&_token=' + token(),
            success: res => {
                if (res.success) {
                    window.Toast?.success(res.message);
                    document.getElementById('process-modal').classList.add('hidden');
                    this.reset();
                    table.ajax.reload();
                }
            },
            error: xhr => window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.processingFailed),
        });
    });
})();
</script>
@endpush
