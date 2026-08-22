@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.subscriptions.subscription_invoices_title'))

@section('content')


    {{-- ─── Stats ───────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.total') }}</p>
            <p class="text-2xl font-extrabold text-gray-800">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-yellow-500 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.open') }}</p>
            <p class="text-2xl font-extrabold text-yellow-600">{{ number_format($stats['open']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-green-500 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.paid') }}</p>
            <p class="text-2xl font-extrabold text-green-600">{{ number_format($stats['paid']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-yellow-50 bg-yellow-50 p-4 text-center">
            <p class="text-xs text-yellow-600 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.open_revenue') }}</p>
            <p class="text-2xl font-extrabold text-yellow-700">{{ number_format($stats['open_sum']) }} EGP</p>
        </div>
        <div class="bg-white rounded-2xl border border-green-50 bg-green-50 p-4 text-center">
            <p class="text-xs text-green-600 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.paid_revenue') }}</p>
            <p class="text-2xl font-extrabold text-green-700">{{ number_format($stats['paid_sum']) }} EGP</p>
        </div>
    </div>

    {{-- ─── Filter ───────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="label-sm">{{ __('admin.subscriptions.status') }}</label>
                <select id="filter-status" class="form-select text-sm py-1.5 pr-8">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="open">{{ __('admin.subscriptions.open') }}</option>
                    <option value="paid">{{ __('admin.subscriptions.paid') }}</option>
                    <option value="void">{{ __('admin.subscriptions.void') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
        <table id="tbl-invoices" class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.invoice_number') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.vendor') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.plan') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.amount') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.period') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.paid_at') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.actions') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        </div>
    </div>

    {{-- ─── Mark Paid Modal ─────────────────────────────────────────────────────── --}}
    <div id="mark-paid-modal" class="modal" style="display:none;">
        <div class="modal-box max-w-sm">
            <h3 class="font-bold text-lg mb-4">{{ __('admin.subscriptions.mark_invoice_paid') }}</h3>
            <input type="hidden" id="mp-invoice-id">
            <div>
                <label class="label-sm">{{ __('admin.subscriptions.payment_transaction_id_optional') }}</label>
                <input type="text" id="mp-txid" class="form-input w-full text-sm" placeholder="{{ __('admin.subscriptions.payment_transaction_id_placeholder') }}">
            </div>
            <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-gray-100">
                <button type="button" id="mp-close" class="btn btn-ghost btn-sm">{{ __('admin.subscriptions.close') }}</button>
                <button type="button" id="mp-confirm" class="btn btn-success btn-sm">{{ __('admin.subscriptions.mark_paid') }}</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            loading: @json(__('admin.subscriptions.loading')),
        });

        document.addEventListener('DOMContentLoaded', function () {
            const tok = '{{ csrf_token() }}';

            const tbl = $('#tbl-invoices').DataTable({
                serverSide: true,
                processing: true,
                pageLength: 25,
                order: [[0, 'desc']],
                ajax: {
                    url: '{{ route('admin.subscriptions.invoices.datatable') }}',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok },
                    data: d => { d.status = $('#filter-status').val(); },
                },
                columns: [
                    { data: 'invoice_number', orderable: false },
                    { data: 'vendor', orderable: false },
                    { data: 'plan', orderable: false },
                    { data: 'amount', orderable: false },
                    { data: 'status', orderable: false },
                    { data: 'period', orderable: false },
                    { data: 'paid_at', orderable: false },
                    { data: 'actions', orderable: false },
                ],
                language: { processing: window.TRANSLATIONS.loading },
            });

            $('#filter-status').on('change', () => tbl.ajax.reload());

            // ── Mark paid ──────────────────────────────────────────────────────────────
            $(document).on('click', '.btn-mark-paid', function () {
                $('#mp-invoice-id').val($(this).data('id'));
                $('#mp-txid').val('');
                $('#mark-paid-modal').show();
            });
            $('#mp-close').on('click', () => $('#mark-paid-modal').hide());
            $('#mp-confirm').on('click', function () {
                const id = $('#mp-invoice-id').val();
                fetch('/admin/subscriptions/invoices/' + id + '/mark-paid', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payment_transaction_id: $('#mp-txid').val() }),
                }).then(r => r.json()).then(data => {
                    if (data.success) { window.Toast.success(data.message); $('#mark-paid-modal').hide(); tbl.ajax.reload(); }
                    else { window.Toast.error(data.message); }
                });
            });
        }, { once: true });
    </script>
@endpush
