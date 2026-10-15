{{-- Influencer fixed amounts, one line per campaign. $fixedLines = Collection of MarketerEarningsService::fixedAmountLines() --}}
@if(($fixedLines ?? collect())->isNotEmpty())
<div class="bg-white rounded-xl border overflow-hidden" data-fixed-lines>
    <div class="px-5 py-4 border-b">
        <div class="font-bold">{{ __('marketer.finance.fixed_title') }}</div>
        <p class="text-xs text-gray-400 mt-1">{{ __('marketer.finance.fixed_hint') }}</p>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500">
            <tr>
                <th class="px-4 py-3 text-start">{{ __('marketer.finance.campaign_header') }}</th>
                <th class="px-4 py-3 text-center">{{ __('marketer.finance.fixed_header') }}</th>
                <th class="px-4 py-3 text-center">{{ __('marketer.finance.fee_header') }}</th>
                <th class="px-4 py-3 text-center">{{ __('marketer.finance.net_header') }}</th>
                <th class="px-4 py-3 text-center">{{ __('marketer.finance.status_header') }}</th>
                <th class="px-4 py-3 text-center">{{ __('marketer.finance.date_header') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($fixedLines as $line)
            @php
                $badge = ['reserved' => 'bg-yellow-100 text-yellow-700', 'paid' => 'bg-green-100 text-green-700', 'released' => 'bg-gray-100 text-gray-600'][$line['status']] ?? 'bg-gray-100 text-gray-600';
            @endphp
            <tr data-fixed-row="{{ $line['status'] }}">
                <td class="px-4 py-3">{{ $line['campaign_title'] }}</td>
                <td class="px-4 py-3 text-center" data-gross>{{ \App\Support\Money::format($line['gross']) }} {{ $line['currency'] }}</td>
                <td class="px-4 py-3 text-center text-red-600" data-fee>{{ $line['fee'] !== null ? '−'.\App\Support\Money::format($line['fee']) : '—' }}</td>
                <td class="px-4 py-3 text-center font-bold text-green-600" data-net>{{ $line['net'] !== null ? \App\Support\Money::format($line['net']).' '.$line['currency'] : '—' }}</td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 rounded text-xs {{ $badge }}">{{ __('marketer.finance.fixed_status_'.$line['status']) }}</span></td>
                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $line['at']?->format('Y-m-d') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif
