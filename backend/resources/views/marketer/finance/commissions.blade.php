@extends('layouts.marketer')
@section('title', __('marketer.finance.commissions_title'))
@section('page-title', __('marketer.finance.commissions_title'))

@section('content')
<div class="space-y-5">

    @if(!empty($commissionRules))
    <div class="bg-white rounded-xl border p-5">
        <div class="font-bold mb-3">{{ __('marketer.my_commission_rates') }}</div>
        <ul class="text-sm divide-y">
            @foreach($commissionRules as $r)
            <li class="py-2 flex justify-between gap-3">
                <span>{{ __('marketer.commission_scope_' . $r['scope']) }} — {{ $r['category']['name'] ?? __('marketer.commission_default') }}@if(empty($r['category']) && !empty($r['excluded_categories'])) <span class="text-xs text-red-500">({{ __('admin.marketer_commission_except') }}: {{ collect($r['excluded_categories'])->pluck('name')->implode('، ') }})</span>@endif</span>
                <span class="font-semibold">{{ collect([
                    in_array($r['commission_mode'], ['percentage','both']) ? rtrim(rtrim($r['commission_rate'],'0'),'.').'%' : null,
                    in_array($r['commission_mode'], ['fixed','both']) ? \App\Support\Money::format($r['commission_flat_amount']).' '.$r['currency'] : null,
                ])->filter()->implode(' + ') }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-xl border p-5" data-statement-totals>
        <div class="font-bold mb-3">{{ __('marketer.finance.statement_title') }}</div>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.gross_header') }}</dt><dd class="font-bold">{{ \App\Support\Money::format($totals['gross']) }}</dd></div>
            <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.fee_header') }}</dt><dd class="font-bold text-red-600">−{{ \App\Support\Money::format($totals['fee']) }}</dd></div>
            <div><dt class="text-xs text-gray-400">{{ __('marketer.finance.net_header') }}</dt><dd class="font-bold text-green-600">{{ \App\Support\Money::format($totals['net']) }}</dd></div>
        </dl>
        <p class="text-xs text-gray-400 mt-2">{{ __('marketer.finance.statement_hint') }}</p>
    </div>

    @include('marketer.finance._fixed_lines')

    @if(($salesTotals['count'] ?? 0) > 0)
    <div class="bg-white rounded-xl border overflow-hidden" data-sales-lines>
        <div class="px-5 py-4 border-b">
            <div class="font-bold">{{ __('marketer.finance.sales_title') }}</div>
            <p class="text-xs text-gray-400 mt-1">{{ __('marketer.finance.sales_hint') }}</p>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('marketer.finance.date_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.finance.order_number_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.finance.sales_via_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.finance.gross_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.finance.sales_commission_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.finance.vat_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.finance.sales_peer_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.finance.sales_other_header') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('marketer.finance.net_header') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($sales as $sale)
                <tr data-sale-row>
                    <td class="px-4 py-3 text-gray-600">{{ $sale->credited_at?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-center font-mono text-xs">{{ $sale->order?->order_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-xs">{{ __('marketer.finance.sales_via_'.$sale->sold_via) }}</td>
                    <td class="px-4 py-3 text-center" data-gross>{{ \App\Support\Money::format($sale->gross_amount) }}</td>
                    <td class="px-4 py-3 text-center text-red-600" data-commission>−{{ \App\Support\Money::format($sale->platform_commission_amount) }}</td>
                    <td class="px-4 py-3 text-center text-red-600" data-vat>−{{ \App\Support\Money::format($sale->commission_vat_amount) }}</td>
                    <td class="px-4 py-3 text-center text-red-600" data-peer>−{{ \App\Support\Money::format($sale->peer_commission_amount) }}</td>
                    <td class="px-4 py-3 text-center text-red-600">−{{ \App\Support\Money::format($sale->gross_amount - $sale->net_amount - $sale->platform_commission_amount - $sale->commission_vat_amount - $sale->peer_commission_amount) }}</td>
                    <td class="px-4 py-3 text-center font-bold text-green-600" data-net>{{ \App\Support\Money::format($sale->net_amount) }} {{ $sale->currency }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="p-4">{{ $sales->links() }}</div>
    </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.finance.total_earned_label') }}</div>
            <div class="text-3xl font-black text-green-600">{{ \App\Support\Money::format($totalEarned) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.finance.pending_earnings_label') }}</div>
            <div class="text-3xl font-black text-yellow-500">{{ \App\Support\Money::format($pendingEarnings) }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        @if($conversions->isEmpty())
            <div class="p-12 text-center text-gray-400">{{ __('marketer.finance.no_commissions') }}</div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('marketer.finance.date_header') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('marketer.finance.campaign_header') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('marketer.finance.product_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.finance.order_number_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.finance.gross_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.finance.fee_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.finance.net_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.finance.status_header') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($conversions as $conv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-600">{{ $conv->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $conv->campaign?->title ?? '—' }}@if($conv->conversion_type)<div class="text-[11px] text-gray-400">{{ __('marketer.finance.conversion_type_'.$conv->conversion_type) }}</div>@endif</td>
                        <td class="px-4 py-3">{{ $conv->campaign?->vendorListing?->productVariant?->product?->title_en ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-mono text-xs">{{ $conv->order?->order_number ?? '—' }}</td>
                        @php $st = $statements[$conv->id]; @endphp
                        <td class="px-4 py-3 text-center" data-gross>{{ \App\Support\Money::format($st['gross']) }}</td>
                        <td class="px-4 py-3 text-center text-red-600" data-fee>−{{ \App\Support\Money::format($st['fee']) }} <span class="text-[11px] text-gray-400">({{ rtrim(rtrim(number_format($st['fee_percentage'], 2), '0'), '.') }}%)</span></td>
                        <td class="px-4 py-3 text-center font-bold text-green-600" data-net>{{ \App\Support\Money::format($st['net']) }} {{ $conv->currency }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $convState = $conv->status === 'reversed' ? 'reversed' : ($conv->commissioned ? 'paid' : ($conv->status === 'approved' ? 'approved' : 'pending'));
                                $convClass = ['reversed' => 'bg-red-100 text-red-700', 'paid' => 'bg-green-100 text-green-700', 'approved' => 'bg-blue-100 text-blue-700', 'pending' => 'bg-yellow-100 text-yellow-700'][$convState];
                            @endphp
                            <span class="px-2 py-0.5 rounded text-xs {{ $convClass }}" data-status="{{ $convState }}">{{ __('marketer.finance.status_'.$convState) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            <div class="p-4">{{ $conversions->links() }}</div>
        @endif
    </div>
</div>
@endsection
