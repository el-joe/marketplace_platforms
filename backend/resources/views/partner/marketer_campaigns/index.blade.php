@extends('layouts.partner')

@section('title', __('partner.marketer_campaigns_my.title'))
@section('page-title', __('partner.marketer_campaigns_my.title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    {{-- Header --}}
    @php
        $isClassifiedVendor = auth('vendor')->user()?->vendor?->vendor_type === \App\Enums\VendorType::ClassifiedVendor;
        $newCampaignUrl = request('listing')
            ? route('partner.marketer-campaigns.create', request('listing'))
            : (request('classified')
                ? route('partner.marketer-campaigns.create-classified', request('classified'))
                : ($isClassifiedVendor ? route('partner.classifieds.index') : route('partner.listings.index')));
    @endphp
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_campaigns_my.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_campaigns_my.subtitle') }}</p>
        </div>
        <a href="{{ $newCampaignUrl }}"
           class="shrink-0 bg-yellow-400 hover:bg-yellow-300 text-gray-900 text-sm font-semibold px-4 py-2 rounded-xl transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('partner.marketer_campaigns_my.new_campaign_btn') }}
        </a>
    </div>

    @if (request('listing') || request('classified'))
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <span>{{ __('partner.marketer_campaigns_my.listing_filter') }}</span>
            <span class="flex items-center gap-4">
                <a href="{{ request('classified') ? route('partner.classifieds.show', request('classified')) : route('partner.listings.show', request('listing')) }}#marketer_campaign" class="font-medium hover:underline">{{ __('partner.marketer_campaigns_my.back_to_listing') }}</a>
                <a href="{{ route('partner.marketer-campaigns.index') }}" class="font-medium hover:underline">{{ __('partner.marketer_campaigns_my.clear_filter') }}</a>
            </span>
        </div>
    @endif

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @php
        $statusMap = [
            'pending_admin' => ['label' => __('partner.marketer_campaigns_my.status.pending_admin'), 'cls' => 'bg-yellow-100 text-yellow-700'],
            'active'        => ['label' => __('partner.marketer_campaigns_my.status.active'),        'cls' => 'bg-green-100 text-green-700'],
            'auto_approved' => ['label' => __('partner.marketer_campaigns_my.status.auto_approved'),  'cls' => 'bg-blue-100 text-blue-700'],
            'rejected'      => ['label' => __('partner.marketer_campaigns_my.status.rejected'),       'cls' => 'bg-red-100 text-red-700'],
            'done'          => ['label' => __('partner.marketer_campaigns_my.status.done'),           'cls' => 'bg-gray-100 text-gray-500'],
            'cancelled'     => ['label' => __('partner.marketer_campaigns_my.status.cancelled'),      'cls' => 'bg-gray-100 text-gray-400'],
            'paused'        => ['label' => __('partner.marketer_campaigns_my.status.paused'),         'cls' => 'bg-gray-100 text-gray-600'],
            'marketer_requested' => ['label' => __('partner.marketer_campaigns_my.status.marketer_requested'), 'cls' => 'bg-yellow-100 text-yellow-700'],
        ];
    @endphp

    @include('partner.finance._marketing_totals', ['marketingTitle' => __('marketing_fees.reports.vendor_campaign_totals_title')])

    @if ($campaigns->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-200 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535"/>
            </svg>
            <p class="mt-4 text-sm text-gray-500">{{ __('partner.marketer_campaigns_my.no_campaigns') }}</p>
            <p class="mt-2 text-xs text-gray-400">{{ __('partner.marketer_campaigns_my.new_campaign_hint') }}</p>
            <a href="{{ $isClassifiedVendor ? route('partner.classifieds.index') : route('partner.listings.index') }}"
               class="mt-5 inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-300 text-gray-900 text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('partner.marketer_campaigns_my.new_campaign_btn') }}
            </a>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.item') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.country') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('marketer_campaigns.cost.budget') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('marketing_fees.campaign.list_grand_total') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.status') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.invited') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.created_at') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($campaigns as $campaign)
                        @php
                            $st = $statusMap[$campaign->status] ?? ['label' => $campaign->status, 'cls' => 'bg-gray-100 text-gray-600'];
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $campaign->getPromotedTitle() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $campaign->country?->name_ar ?? $campaign->country?->name_en ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $campaign->budgetLabel() }}</td>
                            <td class="px-4 py-3 text-gray-600" data-testid="campaign-grand-total">
                                @if ($campaign->campaign_grand_total !== null)
                                    {{ \App\Support\Money::format($campaign->campaign_grand_total) }} {{ $campaign->currency }}
                                    @if ($campaign->fulfillment_type)
                                        <span class="block text-[11px] text-gray-400">{{ $campaign->fulfillment_type->label() }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $st['cls'] }}">
                                    {{ $st['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $campaign->invitations_count }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $campaign->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-1">
                                    <a href="{{ route('partner.marketer-campaigns.show', $campaign) }}" class="text-primary-600 hover:underline font-medium">
                                        {{ __('partner.marketer_campaigns_my.view') }}
                                    </a>
                                    @if (in_array($campaign->status, ['pending_admin', 'marketer_requested'], true) && auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.edit'))
                                        <a href="{{ route('partner.marketer-campaigns.edit', $campaign) }}" class="text-gray-700 hover:underline font-medium">
                                            {{ __('partner.listings.show.marketer_campaign.edit') }}
                                        </a>
                                    @endif
                                    @if (in_array($campaign->status, ['cancelled', 'rejected', 'done', 'completed', 'pending_admin'], true) && auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.delete'))
                                        <form method="POST" action="{{ route('partner.marketer-campaigns.destroy', $campaign) }}"
                                              onsubmit="return confirm(@js(__('partner.marketer_campaigns_my.confirm_delete')))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline font-medium">{{ __('partner.marketer_campaigns_my.delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>{{ $campaigns->links() }}</div>
    @endif

</div>
@endsection
