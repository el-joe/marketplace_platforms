@extends('layouts.admin')

@section('title', __('admin.cart_card_offers_section.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/cart-card-offers.js'])
@endpush

@section('content')
    @php
        $columns = [
            ['title' => __('admin.cart_card_offers_section.card_name'), 'data' => 'card_name_en', 'name' => 'card_name_en'],
            ['title' => __('admin.cart_card_offers_section.country'), 'data' => 'country_name', 'name' => 'country_name'],
            [
                'title' => __('admin.cart_card_offers_section.cashback_type'),
                'data' => 'cashback_type',
                'name' => 'cashback_type',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            percentage: { label: "' . __('admin.cart_card_offers_section.percentage') . '", color: "blue" },
                            fixed:      { label: "' . __('admin.cart_card_offers_section.fixed') . '",      color: "purple" }
                        })',
            ],
            [
                'title' => __('admin.cart_card_offers_section.cashback'),
                'data' => 'cashback_pct',
                'name' => 'cashback_pct',
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row){ return row.cashback_type === "percentage" ? data + "%" : row.cashback_fixed_amount; }',
            ],
            [
                'title' => __('admin.cart_card_offers_section.active'),
                'data' => 'is_active',
                'name' => 'is_active',
                'searchable' => false,
                'render' => 'Renderers.badge({ true: { label: "' . __('admin.cart_card_offers_section.active') . '", color: "success" }, false: { label: "' . __('admin.cart_card_offers_section.inactive') . '", color: "gray" } })',
            ],
            ['title' => __('admin.cart_card_offers_section.valid_from'), 'data' => 'valid_from', 'name' => 'valid_from', 'searchable' => false, 'render' => 'Renderers.date'],
            ['title' => __('admin.cart_card_offers_section.valid_until'), 'data' => 'valid_until', 'name' => 'valid_until', 'searchable' => false, 'render' => 'Renderers.date'],
            ['title' => __('admin.cart_card_offers_section.sort_order'), 'data' => 'sort_order', 'name' => 'sort_order', 'searchable' => false, 'className' => 'text-end'],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row) {
                            var actions = [
                                { type: "link", label: "' . __('admin.cart_card_offers_section.edit') . '", url: row.edit_url },
                                { type: "button", label: "' . __('admin.cart_card_offers_section.delete') . '", id: "delete", class: "btn-danger" },
                            ];
                            return Renderers.actions(actions)(data, type, row);
                        }',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.cart_card_offers_section.card_name'), 'placeholder' => __('admin.cart_card_offers_section.search_placeholder')],
            [
                'type' => 'select',
                'name' => 'is_active',
                'label' => __('admin.cart_card_offers_section.status'),
                'options' => ['' => __('admin.cart_card_offers_section.all'), '1' => __('admin.cart_card_offers_section.active'), '0' => __('admin.cart_card_offers_section.inactive')],
            ],
            [
                'type' => 'select',
                'name' => 'cashback_type',
                'label' => __('admin.cart_card_offers_section.cashback_type'),
                'options' => ['' => __('admin.cart_card_offers_section.all'), 'percentage' => __('admin.cart_card_offers_section.percentage'), 'fixed' => __('admin.cart_card_offers_section.fixed')],
            ],
        ];
    @endphp

    <x-table.datatable id="cart-card-offers-table" url="{{ route('admin.cart-card-offers.datatable') }}" :columns="$columns"
        :filters="$filters"
        :create-action="['url' => route('admin.cart-card-offers.create'), 'label' => __('admin.cart_card_offers_section.add_offer')]" :page-length="25" :order="[[7, 'asc']]" />
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            deleteOfferTitle: @json(__('admin.cart_card_offers_section.delete_offer_title')),
            offerDeleted: @json(__('admin.cart_card_offers_section.offer_deleted')),
            deleteFailed: @json(__('admin.cart_card_offers_section.delete_failed')),
            defaultOfferLabel: @json(__('admin.cart_card_offers_section.title')),
        });

        window.tableActions = window.tableActions || {};

        window.tableActions.delete = async function (id, row) {
            const name = row.card_name_en || window.TRANSLATIONS.defaultOfferLabel;
            const message = @json(__('admin.cart_card_offers_section.delete_offer_confirm', ['name' => '%NAME%'])).replace('%NAME%', name);
            const confirmed = window.confirmDelete
                ? await window.confirmDelete(message, { title: window.TRANSLATIONS.deleteOfferTitle })
                : confirm(message);
            if (!confirmed) return;

            $.ajax({
                url: row.delete_url,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            })
                .done(function () {
                    window.Toast && window.Toast.success(window.TRANSLATIONS.offerDeleted);
                    window.reloadDataTable('cart-card-offers-table');
                })
                .fail(function (xhr) {
                    window.Toast && window.Toast.error(xhr.responseJSON?.message || window.TRANSLATIONS.deleteFailed);
                });
        };
    </script>
@endpush
