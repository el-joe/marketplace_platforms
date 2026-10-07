@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.fbn_section.storage_fees_title'))

@section('content')


    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.fbn_section.storage_fees_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.fbn_section.storage_fees_desc') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.fbn.storage-fees.free-period-rules.index') }}" class="btn btn-secondary btn-sm">
                {{ __('admin.fbn_section.free_period_rules_title') }}
            </a>
            <button type="button" id="btn-generate-fees" class="btn btn-primary btn-sm">
                {{ __('admin.fbn_section.generate_monthly_fees') }}
            </button>
        </div>
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-3 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.total_records') }}</p>
            <p class="text-xl font-extrabold text-gray-700">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-3 text-center">
            <p class="text-xs text-yellow-500 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.pending') }}</p>
            <p class="text-xl font-extrabold text-yellow-600">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-3 text-center">
            <p class="text-xs text-blue-500 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.invoiced') }}</p>
            <p class="text-xl font-extrabold text-blue-600">{{ number_format($stats['invoiced']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-3 text-center">
            <p class="text-xs text-green-500 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.paid') }}</p>
            <p class="text-xl font-extrabold text-green-600">{{ number_format($stats['paid']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-yellow-50 bg-yellow-50 p-3 text-center">
            <p class="text-xs text-yellow-600 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.pending_revenue') }}</p>
            <p class="text-xl font-extrabold text-yellow-700">{{ number_format($stats['pending_revenue']) }} {{ $stats['currency'] ?? '' }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-green-50 bg-green-50 p-3 text-center">
            <p class="text-xs text-green-600 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.paid_revenue') }}</p>
            <p class="text-xl font-extrabold text-green-700">{{ number_format($stats['paid_revenue']) }} {{ $stats['currency'] ?? '' }}</p>
        </div>
    </div>

    {{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="label-sm">{{ __('admin.fbn_section.status') }}</label>
            <select id="filter-status" class="form-select text-sm py-1.5 pr-8">
                <option value="">{{ __('admin.fbn_section.all') }}</option>
                <option value="pending">{{ __('admin.fbn_section.pending') }}</option>
                <option value="invoiced">{{ __('admin.fbn_section.invoiced') }}</option>
                <option value="paid">{{ __('admin.fbn_section.paid') }}</option>
            </select>
        </div>
        <div>
            <label class="label-sm">{{ __('admin.fbn_section.month') }}</label>
            <select id="filter-month" class="form-select text-sm py-1.5 pr-8">
                <option value="">{{ __('admin.fbn_section.all_months') }}</option>
                @foreach($months as $m)
                    <option value="{{ $m->month_key }}">{{ $m->month_label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- ─── Storage days info note ──────────────────────────────────────────────── --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 mb-4 flex items-start gap-2 text-sm text-blue-800">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        <span>{{ __('admin.fbn_section.storage_days_note') }}</span>
    </div>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tbl-fees" class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.vendor') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.product') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.month') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.units_stored') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.actual_weight') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.volumetric_weight') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.chargeable_weight') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.arrival_date') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.days_in_storage') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.in_free_period') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.rate_per_unit') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.total_fee') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.status') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- ─── Generate Modal ──────────────────────────────────────────────────────── --}}
    <div id="generate-modal" class="modal" style="display:none;">
        <div class="modal-box max-w-sm">
            <h3 class="font-bold text-lg mb-4">{{ __('admin.fbn_section.generate_monthly_storage_fees') }}</h3>
            <p class="text-sm text-gray-500 mb-3">
                {{ __('admin.fbn_section.generate_fees_desc') }}
            </p>
            <div>
                <label class="label-sm">{{ __('admin.fbn_section.month') }} <span class="text-red-500">*</span></label>
                <input type="month" id="gen-month" class="form-input w-full text-sm" value="{{ now()->format('Y-m') }}">
            </div>
            <div class="flex gap-3 justify-end mt-5 pt-4 border-t">
                <button type="button" id="gen-close" class="btn btn-ghost btn-sm">{{ __('admin.fbn_section.cancel') }}</button>
                <button type="button" id="gen-confirm" class="btn btn-primary btn-sm">{{ __('admin.fbn_section.queue_job') }}</button>
            </div>
            <div id="gen-progress" class="hidden mt-4 pt-4 border-t text-sm text-gray-600 flex items-center gap-2">
                <span class="loading loading-spinner loading-sm"></span>
                <span id="gen-progress-text">{{ __('admin.fbn_section.generation_in_progress') }}</span>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            loading: @json(__('admin.fbn_section.loading')),
            error: @json(__('admin.fbn_section.error')),
            selectMonth: @json(__('admin.fbn_section.select_month')),
            generationInProgress: @json(__('admin.fbn_section.generation_in_progress')),
            generationComplete: @json(__('admin.fbn_section.generation_complete')),
            generationFailed: @json(__('admin.fbn_section.generation_failed')),
        });

        document.addEventListener('DOMContentLoaded', function () {
            const tok = '{{ csrf_token() }}';
            const T = window.TRANSLATIONS;

            const tbl = $('#tbl-fees').DataTable({
                serverSide: true,
                processing: true,
                pageLength: 25,
                order: [[1, 'desc']],
                ajax: {
                    url: '{{ route('admin.fbn.storage-fees.datatable') }}',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok },
                    data: d => {
                        d.status = $('#filter-status').val();
                        d.month = $('#filter-month').val();
                    },
                },
                columns: [
                    { data: 'vendor', orderable: false },
                    { data: 'product_name', orderable: false },
                    { data: 'month', orderable: true },
                    { data: 'units_stored', orderable: false },
                    { data: 'actual_weight', orderable: false },
                    { data: 'volumetric_weight', orderable: false },
                    { data: 'chargeable_weight', orderable: false },
                    { data: 'stored_since', orderable: false },
                    { data: 'days_in_storage', orderable: false },
                    { data: 'in_free_period', orderable: false },
                    { data: 'rate', orderable: false },
                    { data: 'total_fee', orderable: false },
                    { data: 'status', orderable: false },
                    { data: 'actions', orderable: false },
                ],
                language: { processing: T.loading },
            });

            $('#filter-status, #filter-month').on('change', () => tbl.ajax.reload());

            function jsonPost(url, body, onSuccess) {
                fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                }).then(r => r.json()).then(data => {
                    if (data.success) { window.Toast.success(data.message); onSuccess?.(); tbl.ajax.reload(); }
                    else { window.Toast.error(data.message ?? T.error); }
                });
            }

            // ── Status updates ─────────────────────────────────────────────────────────
            $(document).on('click', '.btn-mark-invoiced', function () {
                jsonPost(`/fbn/storage-fees/${$(this).data('id')}/status`, { status: 'invoiced' });
            });
            $(document).on('click', '.btn-mark-fee-paid', function () {
                jsonPost(`/fbn/storage-fees/${$(this).data('id')}/status`, { status: 'paid' });
            });

            // ── Generate monthly fees ──────────────────────────────────────────────────
            let genPollTimer = null;

            function stopGenPolling() {
                if (genPollTimer) { clearInterval(genPollTimer); genPollTimer = null; }
            }

            function pollGenerationStatus(month) {
                fetch(`{{ route('admin.fbn.storage-fees.generation-status') }}?month=${encodeURIComponent(month)}`)
                    .then(r => r.json())
                    .then(status => {
                        if (status.state === 'done') {
                            stopGenPolling();
                            $('#gen-progress').addClass('hidden');
                            window.Toast.success(T.generationComplete
                                .replace(':created', status.created)
                                .replace(':free', status.free)
                                .replace(':skipped', status.skipped));
                            tbl.ajax.reload();
                        } else if (status.state === 'failed') {
                            stopGenPolling();
                            $('#gen-progress').addClass('hidden');
                            window.Toast.error(T.generationFailed);
                        }
                        // 'running' / 'unknown' → keep polling
                    });
            }

            $('#btn-generate-fees').on('click', () => $('#generate-modal').show());
            $('#gen-close').on('click', () => $('#generate-modal').hide());
            $('#gen-confirm').on('click', () => {
                const month = $('#gen-month').val();
                if (!month) { window.Toast.error(T.selectMonth); return; }
                $('#gen-progress').removeClass('hidden');
                $('#gen-progress-text').text(T.generationInProgress);
                jsonPost('{{ route('admin.fbn.storage-fees.generate') }}', { month }, () => {
                    stopGenPolling();
                    genPollTimer = setInterval(() => pollGenerationStatus(month), 1500);
                });
            });
        }, { once: true });
    </script>
@endpush
