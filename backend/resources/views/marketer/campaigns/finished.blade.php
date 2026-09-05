@extends('layouts.marketer')
@section('title', 'الحملات المنتهية')
@section('page-title', 'الحملات المنتهية')

@section('content')
<div class="space-y-4">
    @if($invitations->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">🏁</div>
            <h3 class="font-bold text-gray-700">لا توجد حملات منتهية</h3>
        </div>
    @else
        @foreach($invitations as $inv)
        @php
            $product = $inv->campaign->vendorListing?->productVariant?->product
                     ?? $inv->campaign->adminListing?->productVariant?->product;
            $statusLabel = ['done' => 'منتهية', 'cancelled' => 'ملغاة', 'rejected' => 'مرفوضة'][$inv->campaign->status] ?? $inv->campaign->status;
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h4 class="font-bold text-gray-900">{{ $product?->name_ar ?? $inv->campaign->title }}</h4>
                    <div class="text-sm text-gray-500 mt-0.5">{{ $inv->campaign->vendor->name ?? 'نون' }} • {{ $inv->campaign->country->name_ar ?? '' }}</div>
                </div>
                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded">{{ $statusLabel }}</span>
            </div>
            <div class="flex gap-6 text-sm">
                <div><span class="text-gray-500">التحويلات: </span><strong>{{ number_format($inv->conversions_count ?? 0) }}</strong></div>
                <div><span class="text-gray-500">الأرباح: </span><strong class="text-green-600">{{ number_format($inv->conversions_sum_commission_amount ?? 0) }} {{ $inv->campaign->currency }}</strong></div>
            </div>
        </div>
        @endforeach

        <div>{{ $invitations->links() }}</div>
    @endif
</div>
@endsection
