@props(['campaign', 'calculations', 'price' => 0])

@php
    $marketerCampaign = $campaign;
    $promotedTitle = $campaign->getPromotedTitle();
    $isProductCampaign = ! in_array($campaign->campaign_category, ['classified', 'travel'], true);
@endphp

{{-- Shared by the partner (vendor) and travel agency campaign pages: facts, cost, how each marketer is paid, timeline. --}}
<div class="space-y-6">
@if (! $isProductCampaign)
    <div class="rounded-lg border border-purple-200 bg-purple-50 px-4 py-3 text-xs text-purple-800" data-testid="campaign-approval-hint">
        {{ __('marketer_campaigns.vertical.'.$campaign->campaign_category.'.approval_hint') }}
    </div>
@endif
<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <h3 class="mb-4 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.tab_info') }}</h3>
    <div class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm md:grid-cols-3">
        @foreach (array_filter([
            __('partner.marketer_campaigns_my.field.item') => $promotedTitle,
            __('partner.marketer_campaigns_my.field.country') => $marketerCampaign->country?->name_ar ?? $marketerCampaign->country?->name_en ?? '—',
            __('partner.marketer_campaigns_my.field.currency') => $marketerCampaign->currency ?? '—',
            __('partner.marketer_campaigns_my.field.max_commission_budget') => $marketerCampaign->max_commission_budget !== null ? \App\Support\Money::format($marketerCampaign->max_commission_budget) : '—',
            __('marketer_campaigns.cost.ends_at') => $marketerCampaign->ends_at?->format('Y/m/d') ?? __('marketer_campaigns.cost.no_end_date'),
            __('marketer_campaigns.wizard.audience') => $marketerCampaign->open_to_affiliates ? __('marketer_campaigns.wizard.audience_open') : __('marketer_campaigns.wizard.audience_picked'),
            __('partner.marketer_campaigns_my.field.platform_sample_qty') => $isProductCampaign ? ($marketerCampaign->platform_sample_qty_snapshot ?? 0) : null,
            __('partner.marketer_campaigns_my.field.per_marketer_sample_qty') => $isProductCampaign ? ($marketerCampaign->per_marketer_sample_qty_snapshot ?? 0) : null,
            __('partner.marketer_campaigns_my.field.created_at') => $marketerCampaign->created_at->format('Y-m-d H:i'),
            __('partner.marketer_campaigns_my.field.reviewed_at') => $marketerCampaign->reviewed_at?->format('Y-m-d H:i') ?? '—',
            __('partner.marketer_campaigns_my.field.auto_approve_at') => $marketerCampaign->auto_approve_at?->format('Y-m-d H:i') ?? '—',
        ], fn ($v) => $v !== null) as $label => $value)
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</dt>
                <dd class="mt-0.5 font-medium text-gray-900">{{ $value }}</dd>
            </div>
        @endforeach
        @if ($marketerCampaign->notes)
            <div class="col-span-2 md:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('partner.marketer_campaigns_my.field.notes') }}</dt>
                <dd class="mt-0.5 text-gray-700">{{ $marketerCampaign->notes }}</dd>
            </div>
        @endif
        @if ($marketerCampaign->selected_ad_types && count($marketerCampaign->selected_ad_types) > 0)
            <div class="col-span-2 md:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-1">{{ __('partner.marketer_campaigns_my.field.selected_ad_types') }}</dt>
                <dd class="flex flex-wrap gap-2 mt-1">
                    @php
                        $adTypeLabels = ['story' => __('partner.marketer_campaigns.ad_type_story'), 'post' => __('partner.marketer_campaigns.ad_type_post'), 'video' => __('partner.marketer_campaigns.ad_type_video')];
                    @endphp
                    @foreach ($marketerCampaign->selected_ad_types as $adType)
                        <span class="inline-flex items-center rounded-full bg-purple-100 text-purple-700 px-3 py-1 text-xs font-semibold">
                            {{ $adTypeLabels[$adType] ?? $adType }}
                        </span>
                    @endforeach
                </dd>
            </div>
        @endif
        @if ($marketerCampaign->vendor_ad_notes)
            <div class="col-span-2 md:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('partner.marketer_campaigns_my.field.vendor_ad_notes') }}</dt>
                <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap">{{ $marketerCampaign->vendor_ad_notes }}</dd>
            </div>
        @endif
        @if ($marketerCampaign->status === 'rejected' && $marketerCampaign->rejection_reason)
            <div class="col-span-2 md:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('partner.marketer_campaigns_my.field.rejection_reason') }}</dt>
                <dd class="mt-0.5 text-red-700">{{ $marketerCampaign->rejection_reason }}</dd>
            </div>
        @endif
    </div>
</div>
@if ($marketerCampaign->vendor_listing_id || $marketerCampaign->classified_listing_id || $marketerCampaign->travel_package_id)
    <x-marketer-campaign-cost :campaign="$marketerCampaign" audience="vendor" />
@endif
<x-marketer-campaign-calculations :campaign="$marketerCampaign" :calculations="$calculations" :price="$price" />

{{-- Status timeline --}}
<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <h3 class="mb-4 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.status_timeline') }}</h3>
    <ol class="space-y-3 text-sm">
        <li class="flex items-center gap-3">
            <span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>
            <span class="text-gray-700">{{ __('partner.marketer_campaigns_my.timeline.created') }}</span>
            <span class="text-gray-400">{{ $marketerCampaign->created_at->format('Y-m-d H:i') }}</span>
        </li>
        @if ($marketerCampaign->reviewed_at)
            <li class="flex items-center gap-3">
                <span class="h-2.5 w-2.5 rounded-full {{ $marketerCampaign->status === 'rejected' ? 'bg-red-500' : 'bg-green-500' }}"></span>
                <span class="text-gray-700">
                    {{ $marketerCampaign->status === 'rejected' ? __('partner.marketer_campaigns_my.timeline.rejected') : __('partner.marketer_campaigns_my.timeline.reviewed') }}
                </span>
                <span class="text-gray-400">{{ $marketerCampaign->reviewed_at->format('Y-m-d H:i') }}</span>
            </li>
        @elseif ($marketerCampaign->auto_approved)
            <li class="flex items-center gap-3">
                <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                <span class="text-gray-700">{{ __('partner.marketer_campaigns_my.timeline.auto_approved') }}</span>
            </li>
        @endif
        @if (in_array($marketerCampaign->status, ['cancelled', 'done', 'completed'], true))
            <li class="flex items-center gap-3">
                <span class="h-2.5 w-2.5 rounded-full {{ $marketerCampaign->status === 'cancelled' ? 'bg-red-500' : 'bg-gray-500' }}"></span>
                <span class="text-gray-700">{{ $marketerCampaign->status === 'cancelled' ? __('partner.marketer_campaigns_my.timeline.cancelled') : __('partner.marketer_campaigns_my.timeline.done') }}</span>
                <span class="text-gray-400">{{ $marketerCampaign->updated_at->format('Y-m-d H:i') }}</span>
            </li>
        @endif
    </ol>
</div>
</div>
