@extends('layouts.admin')

@section('title', __('admin.warranty_purchases_section.purchase_detail'))

@section('content')

    @php
        $statusBadge = match($warrantyPurchase->status) {
            'active' => 'bg-green-100 text-green-700',
            'pending' => 'bg-yellow-100 text-yellow-700',
            'expired' => 'bg-gray-100 text-gray-500',
            'cancelled' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-500',
        };
        $snapshot = $warrantyPurchase->plan_snapshot ?? [];
    @endphp

    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('admin.warranty-purchases.index') }}" class="text-gray-400 hover:text-gray-600">{{ __('admin.warranty_purchases_section.back_to_list') }}</a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-900 font-mono">{{ substr($warrantyPurchase->id, 0, 8) }}…</h1>
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }}">{{ ucfirst($warrantyPurchase->status) }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- ─── Purchase Info ───────────────────────────────────────────── --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.warranty_purchases_section.purchase_info') }}</h2>
            <dl class="text-sm divide-y divide-gray-100">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.price_col') }}</dt>
                    <dd class="font-medium">{{ number_format($warrantyPurchase->price_paid, 2) }} {{ $warrantyPurchase->currency }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.coverage_starts_col') }}</dt>
                    <dd class="font-medium">{{ $warrantyPurchase->coverage_starts_at?->format('M d, Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.coverage_ends_col') }}</dt>
                    <dd class="font-medium">{{ $warrantyPurchase->coverage_ends_at?->format('M d, Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.date_col') }}</dt>
                    <dd class="font-medium">{{ $warrantyPurchase->created_at->format('M d, Y H:i') }}</dd>
                </div>
                @if($warrantyPurchase->order)
                    <div class="flex justify-between py-2">
                        <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.order_col') }}</dt>
                        <dd class="font-medium font-mono text-xs">{{ $warrantyPurchase->order->order_number }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- ─── Plan Snapshot (read-only) ───────────────────────────────── --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.warranty_purchases_section.plan_snapshot') }}</h2>
            <dl class="text-sm divide-y divide-gray-100">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.plan_col') }}</dt>
                    <dd class="font-medium">{{ $snapshot['name_en'] ?? $warrantyPurchase->plan?->name_en ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.duration_col') }}</dt>
                    <dd class="font-medium">{{ $snapshot['duration_label'] ?? ($snapshot['duration_months'] ?? '—') }}</dd>
                </div>
                @if(!empty($snapshot['features_en']))
                    <div class="py-2">
                        <dt class="text-gray-500 mb-1">{{ __('admin.warranty_purchases_section.features_label') }}</dt>
                        <dd>
                            <ul class="list-disc ps-5 text-gray-700 space-y-0.5">
                                @foreach($snapshot['features_en'] as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- ─── Customer Info ───────────────────────────────────────────── --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.warranty_purchases_section.customer_info') }}</h2>
            <dl class="text-sm divide-y divide-gray-100">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.name_label') }}</dt>
                    <dd class="font-medium">{{ $warrantyPurchase->customer?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('common.email') }}</dt>
                    <dd class="font-medium">{{ $warrantyPurchase->customer?->email ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- ─── Order Item Info ─────────────────────────────────────────── --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.warranty_purchases_section.order_info') }}</h2>
            @php
                $productSnapshot = $warrantyPurchase->orderItem?->product_snapshot ?? [];
            @endphp
            <dl class="text-sm divide-y divide-gray-100">
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.product_col') }}</dt>
                    <dd class="font-medium">{{ $productSnapshot['name_en'] ?? $productSnapshot['name'] ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.sku') }}</dt>
                    <dd class="font-medium font-mono text-xs">{{ $warrantyPurchase->orderItem?->sku ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.quantity') }}</dt>
                    <dd class="font-medium">{{ $warrantyPurchase->orderItem?->quantity ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="text-gray-500">{{ __('admin.warranty_purchases_section.unit_price') }}</dt>
                    <dd class="font-medium">{{ $warrantyPurchase->orderItem ? number_format($warrantyPurchase->orderItem->unit_price, 2) : '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- ─── Related Claim ───────────────────────────────────────────── --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.warranty_purchases_section.related_claim') }}</h2>
            @if($relatedClaim)
                <a href="{{ route('admin.warranty-claims.show', $relatedClaim->id) }}" class="btn btn-secondary btn-sm">
                    {{ __('admin.warranty_purchases_section.view_claim') }}
                </a>
            @else
                <p class="text-sm text-gray-400">{{ __('admin.warranty_purchases_section.no_related_claim') }}</p>
            @endif
        </div>

    </div>

@endsection
