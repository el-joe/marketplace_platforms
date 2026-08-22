@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.payouts.title'))

@section('content')
    @php
        $columns = [
            [
                'title' => __('admin.payouts.payout_number'),
                'data' => 'payout_number',
                'name' => 'payout_number',
                'searchable' => true,
                'render' => 'function(data,t,row){return "<a href=\""+row.show_url+"\" class=\"font-medium text-primary-600 hover:text-primary-800 hover:underline\">"+data+"</a>";}',
            ],
            [
                'title' => __('admin.payouts.vendor'),
                'data' => 'vendor_name',
                'name' => 'vendor_name',
                'searchable' => false,
            ],
            [
                'title' => __('admin.payouts.period'),
                'data' => 'period',
                'name' => 'period',
                'searchable' => false,
            ],
            [
                'title' => __('admin.payouts.gross_sales'),
                'data' => 'gross_formatted',
                'name' => 'gross_formatted',
                'searchable' => false,
                'className' => 'text-end',
            ],
            [
                'title' => __('admin.payouts.net_amount'),
                'data' => 'net_formatted',
                'name' => 'net_formatted',
                'searchable' => false,
                'className' => 'text-end font-semibold',
            ],
            [
                'title' => __('admin.payouts.method'),
                'data' => 'payout_method',
                'name' => 'payout_method',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            bank_transfer: { label: "' . __('admin.payouts.bank_transfer') . '", color: "primary" },
                            wallet:        { label: "' . __('admin.payouts.wallet') . '",        color: "primary" },
                            paypal:        { label: "' . __('admin.payouts.paypal') . '",        color: "primary" }
                        })',
            ],
            [
                'title' => __('admin.payouts.status'),
                'data' => 'status',
                'name' => 'status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            pending:    { label: "' . __('admin.payouts.pending') . '",    color: "gray"    },
                            approved:   { label: "' . __('admin.payouts.approved') . '",   color: "primary" },
                            processing: { label: "' . __('admin.payouts.processing') . '", color: "primary" },
                            completed:  { label: "' . __('admin.payouts.completed') . '",  color: "success" },
                            failed:     { label: "' . __('admin.payouts.failed') . '",     color: "danger"  },
                            on_hold:    { label: "' . __('admin.payouts.on_hold') . '",    color: "warning" }
                        })',
            ],
            [
                'title' => __('admin.payouts.processed'),
                'data' => 'processed_at',
                'name' => 'processed_at',
                'searchable' => false,
                'render' => 'function(data){return data ? Renderers.dateAgo(data) : "<span class=\"text-gray-300\">—</span>";}',
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
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.payouts.payout_number'), 'placeholder' => __('admin.payouts.payout_number') . '…'],
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __('admin.payouts.status'),
                'options' => [
                    'pending' => __('admin.payouts.pending'),
                    'approved' => __('admin.payouts.approved'),
                    'processing' => __('admin.payouts.processing'),
                    'completed' => __('admin.payouts.completed'),
                    'failed' => __('admin.payouts.failed'),
                    'on_hold' => __('admin.payouts.on_hold'),
                ],
            ],
            [
                'type' => 'select',
                'name' => 'currency',
                'label' => __('common.currency'),
                'options' => \App\Models\Currency::where('is_active', true)
                    ->orderBy('code')
                    ->pluck('code', 'code')
                    ->toArray(),
            ],
            ['type' => 'date_range', 'name' => 'date', 'label' => __('admin.payouts.period')],
            ['type' => 'text', 'name' => 'min_amount', 'label' => __('admin.payouts.min_net'), 'placeholder' => 'e.g. 100'],
        ];
    @endphp

    <div class="flex items-center justify-end mb-3">
        <x-export-dropdown />
    </div>

    <x-table.datatable id="payouts-table" url="{{ route('admin.payouts.datatable') }}" :columns="$columns"
        :filters="$filters" :page-length="25" :order="[[7, 'desc']]" />
@endsection
