@extends('layouts.partner')

@section('title', 'تفاصيل طلب الترويج')
@section('page-title', 'تفاصيل طلب الترويج')

@php
    $itemStatusBadge = fn (string $status) => match ($status) {
        'pending' => 'bg-yellow-100 text-yellow-800',
        'accepted' => 'bg-green-100 text-green-800',
        'declined' => 'bg-red-100 text-red-800',
        'timed_out', 'expired' => 'bg-gray-100 text-gray-800',
        'reassigned' => 'bg-blue-100 text-blue-800',
        'cancelled' => 'bg-gray-100 text-gray-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('partner.promotion-requests.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; الرجوع إلى طلبات الترويج</a>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <div class="text-gray-500">القائمة</div>
                    <div class="font-medium text-gray-900">{{ $promotionRequest->vendorListing?->vendor_sku ?? $promotionRequest->vendor_listing_id }}</div>
                </div>
                <div>
                    <div class="text-gray-500">نموذج التنفيذ</div>
                    <div class="font-medium text-gray-900">{{ $promotionRequest->listing_fulfillment_model }}</div>
                </div>
                <div>
                    <div class="text-gray-500">إجمالي الرسوم</div>
                    <div class="font-medium text-gray-900">{{ $promotionRequest->currency }} {{ number_format($promotionRequest->total_promotion_fee) }}</div>
                </div>
                <div>
                    <div class="text-gray-500">الحالة</div>
                    <div class="font-medium text-gray-900">{{ $promotionRequest->status }}</div>
                </div>
            </div>
        </div>

        @if($promotionRequest->requires_warehouse_receipt)
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                يرجى شحن {{ $promotionRequest->num_celebrities_requested + 1 }} عينات إلى عنوان المستودع.
                استلام المستودع: {{ $promotionRequest->warehouse_receipt_confirmed ? 'مؤكد' : 'قيد الانتظار' }}
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-right">المؤثر</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-right">تاريخ الرد</th>
                        <th class="px-4 py-3 text-right">تمت إعادة التعيين</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($promotionRequest->items as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $item->marketer?->display_name ?? $item->marketer?->name }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium {{ $itemStatusBadge($item->status) }}">
                                    {{ $item->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $item->responded_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $item->status === 'reassigned' ? 'نعم' : 'لا' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
