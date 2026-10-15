{{--
    Marketer Campaign tab of the vendor listing page.
    Expects: $listing, $fulfillmentKey, $marketerCampaigns (paginator), $marketerCampaignStats, $marketerCampaignBlock
--}}
@php
    $mcT = 'partner.listings.show.marketer_campaign.';
    $mcUser = auth('vendor')->user();
    $mcCan = fn (string $p) => (bool) $mcUser?->hasPermissionTo($p);
    $mcStatusMap = [
        'pending_admin' => 'bg-yellow-100 text-yellow-700',
        'marketer_requested' => 'bg-yellow-100 text-yellow-700',
        'active' => 'bg-green-100 text-green-700',
        'auto_approved' => 'bg-blue-100 text-blue-700',
        'paused' => 'bg-gray-100 text-gray-600',
        'rejected' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-gray-100 text-gray-400',
        'done' => 'bg-gray-100 text-gray-500',
        'completed' => 'bg-gray-100 text-gray-500',
    ];
    $mcStats = $marketerCampaignStats;
    $mcByStatus = $mcStats['by_status'] ?? [];
    $mcActive = ($mcByStatus['active'] ?? 0) + ($mcByStatus['auto_approved'] ?? 0);
    $mcPending = ($mcByStatus['pending_admin'] ?? 0) + ($mcByStatus['marketer_requested'] ?? 0);
    $mcCurrency = $listing->currency;
@endphp

{{-- Header --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h3 class="font-semibold text-gray-800">{{ __($mcT.'campaigns_title') }}</h3>
    <div class="flex items-center gap-3">
        <a href="{{ route('partner.marketer-campaigns.index', ['listing' => $listing->id]) }}"
            class="text-sm text-blue-600 hover:underline">{{ __($mcT.'view_details') }}</a>
        @if($mcCan('marketer_campaigns.create'))
            @if($marketerCampaignBlock)
                <span class="inline-flex items-center gap-2 bg-gray-200 text-gray-500 text-sm font-semibold px-4 py-2.5 rounded-xl cursor-not-allowed"
                    title="{{ $marketerCampaignBlock }}" aria-disabled="true">
                    <i class="fas fa-bullhorn"></i>{{ __($mcT.'new_campaign') }}
                </span>
            @else
                <a href="{{ route('partner.marketer-campaigns.create', $listing) }}"
                    class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
                    <i class="fas fa-bullhorn"></i>{{ __($mcT.'new_campaign') }}
                </a>
            @endif
        @endif
    </div>
</div>

@if($marketerCampaignBlock && $mcCan('marketer_campaigns.create'))
    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">{{ $marketerCampaignBlock }}</p>
@endif

@if($fulfillmentKey !== 'fbn')
    <p class="text-xs text-yellow-800 bg-yellow-50 border border-yellow-300 rounded-lg p-3 mb-4">{{ __($mcT.'fbm_warning') }}</p>
@endif

{{-- Stat tiles --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
    @foreach([
        ['stat_total', $mcStats['total'] ?? 0],
        ['stat_active', $mcActive],
        ['stat_pending', $mcPending],
        ['stat_conversions', $mcStats['conversions'] ?? 0],
        ['stat_spent', \App\Support\Money::format($mcStats['commission_budget_spent'] ?? 0).' '.$mcCurrency],
    ] as [$key, $value])
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-400 mb-1">{{ __($mcT.$key) }}</p>
            <p class="text-lg font-bold text-gray-900">{{ $value }}</p>
        </div>
    @endforeach
</div>

@if($marketerCampaigns->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center">
        <p class="text-sm text-gray-400">{{ __($mcT.'empty') }}</p>
    </div>
@else
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-xs text-gray-500">
                        @foreach(['col_campaign', 'col_status', 'col_invited', 'col_fee', 'col_budget', 'col_created', 'col_actions'] as $col)
                            <th class="text-start px-4 py-3 font-medium whitespace-nowrap">{{ __($mcT.$col) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($marketerCampaigns as $campaign)
                        @php
                            $cStatus = $campaign->status;
                            $cClass = $mcStatusMap[$cStatus] ?? 'bg-gray-100 text-gray-600';
                            $cLabel = \Illuminate\Support\Facades\Lang::has('partner.marketer_campaigns_my.status.'.$cStatus)
                                ? __('partner.marketer_campaigns_my.status.'.$cStatus)
                                : $cStatus;
                            $canEdit = in_array($cStatus, ['pending_admin', 'marketer_requested'], true) && $mcCan('marketer_campaigns.edit');
                            $canInvite = $cStatus === 'active' && $mcCan('marketer_campaigns.create');
                            $canCancelPending = $cStatus === 'pending_admin' && $mcCan('marketer_campaigns.cancel');
                            $canCancelActive = in_array($cStatus, ['active', 'auto_approved', 'paused'], true) && $mcCan('marketer_campaigns.cancel');
                            $canDelete = in_array($cStatus, ['cancelled', 'rejected', 'done', 'completed', 'pending_admin'], true) && $mcCan('marketer_campaigns.delete');
                            $fee = (float) ($campaign->marketing_fee_total ?? 0);
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3">
                                <a href="{{ route('partner.marketer-campaigns.show', $campaign) }}" class="font-medium text-gray-900 hover:underline">
                                    {{ $campaign->title ?: '#'.substr($campaign->id, 0, 8) }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap {{ $cClass }}">{{ $cLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $campaign->invitations_count }} / {{ $campaign->accepted_invitations_count }}</td>
                            <td class="px-4 py-3 whitespace-nowrap {{ $fee > 0 ? 'text-orange-700' : 'text-green-600' }}">
                                {{ $fee > 0 ? \App\Support\Money::format($fee).' '.$mcCurrency : __($mcT.'free') }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ \App\Support\Money::format($campaign->commission_budget_spent) }} /
                                {{ $campaign->max_commission_budget !== null && $campaign->max_commission_budget > 0 ? \App\Support\Money::format($campaign->max_commission_budget) : __($mcT.'unlimited') }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $campaign->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium">
                                    <a href="{{ route('partner.marketer-campaigns.show', $campaign) }}" class="text-blue-600 hover:underline">{{ __($mcT.'view') }}</a>
                                    @if($canEdit)
                                        <a href="{{ route('partner.marketer-campaigns.edit', $campaign) }}" class="text-gray-700 hover:underline">{{ __($mcT.'edit') }}</a>
                                    @endif
                                    @if($canInvite)
                                        <a href="{{ route('partner.marketer-campaigns.show', $campaign) }}" class="text-purple-600 hover:underline">{{ __($mcT.'invite_more') }}</a>
                                    @endif
                                    @if($canCancelPending)
                                        <form method="POST" action="{{ route('partner.marketer-campaigns.cancel', $campaign) }}"
                                            onsubmit="return confirm(@js(__($mcT.'confirm_cancel')))">
                                            @csrf
                                            <button type="submit" class="text-amber-600 hover:underline">{{ __($mcT.'cancel') }}</button>
                                        </form>
                                    @elseif($canCancelActive)
                                        <form method="POST" action="{{ route('partner.marketer-campaigns.cancel-active', $campaign) }}"
                                            onsubmit="const r = prompt(@js(__($mcT.'cancel_reason_prompt'))); if (!r || r.trim().length < 10) return false; this.reason.value = r.trim(); return true;">
                                            @csrf
                                            <input type="hidden" name="reason" value="">
                                            <button type="submit" class="text-amber-600 hover:underline">{{ __($mcT.'cancel') }}</button>
                                        </form>
                                    @endif
                                    @if($canDelete)
                                        <form method="POST" action="{{ route('partner.marketer-campaigns.destroy', $campaign) }}"
                                            onsubmit="return confirm(@js(__($mcT.'confirm_delete')))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">{{ __($mcT.'delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($marketerCampaigns->hasPages())
        <div class="mt-4">{{ $marketerCampaigns->links() }}</div>
    @endif
@endif
