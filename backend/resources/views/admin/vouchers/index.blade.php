@extends('layouts.admin')

@section('title', __('admin.vouchers_section.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/components/select2.js', 'resources/js/components/flatpickr.js'])
@endpush

@section('content')
    @php
        $columns = [
            [
                'title' => __('admin.vouchers_section.code'),
                'data' => 'code',
                'name' => 'code',
                'render' => 'function(data){ return "<code class=\"font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded\">" + data + "</code>"; }',
            ],
            ['title' => __('admin.vouchers_section.name'), 'data' => 'name', 'name' => 'name'],
            [
                'title' => __('admin.vouchers_section.amount'),
                'data' => 'amount',
                'name' => 'amount',
                'searchable' => false,
                'className' => 'text-end',
            ],
            ['title' => __('admin.vouchers_section.currency'), 'data' => 'currency_code', 'name' => 'currency_code', 'searchable' => false],
            ['title' => __('admin.vouchers_section.country'), 'data' => 'country_name', 'name' => 'country_name', 'orderable' => false, 'render' => 'function(data){ return data || "' . __('admin.vouchers_section.all_countries') . '"; }'],
            [
                'title' => __('admin.vouchers_section.valid_period'),
                'data' => 'valid_from',
                'name' => 'valid_from',
                'searchable' => false,
                'render' => 'function(data, type, row){ return Renderers.date(data) + " → " + Renderers.date(row.valid_until); }',
            ],
            [
                'title' => __('admin.vouchers_section.redeemed_limit'),
                'data' => 'times_redeemed',
                'name' => 'times_redeemed',
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row){ var limit = row.usage_limit_total ? row.usage_limit_total : "∞"; return data + " / " + limit; }',
            ],
            [
                'title' => __('admin.vouchers_section.status'),
                'data' => 'is_active',
                'name' => 'is_active',
                'searchable' => false,
                'render' => 'Renderers.badge({ true: { label: "' . __('admin.vouchers_section.active') . '", color: "success" }, false: { label: "' . __('admin.vouchers_section.inactive') . '", color: "gray" } })',
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([
                            { type: "link",   label: "' . __('admin.vouchers_section.view') . '",   url: ":show_url" },
                            { type: "link",   label: "' . __('admin.vouchers_section.edit') . '",   url: ":edit_url" },
                            { type: "button", label: "' . __('admin.vouchers_section.toggle') . '", id: "toggle" },
                            { type: "button", label: "' . __('admin.vouchers_section.delete') . '", id: "delete", class: "btn-danger" }
                        ])',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.vouchers_section.code_name'), 'placeholder' => __('admin.vouchers_section.search_placeholder')],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('admin.vouchers_section.status'),
                'options' => ['' => __('admin.vouchers_section.all'), '1' => __('admin.vouchers_section.active'), '0' => __('admin.vouchers_section.inactive')],
            ],
            [
                'type' => 'select',
                'name' => 'currency_code',
                'label' => __('admin.vouchers_section.currency'),
                'options' => ['' => __('admin.vouchers_section.all'), 'SAR' => 'SAR', 'AED' => 'AED', 'EGP' => 'EGP', 'KWD' => 'KWD', 'OMR' => 'OMR', 'QAR' => 'QAR', 'BHD' => 'BHD', 'JOD' => 'JOD'],
            ],
        ];
    @endphp

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-900">{{ __('admin.vouchers_section.title') }}</h1>
        <div class="flex gap-2" x-data="{ open: false }">
            <a href="{{ route('admin.vouchers.create') }}" class="btn btn-primary gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('admin.vouchers_section.add_voucher') }}
            </a>
            <button type="button" @click="open = true" class="btn btn-secondary text-sm">
                {{ __('admin.vouchers_section.bulk_generate') }}
            </button>

            {{-- ═══════════════ Bulk Generate Modal ═══════════════ --}}
            <div x-show="open" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 x-transition
                 @keydown.escape.window="open = false">
                <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto" @click.away="open = false" @click.stop>
                    <form id="bulk-generate-form">
                        @csrf
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-base font-semibold text-gray-900">{{ __('admin.vouchers_section.bulk_generate_title') }}</h2>
                            <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                        </div>

                        <div class="px-6 py-5 space-y-4">

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('admin.vouchers_section.quantity') }} <span class="text-red-500">*</span>
                                        <span class="text-gray-400 font-normal">{{ __('admin.vouchers_section.quantity_hint') }}</span>
                                    </label>
                                    <input type="number" name="quantity" class="input w-full" min="1" max="50000" value="1" required />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.code_prefix') }}</label>
                                    <input type="text" name="code_prefix" class="input w-full font-mono uppercase" maxlength="10"
                                           placeholder="{{ __('admin.vouchers_section.code_prefix_placeholder') }}"
                                           oninput="this.value = this.value.toUpperCase()" />
                                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.vouchers_section.code_prefix_hint') }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.name') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="name" class="input w-full" maxlength="150" required />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.description') }}</label>
                                <textarea name="description" rows="2" class="input w-full"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.title_en') }}</label>
                                    <input type="text" name="title_en" class="input w-full" maxlength="255" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.title_ar') }}</label>
                                    <input type="text" name="title_ar" dir="rtl" class="input w-full" maxlength="255" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('admin.vouchers_section.amount') }} <span class="text-red-500">*</span>
                                        <span class="text-gray-400 font-normal block">{{ __('admin.vouchers_section.amount_hint') }}</span>
                                    </label>
                                    <input type="number" name="amount" class="input w-full" min="1" required />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.currency') }} <span class="text-red-500">*</span></label>
                                    <select name="currency_code" class="input w-full" required>
                                        @foreach(['SAR','AED','EGP','KWD','OMR','QAR','BHD','JOD'] as $code)
                                            <option value="{{ $code }}">{{ $code }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.country') }}</label>
                                <select name="country_id" class="input w-full" data-select2-init data-placeholder="{{ __('admin.vouchers_section.all_countries') }}">
                                    <option value=""></option>
                                    @foreach($countries ?? [] as $country)
                                        <option value="{{ $country->id }}">{{ e($country->name_en) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.customer_eligibility') }} <span class="text-red-500">*</span></label>
                                <select name="customer_eligibility" class="input w-full" required>
                                    <option value="all">{{ __('admin.vouchers_section.eligibility_all') }}</option>
                                    <option value="new_customers">{{ __('admin.vouchers_section.eligibility_new') }}</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('admin.vouchers_section.total_usage_limit') }}
                                        <span class="text-gray-400 font-normal">{{ __('admin.vouchers_section.unlimited_hint') }}</span>
                                    </label>
                                    <input type="number" name="usage_limit_total" class="input w-full" min="1" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.per_customer_limit') }} <span class="text-red-500">*</span></label>
                                    <input type="number" name="usage_limit_per_customer" class="input w-full" min="1" value="1" required />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.valid_from') }} <span class="text-red-500">*</span></label>
                                    <input type="text" name="valid_from" class="input w-full flatpickr-input" data-flatpickr data-enable-time="true" placeholder="YYYY-MM-DD HH:MM" required />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.valid_until') }} <span class="text-red-500">*</span></label>
                                    <input type="text" name="valid_until" class="input w-full flatpickr-input" data-flatpickr data-enable-time="true" placeholder="YYYY-MM-DD HH:MM" required />
                                </div>
                            </div>

                            <div id="bulk-generate-result" class="hidden rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800"></div>

                        </div>

                        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2">
                            <button type="button" @click="open = false" class="btn btn-secondary">{{ __('common.cancel') }}</button>
                            <button type="submit" id="bulk-generate-submit" class="btn btn-primary">{{ __('admin.vouchers_section.generate') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach($stats as $stat)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
                <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">{{ $stat->currency_code }}</p>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.vouchers_section.total_vouchers') }}</dt>
                        <dd class="font-medium text-gray-900">{{ number_format($stat->total) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.vouchers_section.active') }}</dt>
                        <dd class="font-medium text-green-600">{{ number_format($stat->active_count) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.vouchers_section.times_redeemed') }}</dt>
                        <dd class="font-medium text-gray-900">{{ number_format($stat->total_redeemed) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ __('admin.vouchers_section.total_value_credited') }}</dt>
                        <dd class="font-medium text-gray-900">{{ number_format($stat->total_credited) }} {{ $stat->currency_code }}</dd>
                    </div>
                </dl>
            </div>
        @endforeach
    </div>

    <x-table.datatable id="vouchers-table" url="{{ route('admin.vouchers.datatable') }}" :columns="$columns"
        :filters="$filters" :page-length="25" :order="[[0, 'desc']]" />
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteVoucherTitle: @json(__('admin.vouchers_section.delete_voucher_title')),
            voucherDeleted: @json(__('admin.vouchers_section.voucher_deleted')),
            deleteFailed: @json(__('admin.vouchers_section.delete_failed')),
            toggleFailed: @json(__('admin.vouchers_section.toggle_failed')),
            defaultVoucherLabel: @json(__('admin.vouchers_section.title')),
            generating: @json(__('admin.vouchers_section.generating')),
            generate: @json(__('admin.vouchers_section.generate')),
            generateFailed: @json(__('admin.vouchers_section.generate_failed')),
            vouchersGenerated: @json(__('admin.vouchers_section.vouchers_generated', ['count' => ':count'])),
            sampleCodes: @json(__('admin.vouchers_section.sample_codes')),
        });

        window.tableActions = window.tableActions || {};

        window.tableActions.toggle = async function (id, row) {
            $.ajax({
                url: row.toggle_url,
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(function () {
                    window.reloadDataTable('vouchers-table');
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.toggleFailed);
                });
        };

        window.tableActions.delete = async function (id, row) {
            const code = row.code || window.TRANSLATIONS.defaultVoucherLabel;
            const message = @json(__('admin.vouchers_section.delete_voucher_confirm', ['code' => '%CODE%'])).replace('%CODE%', code);
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(message, { title: window.TRANSLATIONS.deleteVoucherTitle })
                : confirm(message);
            if (!confirmed) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = row.delete_url;
            form.innerHTML = `@csrf @method('DELETE')`;
            document.body.appendChild(form);
            form.submit();
        };

        document.getElementById('bulk-generate-form')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('bulk-generate-submit');
            const resultBox = document.getElementById('bulk-generate-result');
            const formData = new FormData(form);

            submitBtn.disabled = true;
            submitBtn.textContent = window.TRANSLATIONS.generating;
            resultBox.classList.add('hidden');

            $.ajax({
                url: '{{ route('admin.vouchers.bulk_generate') }}',
                method: 'POST',
                data: Object.fromEntries(formData.entries()),
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(function (res) {
                    window.Toast && window.Toast.success(window.TRANSLATIONS.vouchersGenerated.replace(':count', res.count));
                    resultBox.classList.remove('hidden');
                    resultBox.innerHTML = '<strong>' + window.TRANSLATIONS.sampleCodes + ':</strong> ' + (res.sample_codes || []).join(', ');
                    window.reloadDataTable && window.reloadDataTable('vouchers-table');
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.generateFailed);
                })
                .always(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = window.TRANSLATIONS.generate;
                });
        });
    </script>
@endpush
