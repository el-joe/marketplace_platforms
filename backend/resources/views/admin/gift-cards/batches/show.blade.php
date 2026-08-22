@extends('layouts.admin')

@section('title', $batch->name)

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $batch->name }}</h1>
            @if($batch->description)
                <p class="text-sm text-gray-500 mt-0.5">{{ $batch->description }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if(auth('admin')->user()->can('gift_cards.edit'))
            <a href="{{ route('admin.gift-cards.batches.edit', $batch->id) }}" class="btn btn-secondary btn-sm">
                {{ __('admin.edit') }}
            </a>
            @endif
            <a href="#" class="btn btn-secondary btn-sm">
                {{ __('admin.gift_cards_section.export_csv') }}
            </a>
            @if($batch->inactive_count > 0 && auth('admin')->user()->can('gift_cards.edit'))
            <button type="button" id="activate-batch-btn" class="btn btn-primary btn-sm">
                {{ __('admin.gift_cards_section.activate_batch') }}
            </button>
            @endif
        </div>
    </div>

    @if(session('pins_csv'))
        <div x-data="{ visible: true }" x-show="visible" x-init="setTimeout(() => visible = false, 60000)" x-cloak class="mb-6">
            <a href="{{ route('admin.gift-cards.batches.download_pins', $batch) }}"
               class="btn btn-primary inline-flex items-center gap-2">
                ⬇ {{ __('admin.gift_cards_section.download_pins') }} ({{ __('admin.gift_cards_section.download_pins_hint') }})
            </a>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-4 text-sm text-gray-600 bg-white rounded-xl border border-gray-200 p-4 sm:grid-cols-4">
        <div><span class="text-gray-400">{{ __('admin.gift_cards_section.currency_code') }}:</span> {{ $batch->currency_code }}</div>
        <div><span class="text-gray-400">{{ __('admin.gift_cards_section.amount') }}:</span> {{ (int) $batch->amount }} {{ $batch->currency_code }}</div>
        <div><span class="text-gray-400">{{ __('admin.gift_cards_section.expiry') }}:</span> {{ $batch->expires_at?->format('Y-m-d H:i') ?? '—' }}</div>
        <div><span class="text-gray-400">{{ __('admin.gift_cards_section.created_by') }}:</span> {{ $batch->createdByAdmin?->name ?? '—' }} ({{ $batch->created_at->format('Y-m-d H:i') }})</div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        @foreach([
            ['label' => __('admin.gift_cards_section.quantity'), 'value' => $batch->quantity, 'color' => 'gray-700'],
            ['label' => __('admin.gift_cards_section.active_count'), 'value' => $batch->active_count, 'color' => 'emerald-600'],
            ['label' => __('admin.gift_cards_section.redeemed_count'), 'value' => $batch->redeemed_count, 'color' => 'blue-600'],
            ['label' => __('admin.gift_cards_section.expired'), 'value' => $batch->expired_count, 'color' => 'red-600'],
            ['label' => __('admin.gift_cards_section.inactive'), 'value' => $batch->inactive_count, 'color' => 'gray-700'],
        ] as $stat)
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-black text-{{ $stat['color'] }}">
                {{ $stat['value'] }}
            </div>
            <div class="text-xs text-gray-500 mt-1">{{ $stat['label'] }}</div>
        </div>
        @endforeach
    </div>

    @if($batch->is_purchasable)
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-black text-gray-700">{{ $purchaseStats['total_purchased'] }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ __('admin.gift_cards_section.total_purchased') }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-black text-emerald-600">{{ number_format((int) $purchaseStats['total_revenue']) }} {{ $batch->currency_code }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ __('admin.gift_cards_section.total_revenue') }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-black text-amber-600">{{ $purchaseStats['pending_delivery'] }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ __('admin.gift_cards_section.pending_delivery') }}</div>
        </div>
    </div>
    @endif

    <div x-data="{ tab: 'cards' }">

    <div class="flex items-center gap-1 mb-4 border-b border-gray-200">
        <button type="button" @click="tab = 'cards'"
            :class="tab === 'cards' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">
            {{ __('admin.gift_cards_section.tab_cards') }}
        </button>
        <button type="button" @click="tab = 'purchases'"
            :class="tab === 'purchases' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors">
            {{ __('admin.gift_cards_section.tab_purchases') }}
        </button>
    </div>

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
            [
                'title' => __('admin.gift_cards_section.status'),
                'data' => 'status',
                'name' => 'status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            inactive: { label: "' . __('admin.gift_cards_section.inactive') . '", color: "gray"    },
                            active:   { label: "' . __('admin.gift_cards_section.active') . '",   color: "success" },
                            redeemed: { label: "' . __('admin.gift_cards_section.redeemed') . '", color: "blue"    },
                            expired:  { label: "' . __('admin.gift_cards_section.expired') . '",  color: "red"     }
                        })',
            ],
            [
                'title' => __('admin.gift_cards_section.redeemed_by'),
                'data' => 'redeemed_by',
                'name' => 'redeemed_by',
                'searchable' => false,
                'orderable' => false,
                'render' => 'function(data){ return data ? data : "—"; }',
            ],
            ['title' => __('admin.gift_cards_section.redeemed_at'), 'data' => 'redeemed_at', 'name' => 'redeemed_at', 'searchable' => false, 'render' => 'Renderers.date'],
            ['title' => __('admin.gift_cards_section.expiry'), 'data' => 'expires_at', 'name' => 'expires_at', 'searchable' => false, 'render' => 'Renderers.date'],
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
        ];

        $purchaseColumns = [
            ['title' => __('admin.gift_cards_section.buyer_name'), 'data' => 'buyer_name', 'name' => 'buyer_name', 'searchable' => false, 'render' => 'function(data){ return data ? data : "—"; }'],
            ['title' => __('admin.gift_cards_section.buyer_email'), 'data' => 'buyer_email', 'name' => 'buyer_email', 'searchable' => false, 'render' => 'function(data){ return data ? data : "—"; }'],
            [
                'title' => __('admin.gift_cards_section.amount_paid'),
                'data' => 'amount_paid',
                'name' => 'amount_paid',
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row){ return Number(data).toFixed(2) + " " + row.currency_code; }',
            ],
            ['title' => __('admin.gift_cards_section.currency_code'), 'data' => 'currency_code', 'name' => 'currency_code', 'searchable' => false],
            [
                'title' => __('admin.gift_cards_section.is_gift'),
                'data' => 'is_gift',
                'name' => 'is_gift',
                'searchable' => false,
                'render' => 'function(data){ return data ? "' . __('admin.yes') . '" : "' . __('admin.no') . '"; }',
            ],
            ['title' => __('admin.gift_cards_section.recipient_email'), 'data' => 'recipient_email', 'name' => 'recipient_email', 'searchable' => false, 'render' => 'function(data){ return data ? data : "—"; }'],
            [
                'title' => __('admin.gift_cards_section.delivery_status'),
                'data' => 'delivery_status',
                'name' => 'delivery_status',
                'searchable' => false,
                'render' => 'Renderers.badge({
                            pending: { label: "' . __('admin.gift_cards_section.delivery_pending') . '", color: "yellow" },
                            sent:    { label: "' . __('admin.gift_cards_section.delivery_sent') . '",    color: "green"  },
                            failed:  { label: "' . __('admin.gift_cards_section.delivery_failed') . '",  color: "danger" }
                        })',
            ],
            ['title' => __('admin.gift_cards_section.delivered_at'), 'data' => 'delivered_at', 'name' => 'delivered_at', 'searchable' => false, 'render' => 'Renderers.date'],
            ['title' => __('admin.gift_cards_section.order_number'), 'data' => 'order_number', 'name' => 'order_number', 'searchable' => false, 'render' => 'function(data){ return data ? data : "—"; }'],
            [
                'title' => '',
                'data' => 'actions',
                'name' => 'actions',
                'orderable' => false,
                'searchable' => false,
                'className' => 'text-end',
                'render' => 'function(data, type, row) {
                            return "<button type=\"button\" class=\"btn btn-xs btn-ghost\" data-action=\"resend\" data-id=\"" + row.id + "\" data-row=\'" + JSON.stringify(row).replace(/\'/g, "&#39;") + "\'>' . __('admin.gift_cards_section.resend_email') . '</button>";
                        }',
            ],
        ];

        $purchaseFilters = [
            [
                'type' => 'select',
                'name' => 'delivery_status',
                'label' => __('admin.gift_cards_section.delivery_status'),
                'options' => [
                    '' => __('admin.gift_cards_section.all'),
                    'pending' => __('admin.gift_cards_section.delivery_pending'),
                    'sent' => __('admin.gift_cards_section.delivery_sent'),
                    'failed' => __('admin.gift_cards_section.delivery_failed'),
                ],
            ],
        ];
    @endphp

    <div x-show="tab === 'cards'">
        <x-table.datatable id="batch-cards-table" url="{{ route('admin.gift-cards.batches.datatable', $batch->id) }}"
            :columns="$columns" :filters="$filters" :page-length="25" :order="[[5, 'desc']]" />
    </div>

    <div x-show="tab === 'purchases'" x-cloak>
        <x-table.datatable id="batch-purchases-table" url="{{ route('admin.gift-cards.batches.purchases.datatable', $batch->id) }}"
            :columns="$purchaseColumns" :filters="$purchaseFilters" :page-length="25" :order="[]" />
    </div>

    </div>
@endsection

@push('scripts')
    <script>
        window.tableActions = window.tableActions || {};

        window.tableActions.resend = function (id, row) {
            fetch(row.resend_url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
                .then((res) => res.json())
                .then(() => {
                    window.Toast
                        ? window.Toast.success(@json(__('admin.gift_cards_section.resend_queued')))
                        : alert(@json(__('admin.gift_cards_section.resend_queued')));
                })
                .catch(() => {
                    window.Toast && window.Toast.error(@json(__('admin.gift_cards_section.resend_failed')));
                });
        };

        document.getElementById('activate-batch-btn')?.addEventListener('click', function () {
            if (!confirm(@json(__('admin.gift_cards_section.activate_confirm')))) return;

            fetch(@json(route('admin.gift-cards.batches.activate', $batch->id)), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
                .then((res) => res.json())
                .then((data) => {
                    window.Toast
                        ? window.Toast.success(@json(__('admin.gift_cards_section.batch_activated')).replace(':count', data.activated_count))
                        : alert('Activated: ' + data.activated_count);
                    window.location.reload();
                });
        });
    </script>
@endpush
