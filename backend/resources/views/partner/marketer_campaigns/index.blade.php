@extends('layouts.partner')

@section('title', __('partner.marketer_campaigns_my.title'))
@section('page-title', __('partner.marketer_campaigns_my.title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_campaigns_my.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_campaigns_my.subtitle') }}</p>
    </div>

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
        ];
    @endphp

    @if ($campaigns->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-200 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535"/>
            </svg>
            <p class="mt-4 text-sm text-gray-500">{{ __('partner.marketer_campaigns_my.no_campaigns') }}</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.product') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.country') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.commission_type') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.status') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.invited') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.table.created_at') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($campaigns as $campaign)
                        @php
                            $product = $campaign->vendorListing?->productVariant?->product
                                ?? $campaign->adminListing?->productVariant?->product;
                            $st = $statusMap[$campaign->status] ?? ['label' => $campaign->status, 'cls' => 'bg-gray-100 text-gray-600'];
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $product?->name_ar ?? $product?->name_en ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $campaign->country?->name_ar ?? $campaign->country?->name_en ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ __('partner.marketer_campaigns_my.commission_type.' . $campaign->commission_type) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $st['cls'] }}">
                                    {{ $st['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $campaign->invitations_count }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $campaign->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('partner.marketer-campaigns.show', $campaign) }}" class="text-primary-600 hover:underline font-medium">
                                    {{ __('partner.marketer_campaigns_my.view') }}
                                </a>
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
