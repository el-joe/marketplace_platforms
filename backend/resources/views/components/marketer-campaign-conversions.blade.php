@props(['campaign'])

@php
    use App\Support\Money;

    $vertical = in_array($campaign->campaign_category, ['classified', 'travel'], true) ? $campaign->campaign_category : 'product';
    $statusClasses = [
        'pending' => 'bg-yellow-100 text-yellow-700',
        'approved' => 'bg-blue-100 text-blue-700',
        'paid' => 'bg-green-100 text-green-700',
        'reversed' => 'bg-red-100 text-red-700',
    ];
    $conversions = $campaign->conversions->sortByDesc('created_at');
@endphp

{{-- Conversions a campaign has earned (product sales, sold classifieds, trip bookings), with the commission broken down
     the same way the marketer sees it: basis, gross commission, platform commission fee, net. Shared by the vendor and
     travel agency campaign pages. --}}
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="campaign-conversions">
    <p class="border-b border-gray-100 bg-gray-50 px-4 py-2.5 text-xs text-gray-500">{{ __('marketer_campaigns.conversions.help_'.$vertical) }}</p>

    @if ($conversions->isEmpty())
        <p class="p-6 text-center text-sm text-gray-400">{{ __('partner.marketer_campaigns_my.no_conversions') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.reference') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.date') }}</th>
                        <th class="px-4 py-3 text-end font-semibold text-gray-600">{{ __('marketer_campaigns.conversions.basis_'.$vertical) }}</th>
                        <th class="px-4 py-3 text-end font-semibold text-gray-600">{{ __('marketer_campaigns.conversions.gross') }}</th>
                        <th class="px-4 py-3 text-end font-semibold text-gray-600">{{ __('marketer_campaigns.conversions.fee') }}</th>
                        <th class="px-4 py-3 text-end font-semibold text-gray-600">{{ __('marketer_campaigns.conversions.net') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($conversions as $conversion)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ $conversion->reference() }}
                                @if($conversion->invitation?->marketer)
                                    <span class="block text-[11px] font-normal text-gray-400">{{ $conversion->invitation->marketer->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ ($conversion->triggered_at ?? $conversion->created_at)?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 text-end text-gray-700">{{ Money::format($conversion->sale_amount ?? 0) }} {{ $conversion->currency }}</td>
                            <td class="px-4 py-3 text-end text-gray-700">{{ Money::format($conversion->gross_commission_amount ?? $conversion->commission_amount) }}</td>
                            <td class="px-4 py-3 text-end text-gray-500">{{ Money::format($conversion->commission_fee_amount ?? 0) }}</td>
                            <td class="px-4 py-3 text-end font-semibold text-gray-900">{{ Money::format($conversion->net_commission_amount ?? $conversion->commission_amount) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClasses[$conversion->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ __('marketer_campaigns.conversions.status.'.$conversion->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
