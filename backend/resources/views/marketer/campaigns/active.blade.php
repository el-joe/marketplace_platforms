@extends('layouts.marketer')
@section('title', 'الحملات النشطة')
@section('page-title', 'الحملات النشطة')

@section('content')
<div class="space-y-4">
    @if($invitations->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">🚀</div>
            <h3 class="font-bold text-gray-700">لا توجد حملات نشطة</h3>
            <p class="text-gray-400 text-sm mt-1">اقبل دعوة حملة لتبدأ كسب العمولات</p>
            <a href="{{ route('marketer.invitations.index') }}" class="inline-block mt-4 px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm">عرض الدعوات</a>
        </div>
    @else
        @foreach($invitations as $inv)
        @php
            $product = $inv->campaign->vendorListing?->productVariant?->product
                     ?? $inv->campaign->adminListing?->productVariant?->product;
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <h4 class="font-bold text-gray-900">{{ $product?->name_ar ?? $inv->campaign->title }}</h4>
                    <div class="text-sm text-gray-500 mt-0.5">
                        {{ $inv->campaign->vendor->name ?? 'نون' }} • {{ $inv->campaign->country->name_ar ?? '' }}
                    </div>
                </div>
                <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded">نشطة</span>
            </div>

            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-lg font-black text-gray-900">{{ number_format($inv->conversions_count ?? 0) }}</div>
                    <div class="text-xs text-gray-500">تحويلات</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-lg font-black text-green-600">{{ number_format($inv->conversions_sum_commission_amount ?? 0) }}</div>
                    <div class="text-xs text-gray-500">عمولة مكتسبة</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-lg font-black text-yellow-600">{{ number_format($inv->campaign->marketer_commission_amount) }}</div>
                    <div class="text-xs text-gray-500">عمولة/بيعة</div>
                </div>
            </div>

            @if($inv->referral_link)
            <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                <div class="text-xs font-semibold text-yellow-800 mb-1">رابط الإحالة الخاص بك:</div>
                <div class="flex items-center gap-2">
                    <code class="text-xs bg-white px-2 py-1 rounded border text-gray-700 flex-1 truncate">{{ $inv->referral_link }}</code>
                    <button onclick="navigator.clipboard.writeText('{{ $inv->referral_link }}')"
                            class="shrink-0 text-xs px-3 py-1 bg-yellow-400 text-gray-900 font-semibold rounded hover:bg-yellow-500">نسخ</button>
                </div>
                <div class="text-xs text-gray-500 mt-1">كود: <strong>{{ $inv->referral_code }}</strong></div>
            </div>
            @endif

            {{-- Samples summary --}}
            @if($inv->samples->isNotEmpty())
            <div class="text-xs text-gray-500">
                عينات: {{ $inv->samples->where('status', 'delivered')->count() }} مستلمة /
                {{ $inv->samples->count() }} إجمالي
            </div>
            @endif
        </div>
        @endforeach

        <div>{{ $invitations->links() }}</div>
    @endif
</div>
@endsection
