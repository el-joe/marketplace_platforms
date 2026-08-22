@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.vendor_listings.title'))

@section('content')
    @php
        $columns = [
            ['title' => __('admin.vendor_listings.product_col'), 'data' => 'product_name', 'name' => 'product_name'],
            ['title' => __('admin.vendor_listings.variant_col'), 'data' => 'variant_name', 'name' => 'variant_name', 'searchable' => false],
            ['title' => __('admin.vendor_listings.vendor_col'), 'data' => 'vendor_name', 'name' => 'vendor_name'],
            ['title' => __('admin.vendor_listings.country_col'), 'data' => 'country', 'name' => 'country'],
            ['title' => __('common.currency'), 'data' => 'currency', 'name' => 'currency', 'searchable' => false],
            ['title' => __('admin.vendor_listings.price_col'), 'data' => 'price', 'name' => 'price', 'searchable' => false],
            [
                'title' => __('admin.vendor_listings.status_col'),
                'data' => 'status',
                'name' => 'status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                    draft:           { label: "' . __('admin.vendor_listings.draft') . '",           color: "gray"    },
                    pending_review:  { label: "' . __('admin.vendor_listings.pending_review') . '",  color: "warning" },
                    active:          { label: "' . __('admin.vendor_listings.active') . '",          color: "success" },
                    paused:          { label: "' . __('admin.vendor_listings.paused') . '",          color: "warning" },
                    rejected:        { label: "' . __('admin.vendor_listings.rejected') . '",        color: "danger"  },
                    out_of_stock:    { label: "' . __('admin.vendor_listings.out_of_stock') . '",    color: "orange"  },
                    archived:        { label: "' . __('admin.vendor_listings.archived') . '",        color: "gray"    }
                })'
            ],
            [
                'title' => __('admin.vendor_listings.shipping_col'),
                'data' => 'shipping',
                'name' => 'shipping',
                'orderable' => false,
                'searchable' => false,
                'render' => 'function (data, type, row) {
                    if (type !== "display") return data ? data.label : "";
                    if (!data) return "<span class=\\"text-xs text-gray-400\\">—</span>";
                    var pill = "<span class=\\"inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold\\" style=\\"background-color:" + data.color + ";color:" + data.text_color + ";\\">" + data.label + "</span>";
                    if (data.is_inherited) pill += " <span class=\\"text-[10px] uppercase tracking-wide text-gray-400\\">(default)</span>";
                    return pill;
                }'
            ],
            ['title' => __('admin.vendor_listings.created_at_col'), 'data' => 'created_at', 'name' => 'created_at', 'searchable' => false],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'Renderers.actions([
                    { type: "link", label: "' . __('common.view') . '", url: ":show_url" },
                    { type: "link", label: "' . __('common.edit') . '", url: ":edit_url" }
                ])'
            ],
        ];

        $filters = [
            [
                'type' => 'select',
                'name' => 'country_id',
                'label' => __('admin.vendor_listings.country_col'),
                'options' => $countries,
                'placeholder' => __('admin.all'),
            ],
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __('common.status'),
                'options' => $statuses,
            ],
        ];
    @endphp

    <div class="p-6 space-y-5">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.vendor_listings.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.vendor_listings.page_subtitle') }}</p>
        </div>

        <x-table.datatable id="vendor-listings-table" url="{{ route('admin.vendor-listings.datatable') }}"
            :columns="$columns" :filters="$filters" :page-length="25" />
    </div>
@endsection
