@extends('layouts.partner')

@section('title', __('partner.marketer_reports.title'))
@section('page-title', __('partner.marketer_reports.title'))

@push('scripts')
    @vite('resources/js/marketer/reports.js')
    <script>
        window.MONTHLY_EARNINGS_DATA = @json($monthlyEarnings->map(fn ($row) => [
            'month' => $row->month,
            'total' => (int) $row->total,
        ]));
    </script>
@endpush

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_reports.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_reports.subtitle') }}</p>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">{{ __('partner.marketer_reports.stats.total_campaigns') }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['total_campaigns'] }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">{{ __('partner.marketer_reports.stats.total_conversions') }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ $stats['total_conversions'] }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-xs text-gray-500">{{ __('partner.marketer_reports.stats.total_earnings') }}</div>
            <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['total_earnings']) }} {{ $conversions->first()?->currency }}</div>
        </div>
    </div>

    {{-- Monthly earnings chart --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('partner.marketer_reports.chart.title') }}</h2>
        @if ($monthlyEarnings->isEmpty())
            <p class="text-sm text-gray-500">{{ __('partner.marketer_reports.chart.no_data') }}</p>
        @else
            <canvas id="monthly-earnings-chart" height="90"></canvas>
        @endif
    </div>

    {{-- Campaign breakdown --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('partner.marketer_reports.breakdown.title') }}</h2>
        @if ($campaignBreakdown->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-gray-200 py-16 text-center">
                <p class="text-sm text-gray-500">{{ __('partner.marketer_reports.no_conversions') }}</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.breakdown.product') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.breakdown.commission_type') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.breakdown.conversions') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.breakdown.commission_earned') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.breakdown.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($campaignBreakdown as $invitation)
                            @php
                                $campaign = $invitation->campaign;
                                $product  = $campaign?->vendorListing?->productVariant?->product
                                    ?? $campaign?->adminListing?->productVariant?->product;
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $product?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ ucfirst(str_replace('_', ' ', $campaign?->commission_type ?? '—')) }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $invitation->conversions_count }}</td>
                                <td class="px-4 py-3 text-gray-900">{{ number_format($invitation->conversions_sum_commission_amount ?? 0) }} {{ $campaign?->currency }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ __('partner.marketer_invitations.status.' . $invitation->status) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($conversions->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-500">{{ __('partner.marketer_reports.no_conversions') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.table.vendor') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.table.product') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.table.order') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.table.commission') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_reports.table.date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($conversions as $conversion)
                        @php
                            $campaign = $conversion->campaign;
                            $product  = $campaign?->vendorListing?->productVariant?->product;
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-gray-700">{{ $campaign?->vendor?->store_name ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $product?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $conversion->order?->order_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ number_format($conversion->commission_amount, 2) }} {{ $conversion->currency }}</td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $conversion->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>{{ $conversions->links() }}</div>
    @endif
</div>
@endsection
