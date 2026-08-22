@extends('layouts.admin')

@section('title', __('admin.coupons_section.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/coupons.js'])
@endpush

@section('content')
    @php
        $columns = [
            [
                'title' => __('admin.coupons_section.code'),
                'data' => 'code',
                'name' => 'code',
                'render' => 'function(data){ return "<code class=\"font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded\">" + data + "</code>"; }',
            ],
            ['title' => __('admin.coupons_section.name'), 'data' => 'name', 'name' => 'name'],
            [
                'title' => __('admin.coupons_section.type'),
                'data' => 'type',
                'name' => 'type',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            percentage:   { label: "' . __('admin.coupons_section.percentage') . '",   color: "blue"   },
                            fixed_amount: { label: "' . __('admin.coupons_section.fixed_amount') . '", color: "purple" },
                            free_shipping:{ label: "' . __('admin.coupons_section.free_shipping') . '",color: "teal"   },
                            bogo:         { label: "' . __('admin.coupons_section.bogo') . '",         color: "orange" }
                        })',
            ],
            [
                'title' => __('admin.coupons_section.value'),
                'data' => 'value',
                'name' => 'value',
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row){ return row.type === "percentage" ? data + "%" : (row.type === "free_shipping" ? "—" : data); }',
            ],
            [
                'title' => __('admin.coupons_section.scope'),
                'data' => 'scope',
                'name' => 'scope',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            platform: { label: "' . __('admin.coupons_section.platform') . '", color: "blue"   },
                            vendor:   { label: "' . __('admin.coupons_section.vendor') . '",   color: "amber" },
                            category: { label: "' . __('admin.coupons_section.category') . '", color: "green" },
                            product:  { label: "' . __('admin.coupons_section.product') . '",  color: "purple"   }
                        })',
            ],
            [
                'title' => __('admin.coupons_section.status'),
                'data' => 'is_admin_managed',
                'name' => 'is_admin_managed',
                'orderable' => false,
                'searchable' => false,
                'render' => 'function(data){ return data
                            ? ""
                            : "<span class=\"text-xs text-gray-400\" title=\"' . __('admin.coupons_section.scope_not_admin_manageable') . '\">' . __('admin.coupons_section.vendor_owned') . '</span>"; }',
            ],
            [
                'title' => __('admin.coupons_section.used'),
                'data' => 'times_used',
                'name' => 'times_used',
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row){ var limit = row.usage_limit_total ? row.usage_limit_total : "∞"; return data + " / " + limit; }',
            ],
            [
                'title' => __('admin.coupons_section.active'),
                'data' => 'is_active',
                'name' => 'is_active',
                'searchable' => false,
                'render' => 'Renderers.badge({ true: { label: "' . __('admin.coupons_section.active') . '", color: "success" }, false: { label: "' . __('admin.coupons_section.inactive') . '", color: "gray" } })',
            ],
            [
                'title' => __('admin.coupons_section.valid_until'),
                'data' => 'valid_until',
                'name' => 'valid_until',
                'searchable' => false,
                'render' => 'Renderers.date',
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row) {
                            var actions = [{ type: "link", label: "' . __('admin.coupons_section.view') . '", url: row.show_url }];
                            if (row.is_admin_managed) {
                                actions.push({ type: "link",   label: "' . __('admin.coupons_section.edit') . '",   url: row.edit_url });
                                actions.push({ type: "button", label: "' . __('admin.coupons_section.delete') . '", id: "delete", class: "btn-danger" });
                            }
                            return Renderers.actions(actions)(data, type, row);
                        }',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.coupons_section.code_name'), 'placeholder' => __('admin.coupons_section.search_placeholder')],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('admin.coupons_section.status'),
                'options' => ['' => __('admin.coupons_section.all'), '1' => __('admin.coupons_section.active'), '0' => __('admin.coupons_section.inactive')],
            ],
            [
                'type' => 'select',
                'name' => 'type',
                'label' => __('admin.coupons_section.type'),
                'options' => ['' => __('admin.coupons_section.all'), 'percentage' => __('admin.coupons_section.percentage'), 'fixed_amount' => __('admin.coupons_section.fixed_amount'), 'free_shipping' => __('admin.coupons_section.free_shipping'), 'bogo' => __('admin.coupons_section.bogo')],
            ],
            [
                'type' => 'select',
                'name' => 'scope',
                'label' => __('admin.coupons_section.scope'),
                'options' => ['' => __('admin.coupons_section.all'), 'platform' => __('admin.coupons_section.platform'), 'vendor' => __('admin.coupons_section.vendor'), 'category' => __('admin.coupons_section.category'), 'product' => __('admin.coupons_section.product')],
            ],
            [
                'type' => 'select',
                'name' => 'expired',
                'label' => __('admin.coupons_section.expiry'),
                'options' => ['' => __('admin.coupons_section.all'), '0' => __('admin.coupons_section.valid'), '1' => __('admin.coupons_section.expired')],
            ],
        ];

        $bulkActions = [
            ['value' => 'activate', 'label' => __('admin.coupons_section.activate')],
            ['value' => 'deactivate', 'label' => __('admin.coupons_section.deactivate')],
            ['value' => 'delete', 'label' => __('admin.coupons_section.delete'), 'class' => 'text-red-600'],
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
            <p class="text-xs text-gray-500">{{ __('admin.coupons_section.total') }}</p>
            <p class="text-xl font-semibold text-gray-900 mt-1">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
            <p class="text-xs text-gray-500">{{ __('admin.coupons_section.active') }}</p>
            <p class="text-xl font-semibold text-green-600 mt-1">{{ number_format($stats['active']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
            <p class="text-xs text-gray-500">{{ __('admin.coupons_section.expired') }}</p>
            <p class="text-xl font-semibold text-gray-500 mt-1">{{ number_format($stats['expired']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
            <p class="text-xs text-gray-500">{{ __('admin.coupons_section.used_today') }}</p>
            <p class="text-xl font-semibold text-gray-900 mt-1">{{ number_format($stats['used_today']) }}</p>
        </div>
    </div>

    <div class="flex justify-end mb-3">
        <button type="button" id="clear-coupons-cache-btn" class="btn btn-ghost gap-2 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            {{ __('admin.coupons_section.clear_cache') }}
        </button>
    </div>

    <x-table.datatable id="coupons-table" url="{{ route('admin.coupons.datatable') }}" :columns="$columns"
        :filters="$filters" :bulk-actions="$bulkActions" bulk-url="{{ route('admin.coupons.bulk') }}"
        :create-action="['url' => route('admin.coupons.create'), 'label' => __('admin.coupons_section.add_coupon')]" :page-length="25" :order="[[7, 'desc']]" />
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteCouponTitle: @json(__('admin.coupons_section.delete_coupon_title')),
            couponDeleted: @json(__('admin.coupons_section.coupon_deleted')),
            deleteFailed: @json(__('admin.coupons_section.delete_failed')),
            defaultCouponLabel: @json(__('admin.coupons_section.title')),
            clearCacheConfirm: @json(__('admin.coupons_section.clear_cache_confirm')),
            cacheCleared: @json(__('admin.coupons_section.cache_cleared')),
            clearCacheFailed: @json(__('admin.coupons_section.clear_cache_failed')),
        });

        document.getElementById('clear-coupons-cache-btn')?.addEventListener('click', async function () {
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(window.TRANSLATIONS.clearCacheConfirm, { title: window.TRANSLATIONS.clearCacheConfirm })
                : confirm(window.TRANSLATIONS.clearCacheConfirm);
            if (!confirmed) return;

            $.ajax({
                url: '{{ route('admin.coupons.clear-cache') }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(function (res) {
                    window.Toast && window.Toast.success(res.message || window.TRANSLATIONS.cacheCleared);
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.clearCacheFailed);
                });
        });

        window.tableActions = window.tableActions || {};

        window.tableActions.delete = async function (id, row) {
            const code = row.code || window.TRANSLATIONS.defaultCouponLabel;
            const message = @json(__('admin.coupons_section.delete_coupon_confirm', ['code' => '%CODE%'])).replace('%CODE%', code);
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(message, { title: window.TRANSLATIONS.deleteCouponTitle })
                : confirm(message);
            if (!confirmed) return;

            $.ajax({
                url: row.delete_url,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(function (res) {
                    if (res && res.deactivated) {
                        window.Toast && window.Toast.info(res.message || window.TRANSLATIONS.couponDeleted);
                    } else {
                        window.Toast && window.Toast.success(window.TRANSLATIONS.couponDeleted);
                    }
                    window.reloadDataTable('coupons-table');
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.deleteFailed);
                });
        };
    </script>
@endpush
