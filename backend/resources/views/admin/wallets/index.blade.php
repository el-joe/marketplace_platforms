@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.wallets.overview_title'))

@section('content')
    <div class="flex items-center justify-between mb-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.wallets.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.wallets.subtitle') }}</p>
        </div>
        <x-export-dropdown />
    </div>

    @php
        $columns = [
            [
                'title' => __('admin.wallets.owner'),
                'data' => 'owner',
                'name' => 'owner',
                'searchable' => true,
                'render' => 'function(data,t,row){return "<a href=\""+row.show_url+"\" class=\"font-medium text-primary-600 hover:text-primary-800 hover:underline\">"+data+"</a>";}',
            ],
            [
                'title' => __('admin.wallets.type'),
                'data' => 'owner_type',
                'name' => 'owner_type',
                'searchable' => false,
            ],
            [
                'title' => __('admin.wallets.balance'),
                'data' => 'balance',
                'name' => 'balance',
                'searchable' => false,
                'className' => 'text-end font-semibold',
            ],
            [
                'title' => __('admin.wallets.pending'),
                'data' => 'pending_balance',
                'name' => 'pending_balance',
                'searchable' => false,
                'className' => 'text-end',
            ],
            [
                'title' => __('admin.wallets.currency'),
                'data' => 'currency',
                'name' => 'currency',
                'searchable' => false,
                'className' => 'text-center',
            ],
            [
                'title' => __('admin.wallets.status'),
                'data' => 'status',
                'name' => 'status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            frozen: { label: "' . __('admin.wallets.frozen') . '", color: "danger"  },
                            active: { label: "' . __('admin.wallets.active') . '", color: "success" }
                        })',
            ],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([
                            { type: "link", label: "' . __('admin.wallets.view') . '", url: ":show_url", class: "btn-primary btn-sm" }
                        ])',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.wallets.owner'), 'placeholder' => __('admin.wallets.owner') . '…'],
            [
                'type' => 'select',
                'name' => 'owner_type',
                'label' => __('admin.wallets.type'),
                'options' => collect(['customer', 'vendor', 'marketer', 'delivery_agent', 'travel_agency'])
                    ->mapWithKeys(fn($t) => [$t => __('admin.wallets.' . $t)])
                    ->toArray(),
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __('admin.wallets.status'),
                'options' => [
                    'active' => __('admin.wallets.active'),
                    'frozen' => __('admin.wallets.frozen'),
                ],
            ],
            [
                'type' => 'select',
                'name' => 'currency',
                'label' => __('admin.wallets.currency'),
                'options' => \App\Models\Currency::where('is_active', true)
                    ->orderBy('code')
                    ->pluck('code', 'code')
                    ->toArray(),
            ],
            ['type' => 'date_range', 'name' => 'date', 'label' => __('common.date')],
        ];
    @endphp

    <x-table.datatable id="wallets-table" url="{{ route('admin.wallets.datatable') }}" :columns="$columns"
        :filters="$filters" :page-length="25" :order="[[0, 'asc']]" />
@endsection
