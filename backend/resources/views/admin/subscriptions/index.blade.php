@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.subscriptions.title'))

@section('content')


    {{-- ─── Stats ───────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.total') }}</p>
            <p class="text-2xl font-extrabold text-gray-800">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-green-500 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.active') }}</p>
            <p class="text-2xl font-extrabold text-green-600">{{ number_format($stats['active']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.expired') }}</p>
            <p class="text-2xl font-extrabold text-gray-600">{{ number_format($stats['expired']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
            <p class="text-xs text-red-400 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.cancelled') }}</p>
            <p class="text-2xl font-extrabold text-red-500">{{ number_format($stats['cancelled']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-green-50 p-4 text-center bg-green-50">
            <p class="text-xs text-green-600 uppercase tracking-wide mb-1">{{ __('admin.subscriptions.mrr') }}</p>
            <p class="text-2xl font-extrabold text-green-700">{{ number_format($stats['mrr']) }} {{ $stats['mrr_currency'] ?? '' }}</p>
        </div>
    </div>

    {{-- ─── Filters + Actions ───────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-3 items-end justify-between">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="label-sm">{{ __('admin.subscriptions.status') }}</label>
                <select id="filter-status" class="form-select text-sm py-1.5 pr-8">
                    <option value="">{{ __('admin.subscriptions.all_statuses') }}</option>
                    <option value="active">{{ __('admin.subscriptions.active') }}</option>
                    <option value="trialing">{{ __('admin.subscriptions.trialing') }}</option>
                    <option value="past_due">{{ __('admin.subscriptions.past_due') }}</option>
                    <option value="cancelled">{{ __('admin.subscriptions.cancelled') }}</option>
                    <option value="expired">{{ __('admin.subscriptions.expired') }}</option>
                </select>
            </div>
            <div>
                <label class="label-sm">{{ __('admin.subscriptions.plan') }}</label>
                <select id="filter-plan" class="form-select text-sm py-1.5 pr-8">
                    <option value="">{{ __('admin.subscriptions.all_plans') }}</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name_en }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button type="button" id="btn-subscribe-vendor" class="btn btn-primary btn-sm">+ {{ __('admin.subscriptions.subscribe_vendor') }}</button>
    </div>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
        <table id="tbl-subscriptions" class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.vendor') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.plan') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.period') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.listings') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.auto_renew') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('admin.subscriptions.actions') }}</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        </div>
    </div>

    {{-- ─── Subscribe Vendor Modal ──────────────────────────────────────────────── --}}
    <div id="subscribe-modal" class="modal" style="display:none;">
        <div class="modal-box max-w-sm">
            <h3 class="font-bold text-lg mb-4">{{ __('admin.subscriptions.subscribe_a_vendor') }}</h3>

            <div class="space-y-3">
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.vendor_id_or_search') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="sv-vendor-id" class="form-input w-full text-sm"
                        placeholder="{{ __('admin.subscriptions.vendor_id_placeholder') }}">
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.subscriptions.plan') }} <span class="text-red-500">*</span></label>
                    <select id="sv-plan-id" class="form-select w-full text-sm">
                        <option value="">{{ __('admin.subscriptions.select_plan') }}</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name_en }} — {{ number_format($plan->price) }}
                                {{ $plan->currency }}/mo</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-gray-100">
                <button type="button" id="subscribe-modal-cancel" class="btn btn-ghost btn-sm">{{ __('admin.subscriptions.cancel') }}</button>
                <button type="button" id="subscribe-modal-save" class="btn btn-primary btn-sm px-8">{{ __('admin.subscriptions.subscribe') }}</button>
            </div>
        </div>
    </div>

    {{-- ─── Cancel Subscription Modal ───────────────────────────────────────────── --}}
    <div id="cancel-sub-modal" class="modal" style="display:none;">
        <div class="modal-box max-w-sm">
            <h3 class="font-bold text-lg mb-4">{{ __('admin.subscriptions.cancel_subscription') }}</h3>
            <input type="hidden" id="cancel-sub-id">
            <div>
                <label class="label-sm">{{ __('admin.subscriptions.reason_optional') }}</label>
                <textarea id="cancel-sub-reason" rows="3" class="form-input w-full text-sm"
                    placeholder="{{ __('admin.subscriptions.cancellation_reason_placeholder') }}"></textarea>
            </div>
            <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-gray-100">
                <button type="button" id="cancel-sub-modal-close" class="btn btn-ghost btn-sm">{{ __('admin.subscriptions.close') }}</button>
                <button type="button" id="cancel-sub-modal-confirm" class="btn btn-danger btn-sm">{{ __('admin.subscriptions.cancel_subscription') }}</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            loading: @json(__('admin.subscriptions.loading')),
            error: @json(__('admin.subscriptions.error')),
        });

        document.addEventListener('DOMContentLoaded', function () {
            const tok = '{{ csrf_token() }}';

            const tbl = $('#tbl-subscriptions').DataTable({
                serverSide: true,
                processing: true,
                pageLength: 25,
                order: [[3, 'desc']],
                ajax: {
                    url: '{{ route('admin.subscriptions.datatable') }}',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok },
                    data: d => {
                        d.status = $('#filter-status').val();
                        d.plan_id = $('#filter-plan').val();
                    },
                },
                columns: [
                    { data: 'vendor', orderable: false },
                    { data: 'plan', orderable: false },
                    { data: 'status', orderable: false },
                    { data: 'period', orderable: false },
                    { data: 'listings', orderable: false },
                    { data: 'auto_renew', orderable: false },
                    { data: 'actions', orderable: false },
                ],
                language: { processing: window.TRANSLATIONS.loading },
            });

            $('#filter-status, #filter-plan').on('change', () => tbl.ajax.reload());

            // ── Subscribe vendor ───────────────────────────────────────────────────────
            $('#btn-subscribe-vendor').on('click', () => {
                $('#sv-vendor-id').val('');
                $('#sv-plan-id').val('');
                $('#subscribe-modal').show();
            });
            $('#subscribe-modal-cancel').on('click', () => $('#subscribe-modal').hide());
            $('#subscribe-modal-save').on('click', function () {
                fetch('{{ route('admin.subscriptions.subscribe-vendor') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        vendor_id: $('#sv-vendor-id').val(),
                        plan_id: $('#sv-plan-id').val(),
                    }),
                }).then(r => r.json()).then(data => {
                    if (data.success) { window.Toast.success(data.message); $('#subscribe-modal').hide(); tbl.ajax.reload(); }
                    else { window.Toast.error(data.message ?? window.TRANSLATIONS.error); }
                });
            });

            // ── Cancel subscription ────────────────────────────────────────────────────
            $(document).on('click', '.btn-cancel-sub', function () {
                $('#cancel-sub-id').val($(this).data('id'));
                $('#cancel-sub-reason').val('');
                $('#cancel-sub-modal').show();
            });
            $('#cancel-sub-modal-close').on('click', () => $('#cancel-sub-modal').hide());
            $('#cancel-sub-modal-confirm').on('click', function () {
                const id = $('#cancel-sub-id').val();
                fetch('/admin/subscriptions/' + id + '/cancel', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reason: $('#cancel-sub-reason').val() }),
                }).then(r => r.json()).then(data => {
                    if (data.success) { window.Toast.success(data.message); $('#cancel-sub-modal').hide(); tbl.ajax.reload(); }
                    else { window.Toast.error(data.message); }
                });
            });
        });
    </script>
@endpush
