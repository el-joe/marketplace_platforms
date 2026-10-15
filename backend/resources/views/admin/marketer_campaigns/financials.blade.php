@extends('layouts.admin')

@section('title', __('admin.marketer_campaigns.financials_title'))

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.marketer_campaigns.financials_title') }}</h1>
    <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.marketer_campaigns.financials_subtitle') }}</p>
</div>

{{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
<x-card class="mb-5">
    <form method="GET" action="{{ route('admin.marketer-campaigns.financials') }}" class="flex flex-wrap gap-3 items-end">
        <div class="w-44">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.marketer_campaigns.date_from') }}</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input w-full text-sm">
        </div>
        <div class="w-44">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.marketer_campaigns.date_to') }}</label>
            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input w-full text-sm">
        </div>
        <div class="w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.marketer_campaigns.country_column') }}</label>
            <select name="country_id" class="form-input w-full text-sm">
                <option value="">{{ __('admin.marketer_campaigns.all_countries') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" {{ (string) request('country_id') === (string) $country->id ? 'selected' : '' }}>
                        {{ $country->name_en }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.marketer_campaigns.filter') }}</button>
        <a href="{{ route('admin.marketer-campaigns.financials') }}" class="btn btn-ghost btn-sm">{{ __('admin.marketer_campaigns.reset') }}</a>
    </form>
</x-card>

{{-- ─── Summary per currency ───────────────────────────────────────────────── --}}
@forelse($summaryByCurrency as $summary)
    <x-card class="mb-4">
        <div class="text-sm font-semibold text-gray-700 mb-3">{{ $summary->currency }} · {{ $summary->total_campaigns }} {{ __('marketer_campaigns.financials.campaigns') }}</div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-xs text-gray-500 mb-1">{{ __('marketer_campaigns.cost.budget') }}</div>
                <div class="text-lg font-bold text-gray-900">{{ \App\Support\Money::format($summary->total_budget) }}</div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-xs text-gray-500 mb-1">{{ __('marketer_campaigns.cost.influencers_fixed') }}</div>
                <div class="text-lg font-bold text-purple-700">{{ \App\Support\Money::format($summary->influencer_fixed_total) }}</div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-xs text-gray-500 mb-1">{{ __('marketer_campaigns.cost.commissions_spent') }}</div>
                <div class="text-lg font-bold text-red-600">{{ \App\Support\Money::format($summary->commissions_spent) }}</div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-xs text-gray-500 mb-1">{{ __('marketer_campaigns.financials.fees_collected') }}</div>
                <div class="text-lg font-bold text-green-700">{{ \App\Support\Money::format($summary->collected_fees) }}</div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <div class="text-xs text-gray-500 mb-1">{{ __('marketer_campaigns.financials.fees_pending') }}</div>
                <div class="text-lg font-bold text-orange-600">{{ \App\Support\Money::format($summary->pending_fees) }}</div>
            </div>
        </div>
    </x-card>
@empty
    <x-card class="mb-4">
        <div class="text-sm text-gray-500 text-center py-4">{{ __('admin.marketer_campaigns.no_campaigns_period') }}</div>
    </x-card>
@endforelse

{{-- ─── Campaigns table ────────────────────────────────────────────────────── --}}
<x-card>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 uppercase tracking-wide border-b">
                    <th class="text-start py-2 px-3">{{ __('admin.marketer_campaigns.vendor_column') }}</th>
                    <th class="text-start py-2 px-3">{{ __('admin.marketer_campaigns.country_column') }}</th>
                    <th class="text-start py-2 px-3">{{ __('marketer_campaigns.cost.budget') }}</th>
                    <th class="text-start py-2 px-3">{{ __('marketer_campaigns.cost.influencers_fixed') }}</th>
                    <th class="text-start py-2 px-3">{{ __('marketer_campaigns.cost.commissions_spent') }}</th>
                    <th class="text-start py-2 px-3">{{ __('marketer_campaigns.cost.campaign_fee') }}</th>
                    <th class="text-start py-2 px-3">{{ __('marketer_campaigns.cost.grand_total') }}</th>
                    <th class="text-start py-2 px-3">{{ __('admin.marketer_campaigns.fee_status_column') }}</th>
                    <th class="text-start py-2 px-3">{{ __('admin.marketer_campaigns.conversions_column') }}</th>
                    <th class="text-start py-2 px-3">{{ __('admin.marketer_campaigns.actions_column') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($campaigns as $campaign)
                    <tr>
                        <td class="py-2 px-3">{{ $campaign->vendor?->store_name ?? '—' }}</td>
                        <td class="py-2 px-3">{{ $campaign->country?->name_en ?? '—' }}</td>
                        <td class="py-2 px-3">{{ (float) $campaign->max_commission_budget > 0 ? \App\Support\Money::format($campaign->max_commission_budget).' '.$campaign->currency : __('marketer_campaigns.cost.unlimited') }}</td>
                        <td class="py-2 px-3 text-purple-700">{{ \App\Support\Money::format($campaign->influencer_fixed_reserved) }}</td>
                        <td class="py-2 px-3 text-red-600">{{ \App\Support\Money::format($campaign->commission_budget_spent) }}</td>
                        <td class="py-2 px-3" data-testid="financials-marketing-fee">
                            @if($campaign->campaign_grand_total !== null)
                                {{ \App\Support\Money::format($campaign->marketing_fee_total) }} {{ $campaign->currency }}
                                @if($campaign->fulfillment_type)<span class="block text-[11px] text-gray-400">{{ $campaign->fulfillment_type->label() }}</span>@endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="py-2 px-3" data-testid="financials-grand-total">{{ $campaign->campaign_grand_total !== null ? \App\Support\Money::format($campaign->campaign_grand_total).' '.$campaign->currency : '—' }}</td>
                        <td class="py-2 px-3">
                            @if($campaign->campaign_fee_status && $campaign->campaign_fee_status !== 'not_applicable')
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $campaign->campaign_fee_status === 'paid' ? 'bg-green-100 text-green-700' : ($campaign->campaign_fee_status === 'pending' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ __('marketer_campaigns.cost.fee_status.'.$campaign->campaign_fee_status) }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="py-2 px-3">{{ $campaign->conversions_count }}</td>
                        <td class="py-2 px-3">
                            <a href="{{ route('admin.marketer-campaigns.show', $campaign) }}" class="text-primary-600 hover:underline">{{ __('admin.marketer_campaigns.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-gray-500 py-6">{{ __('admin.marketer_campaigns.no_campaigns') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $campaigns->links() }}
    </div>
</x-card>

@endsection
