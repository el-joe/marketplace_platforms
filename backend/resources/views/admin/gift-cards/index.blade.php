@extends('layouts.admin')

@section('title', __('admin.gift_cards_section.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
    @php
        $columns = [
            [
                'title' => __('admin.gift_cards_section.code'),
                'data' => 'code',
                'name' => 'code',
                'render' => 'function(data){ return "<code class=\"font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded\">" + data + "</code>"; }',
            ],
            [
                'title' => __('admin.gift_cards_section.amount'),
                'data' => 'amount',
                'name' => 'amount',
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row){ return Number(data).toFixed(2) + " " + row.currency_code; }',
            ],
            ['title' => __('admin.gift_cards_section.currency_code'), 'data' => 'currency_code', 'name' => 'currency_code', 'searchable' => false],
            [
                'title' => __('admin.gift_cards_section.status'),
                'data' => 'status',
                'name' => 'status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            inactive: { label: "' . __('admin.gift_cards_section.inactive') . '", color: "gray"    },
                            active:   { label: "' . __('admin.gift_cards_section.active') . '",   color: "success" },
                            redeemed: { label: "' . __('admin.gift_cards_section.redeemed') . '", color: "blue"    },
                            expired:  { label: "' . __('admin.gift_cards_section.expired') . '",  color: "amber"   }
                        })',
            ],
            [
                'title' => __('admin.gift_cards_section.expiry'),
                'data' => 'expires_at',
                'name' => 'expires_at',
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
                            return row.batch_url
                                ? Renderers.actions([{ type: "link", label: "' . __('admin.gift_cards_section.view') . '", url: row.batch_url }])(data, type, row)
                                : "";
                        }',
            ],
        ];

        $filters = [
            ['type' => 'text', 'name' => 'search', 'label' => __('admin.gift_cards_section.code'), 'placeholder' => __('admin.gift_cards_section.search_placeholder')],
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __('admin.gift_cards_section.status'),
                'options' => [
                    '' => __('admin.gift_cards_section.all'),
                    'inactive' => __('admin.gift_cards_section.inactive'),
                    'active' => __('admin.gift_cards_section.active'),
                    'redeemed' => __('admin.gift_cards_section.redeemed'),
                    'expired' => __('admin.gift_cards_section.expired'),
                ],
            ],
            ['type' => 'text', 'name' => 'currency_code', 'label' => __('admin.gift_cards_section.currency_code'), 'placeholder' => 'SAR'],
        ];
    @endphp

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.gift_cards_section.title') }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.gift-cards.batches.index') }}" class="btn btn-secondary">
                {{ __('admin.gift_cards_section.batches_title') }}
            </a>
            @if(auth('admin')->user()->can('gift_cards.create'))
                <a href="{{ route('admin.gift-cards.batches.create') }}" class="btn btn-primary">
                    {{ __('admin.gift_cards_section.add_batch') }}
                </a>
            @endif
        </div>
    </div>

    {{-- Per-currency stats — NEVER sum across currencies, gift card balances are currency-scoped --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        @forelse($stats as $stat)
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="text-sm font-bold text-gray-900 mb-3">{{ $stat->currency_code }}</div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="text-lg font-black text-gray-700">{{ number_format((int) $stat->total_value) }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ __('admin.gift_cards_section.total_value') }}</div>
                    </div>
                    <div>
                        <div class="text-lg font-black text-emerald-600">{{ $stat->active_count }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ __('admin.gift_cards_section.active_count') }}</div>
                    </div>
                    <div>
                        <div class="text-lg font-black text-blue-600">{{ $stat->redeemed_count }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ __('admin.gift_cards_section.redeemed_count') }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-center mt-3 pt-3 border-t border-gray-100">
                    <div>
                        <div class="text-lg font-black text-purple-600">{{ $stat->purchased_count }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ __('admin.gift_cards_section.purchased_count') }}</div>
                    </div>
                    <div>
                        <div class="text-lg font-black text-emerald-700">{{ number_format((int) $stat->total_revenue) }}</div>
                        <div class="text-xs text-gray-500 mt-0.5">{{ __('admin.gift_cards_section.total_revenue') }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center text-gray-400 text-sm py-6">
                {{ __('admin.gift_cards_section.no_gift_cards') }}
            </div>
        @endforelse
    </div>

    <x-table.datatable id="gift-cards-table" url="{{ route('admin.gift-cards.datatable') }}" :columns="$columns"
        :filters="$filters" :page-length="25" :order="[[4, 'desc']]" />
@endsection
