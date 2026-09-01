@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.fbn_section.marketplace_rules_title'))

@section('content')


    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.fbn_section.marketplace_rules_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.fbn_section.marketplace_rules_desc') }}</p>
        </div>
        <button type="button" id="btn-create-rule" class="btn btn-primary btn-sm">{{ __('admin.fbn_section.new_rule') }}</button>
    </div>

    {{-- ─── Stats ───────────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-3 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.total_rules') }}</p>
            <p class="text-xl font-extrabold text-gray-700">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-3 text-center">
            <p class="text-xs text-orange-400 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.special_vehicle') }}</p>
            <p class="text-xl font-extrabold text-orange-600">{{ number_format($stats['special_vehicle']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-3 text-center">
            <p class="text-xs text-blue-400 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.refrigerated') }}</p>
            <p class="text-xl font-extrabold text-blue-600">{{ number_format($stats['refrigerated']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-3 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">{{ __('admin.fbn_section.fixed_commission') }}</p>
            <p class="text-xl font-extrabold text-gray-700">{{ number_format($stats['fixed_commission']) }}</p>
        </div>
    </div>

    {{-- ─── Filter ───────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="label-sm">{{ __('admin.fbn_section.commission_type') }}</label>
                <select id="filter-commission-type" class="form-select text-sm py-1.5 pr-8">
                    <option value="">{{ __('admin.fbn_section.all') }}</option>
                    <option value="fixed">{{ __('admin.fbn_section.fixed') }}</option>
                    <option value="percentage">{{ __('admin.fbn_section.percentage') }}</option>
                    <option value="mixed">{{ __('admin.fbn_section.mixed') }}</option>
                </select>
            </div>
        </div>
    </div>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="tbl-rules" class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.listing_id') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.vendor') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.product') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.requirements') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.max_weight') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.commission') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.extra_fee') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('admin.fbn_section.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- ─── Create / Edit Modal ─────────────────────────────────────────────────── --}}
    <div id="rule-modal" class="modal" style="display:none;">
        <div class="modal-box max-w-xl">
            <h3 class="font-bold text-lg mb-5" id="rule-modal-title">{{ __('admin.fbn_section.new_marketplace_rule') }}</h3>
            <input type="hidden" id="rm-id">

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="label-sm">{{ __('admin.fbn_section.vendor_listing_id') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="rm-listing-id" class="form-input w-full text-sm font-mono"
                        placeholder="{{ __('admin.fbn_section.listing_uuid_placeholder') }}">
                    <p id="rm-listing-note" class="text-xs text-gray-400 mt-1">{{ __('admin.fbn_section.listing_must_be_marketplace') }}</p>
                </div>

                <div>
                    <label class="label-sm">{{ __('admin.fbn_section.commission_type') }} <span class="text-red-500">*</span></label>
                    <select id="rm-commission-type" class="form-select w-full text-sm">
                        <option value="percentage">{{ __('admin.fbn_section.percentage_pct') }}</option>
                        <option value="fixed">{{ __('admin.fbn_section.fixed_currency') }}</option>
                        <option value="mixed">{{ __('admin.fbn_section.mixed') }}</option>
                    </select>
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.fbn_section.commission_value') }} <span class="text-red-500">*</span></label>
                    <input type="number" id="rm-commission-value" class="form-input w-full text-sm" min="0" step="any"
                        placeholder="{{ __('admin.fbn_section.commission_value_placeholder') }}">
                </div>

                <div>
                    <label class="label-sm">{{ __('admin.fbn_section.extra_delivery_fee') }}</label>
                    <input type="number" id="rm-extra-fee" class="form-input w-full text-sm" min="0" value="0"
                        placeholder="{{ __('admin.fbn_section.extra_fee_placeholder') }}">
                </div>
                <div>
                    <label class="label-sm">{{ __('admin.fbn_section.max_weight_kg') }}</label>
                    <input type="number" id="rm-weight" class="form-input w-full text-sm" min="0" step="0.01"
                        placeholder="{{ __('admin.fbn_section.max_weight_placeholder') }}">
                </div>

                <div class="col-span-2">
                    <label class="label-sm">{{ __('admin.fbn_section.max_dimensions') }}</label>
                    <input type="text" id="rm-dimensions" class="form-input w-full text-sm" placeholder="{{ __('admin.fbn_section.max_dimensions_placeholder') }}">
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="rm-special-vehicle" class="w-4 h-4">
                    <label for="rm-special-vehicle" class="text-sm font-medium text-gray-700">{{ __('admin.fbn_section.requires_special_vehicle') }}</label>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="rm-refrigeration" class="w-4 h-4">
                    <label for="rm-refrigeration" class="text-sm font-medium text-gray-700">{{ __('admin.fbn_section.requires_refrigeration') }}</label>
                </div>

                <div class="col-span-2">
                    <label class="label-sm">{{ __('admin.fbn_section.special_handling_notes') }}</label>
                    <textarea id="rm-notes" rows="2" class="form-input w-full text-sm"
                        placeholder="{{ __('admin.fbn_section.special_handling_placeholder') }}"></textarea>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-5 pt-4 border-t">
                <button type="button" id="rule-modal-cancel" class="btn btn-ghost btn-sm">{{ __('admin.fbn_section.cancel') }}</button>
                <button type="button" id="rule-modal-save" class="btn btn-primary btn-sm px-8">{{ __('admin.fbn_section.save_rule') }}</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            loading: @json(__('admin.fbn_section.loading')),
            newMarketplaceRule: @json(__('admin.fbn_section.new_marketplace_rule')),
            editMarketplaceRule: @json(__('admin.fbn_section.edit_marketplace_rule')),
            deleteRuleTitle: @json(__('admin.fbn_section.delete_rule_title')),
            deleteRuleText: @json(__('admin.fbn_section.delete_rule_text')),
            error: @json(__('admin.fbn_section.error')),
        });

        document.addEventListener('DOMContentLoaded', function () {
            const tok = '{{ csrf_token() }}';
            const T = window.TRANSLATIONS;

            const tbl = $('#tbl-rules').DataTable({
                serverSide: true,
                processing: true,
                pageLength: 25,
                order: [[0, 'desc']],
                ajax: {
                    url: '{{ route('admin.fbn.marketplace.datatable') }}',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': tok },
                    data: d => { d.commission_type = $('#filter-commission-type').val(); },
                },
                columns: [
                    { data: 'listing_id', orderable: false },
                    { data: 'vendor', orderable: false },
                    { data: 'product', orderable: false },
                    { data: 'flags', orderable: false },
                    { data: 'weight', orderable: false },
                    { data: 'commission', orderable: false },
                    { data: 'extra_fee', orderable: false },
                    { data: 'actions', orderable: false },
                ],
                language: { processing: T.loading },
            });

            $('#filter-commission-type').on('change', () => tbl.ajax.reload());

            function clearModal() {
                $('#rm-id').val('');
                $('#rm-listing-id').val('').prop('disabled', false);
                $('#rm-listing-note').show();
                $('#rm-commission-type').val('percentage');
                $('#rm-commission-value').val('');
                $('#rm-extra-fee').val('0');
                $('#rm-weight').val('');
                $('#rm-dimensions').val('');
                $('#rm-special-vehicle').prop('checked', false);
                $('#rm-refrigeration').prop('checked', false);
                $('#rm-notes').val('');
            }

            // ── Create ──────────────────────────────────────────────────────────────────
            $('#btn-create-rule').on('click', () => {
                clearModal();
                $('#rule-modal-title').text(T.newMarketplaceRule);
                $('#rule-modal').show();
            });

            // ── Edit ────────────────────────────────────────────────────────────────────
            $(document).on('click', '.btn-edit-rule', function () {
                const r = $(this).data('rule');
                $('#rm-id').val(r.id);
                $('#rule-modal-title').text(T.editMarketplaceRule);
                $('#rm-listing-id').val(r.vendor_listing_id).prop('disabled', true);
                $('#rm-listing-note').hide();
                $('#rm-commission-type').val(r.commission_type);
                $('#rm-commission-value').val(r.commission_value);
                $('#rm-extra-fee').val(r.extra_delivery_fee ?? 0);
                $('#rm-weight').val(r.max_weight_kg ?? '');
                $('#rm-dimensions').val(r.max_dimensions_cm ?? '');
                $('#rm-special-vehicle').prop('checked', !!r.requires_special_vehicle);
                $('#rm-refrigeration').prop('checked', !!r.requires_refrigeration);
                $('#rm-notes').val(r.special_handling_notes ?? '');
                $('#rule-modal').show();
            });

            $('#rule-modal-cancel').on('click', () => $('#rule-modal').hide());

            // ── Save ────────────────────────────────────────────────────────────────────
            $('#rule-modal-save').on('click', function () {
                const id = $('#rm-id').val();
                const payload = {
                    vendor_listing_id: $('#rm-listing-id').val(),
                    commission_type: $('#rm-commission-type').val(),
                    commission_value: parseFloat($('#rm-commission-value').val()) || 0,
                    extra_delivery_fee: parseInt($('#rm-extra-fee').val()) || 0,
                    max_weight_kg: parseFloat($('#rm-weight').val()) || null,
                    max_dimensions_cm: $('#rm-dimensions').val() || null,
                    requires_special_vehicle: $('#rm-special-vehicle').is(':checked') ? 1 : 0,
                    requires_refrigeration: $('#rm-refrigeration').is(':checked') ? 1 : 0,
                    special_handling_notes: $('#rm-notes').val() || null,
                };

                const url = id ? `/admin/fbn/marketplace/${id}` : '{{ route('admin.fbn.marketplace.store') }}';
                const method = id ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                }).then(r => r.json()).then(data => {
                    if (data.success) { window.Toast.success(data.message); $('#rule-modal').hide(); tbl.ajax.reload(); }
                    else { window.Toast.error(data.message ?? T.error); }
                });
            });

            // ── Delete ──────────────────────────────────────────────────────────────────
            $(document).on('click', '.btn-delete-rule', function () {
                const id = $(this).data('id');
                window.confirmDelete({
                    title: T.deleteRuleTitle,
                    text: T.deleteRuleText,
                    onConfirm: () => {
                        fetch(`/admin/fbn/marketplace/${id}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
                            body: '{}',
                        }).then(r => r.json()).then(data => {
                            if (data.success) { window.Toast.success(data.message); tbl.ajax.reload(); }
                            else { window.Toast.error(data.message); }
                        });
                    }
                });
            });
        }, { once: true });
    </script>
@endpush
