@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.orders.title'))

@section('content')
    @php
        $columns = [
            [
                'title' => __('admin.orders.order_number'),
                'data' => 'order_number',
                'name' => 'order_number',
                'searchable' => true,
                'render' => 'function(data,t,row){return "<a href=\""+row.show_url+"\" class=\"font-medium text-primary-600 hover:text-primary-800 hover:underline\">"+data+"</a>";}',
            ],
            [
                'title' => __('admin.orders.customer'),
                'data' => 'customer',
                'name' => 'customer',
                'orderable' => false,
                'searchable' => false,
            ],
            [
                'title' => __('admin.orders.items'),
                'data' => 'items_count',
                'name' => 'items_count',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-center',
            ],
            [
                'title' => __('common.total'),
                'data' => 'total_formatted',
                'name' => 'total_formatted',
                'searchable' => false,
                'className' => 'text-end font-medium',
            ],
            [
                'title' => __('admin.orders.payment_method'),
                'data' => 'payment_method',
                'name' => 'payment_method',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            card:          { label: "' . __('admin.orders.payment_card') . '",          color: "primary" },
                            wallet:        { label: "' . __('admin.orders.payment_wallet') . '",        color: "primary" },
                            cod:           { label: "' . __('admin.orders.payment_cod') . '",  color: "gray"    },
                            bnpl:          { label: "' . __('admin.orders.payment_bnpl') . '",          color: "warning" },
                            bank_transfer: { label: "' . __('admin.orders.payment_bank_transfer') . '", color: "gray"    }
                        })',
            ],
            [
                'title' => __('common.status'),
                'data' => 'status',
                'name' => 'status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            placed:              { label: "' . __('common.order_status.placed') . '",          color: "gray"    },
                            confirmed:           { label: "' . __('common.order_status.confirmed') . '",       color: "primary" },
                            partially_shipped:   { label: "' . __('admin.orders.status_partially_shipped') . '",   color: "primary" },
                            shipped:             { label: "' . __('common.order_status.shipped') . '",         color: "primary" },
                            partially_delivered: { label: "' . __('admin.orders.status_partially_delivered') . '", color: "primary" },
                            delivered:           { label: "' . __('common.order_status.delivered') . '",       color: "success" },
                            completed:           { label: "' . __('common.order_status.completed') . '",       color: "success" },
                            cancelled:           { label: "' . __('common.order_status.cancelled') . '",       color: "danger"  },
                            refunded:            { label: "' . __('common.order_status.refunded') . '",        color: "warning" },
                            disputed:            { label: "' . __('common.order_status.disputed') . '",        color: "danger"  }
                        })',
            ],
            [
                'title' => __('admin.orders.risk_score'),
                'data' => 'risk_score',
                'name' => 'risk_score',
                'searchable' => false,
                'className' => 'text-center',
                'render' => 'function(data){
                            if(data===null||data===undefined){return "<span class=\"text-gray-300\">—</span>";}
                            var n=Math.round(data);
                            var cls=n>=70?"bg-red-100 text-red-700 ring-red-200":n>=40?"bg-amber-100 text-amber-800 ring-amber-200":"bg-green-100 text-green-700 ring-green-200";
                            return "<span class=\"inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-bold ring-1 "+cls+"\">"+n+"</span>";
                        }',
            ],
            [
                'title' => __('admin.orders.placed'),
                'data' => 'placed_at',
                'name' => 'placed_at',
                'searchable' => false,
                'render' => 'Renderers.dateAgo',
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([
                            { type: "link", label: "' . __('common.view') . '", url: ":show_url", class: "btn-primary btn-sm" }
                        ])',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.orders.order_number'), 'placeholder' => __('admin.orders.search_order_placeholder')],
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __('common.status'),
                'options' => [
                    'placed' => __('common.order_status.placed'),
                    'confirmed' => __('common.order_status.confirmed'),
                    'partially_shipped' => __('admin.orders.status_partially_shipped'),
                    'shipped' => __('common.order_status.shipped'),
                    'partially_delivered' => __('admin.orders.status_partially_delivered'),
                    'delivered' => __('common.order_status.delivered'),
                    'completed' => __('common.order_status.completed'),
                    'cancelled' => __('common.order_status.cancelled'),
                    'refunded' => __('common.order_status.refunded'),
                    'disputed' => __('common.order_status.disputed'),
                ],
            ],
            [
                'type' => 'select',
                'name' => 'payment_method',
                'label' => __('admin.orders.payment_method'),
                'options' => [
                    'card' => __('admin.orders.payment_card'),
                    'wallet' => __('admin.orders.payment_wallet'),
                    'cod' => __('admin.orders.payment_cod'),
                    'bnpl' => __('admin.orders.payment_bnpl'),
                    'bank_transfer' => __('admin.orders.payment_bank_transfer'),
                ],
            ],
            [
                'type' => 'select',
                'name' => 'payment_status',
                'label' => __('admin.orders.payment_status'),
                'options' => [
                    'pending' => __('common.pending'),
                    'authorized' => __('admin.orders.payment_authorized'),
                    'captured' => __('admin.orders.payment_captured'),
                    'failed' => __('admin.orders.payment_failed'),
                    'refunded' => __('common.order_status.refunded'),
                    'partially_refunded' => __('admin.orders.payment_partially_refunded'),
                ],
            ],
            [
                'type' => 'select',
                'name' => 'country_id',
                'label' => __('common.country'),
                'options' => $countries->toArray(),
            ],
            ['type' => 'date_range', 'name' => 'date', 'label' => __('admin.orders.placed_date')],
            ['type' => 'text', 'name' => 'min_total', 'label' => __('admin.orders.min_total'), 'placeholder' => __('admin.orders.min_total_placeholder')],
            ['type' => 'text', 'name' => 'max_total', 'label' => __('admin.orders.max_total'), 'placeholder' => __('admin.orders.max_total_placeholder')],
            [
                'type' => 'select',
                'name' => 'risk_score_min',
                'label' => __('admin.orders.risk_score'),
                'options' => ['70' => __('admin.orders.risk_high'), '40' => __('admin.orders.risk_medium')],
            ],
            [
                'type' => 'select',
                'name' => 'listing_type',
                'label' => __('admin.orders.listing_type'),
                'options' => [
                    'vendor' => __('admin.orders.listing_type_vendor'),
                    'admin' => __('admin.orders.listing_type_admin'),
                ],
            ],
        ];
    @endphp

    <div class="flex items-center justify-end mb-3">
        <x-export-dropdown />
    </div>

    <x-table.datatable id="orders-table" url="{{ route('admin.orders.datatable') }}" :columns="$columns" :filters="$filters"
        :page-length="25" :order="[[7, 'desc']]" />
@endsection

@push('scripts')
@endpush
