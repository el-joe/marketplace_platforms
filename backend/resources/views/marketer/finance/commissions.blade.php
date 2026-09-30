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
                    in_array($r['commission_mode'], ['fixed','both']) ? number_format($r['commission_flat_amount']).' '.$r['currency'] : null,
                ])->filter()->implode(' + ') }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.finance.total_earned_label') }}</div>
            <div class="text-3xl font-black text-green-600">{{ number_format($totalEarned) }}</div>
        </div>
        <div class="bg-white rounded-xl border p-5">
            <div class="text-xs text-gray-400 mb-1">{{ __('marketer.finance.pending_earnings_label') }}</div>
            <div class="text-3xl font-black text-yellow-500">{{ number_format($pendingEarnings) }}</div>
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
                        <th class="px-4 py-3 text-center">{{ __('marketer.finance.commission_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.finance.flash_sale_bonus_header') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('marketer.finance.status_header') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($conversions as $conv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-600">{{ $conv->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $conv->campaign?->title ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $conv->campaign?->vendorListing?->productVariant?->product?->title_en ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-mono text-xs">{{ $conv->order?->order_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-bold text-green-600">{{ number_format($conv->commission_amount) }} {{ $conv->currency }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($conv->flash_sale_bonus_amount)
                                <span class="text-purple-600 font-bold">+{{ number_format($conv->flash_sale_bonus_amount) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-xs {{ $conv->commissioned ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ $conv->commissioned ? __('marketer.finance.status_paid') : __('marketer.finance.status_pending') }}
                            </span>
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
