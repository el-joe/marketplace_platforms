@extends('layouts.partner')

@section('title', __('partner.finance.sales_report'))
@section('page-title', __('partner.finance.sales_report'))

@section('content')

    @php $currency ??= auth()->guard('vendor')->user()->vendor?->country?->currency_code ?? '' @endphp

    {{-- Summary stat cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.finance.shipping_charged_to_customers') }}</p>
            <p class="text-2xl font-bold text-gray-800">
                {{ number_format($totals->total_shipping_charged / 100, 2) }}
                <span class="text-sm font-normal text-gray-400">{{ $currency }}</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ __('partner.finance.shipping_charged_to_customers_desc') }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <p class="text-xs text-gray-500 mb-1">{{ __('partner.finance.platform_delivery_subsidy') }}</p>
            <p class="text-2xl font-bold text-green-600">
                {{ number_format($totals->total_platform_subsidy / 100, 2) }}
                <span class="text-sm font-normal text-gray-400">{{ $currency }}</span>
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ __('partner.finance.platform_delivery_subsidy_desc') }}</p>
        </div>

        @if($hasVendorContribution)
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <p class="text-xs text-gray-500 mb-1">{{ __('partner.finance.delivery_cost_you_covered') }}</p>
                <p class="text-2xl font-bold text-red-500">
                    {{ number_format($totals->total_vendor_contribution / 100, 2) }}
                    <span class="text-sm font-normal text-gray-400">{{ $currency }}</span>
                </p>
                <p class="text-xs text-gray-400 mt-1">{{ __('partner.finance.delivery_cost_you_covered_desc') }}</p>
            </div>
        @endif

        @if($hasExceptionalDeduction)
            <div class="bg-white rounded-2xl border border-orange-200 p-5">
                <p class="text-xs text-gray-500 mb-1">{{ __('partner.finance.exceptional_zone_deduction') }}</p>
                <p class="text-2xl font-bold text-orange-600">
                    {{ number_format($exceptionalTotals->total_exceptional_deduction / 100, 2) }}
                    <span class="text-sm font-normal text-gray-400">{{ $currency }}</span>
                </p>
                <p class="text-xs text-gray-400 mt-1">{{ __('partner.finance.exceptional_zone_deduction_desc') }}</p>
            </div>
        @endif

    </div>

    {{-- Shipments table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-gray-800">{{ __('partner.finance.shipments') }}</h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400">{{ $shipments->total() }}</span>
                    <a href="{{ route('partner.finance.sales-report.export', request()->query()) }}"
                       class="text-xs font-medium text-primary-600 hover:underline">
                        {{ __('partner.finance.export') }}
                    </a>
                    <a href="{{ route('partner.finance.sales-report.export', array_merge(request()->query(), ['format' => 'excel'])) }}"
                       class="text-xs font-medium text-primary-600 hover:underline">{{ __('common.export_excel') }}</a>
                    <a href="{{ route('partner.finance.sales-report.export', array_merge(request()->query(), ['format' => 'word'])) }}"
                       class="text-xs font-medium text-primary-600 hover:underline">{{ __('common.export_word') }}</a>
                </div>
            </div>
        </div>

        @if($shipments->isEmpty())
            <div class="py-16 text-center">
                <div class="text-4xl mb-3">📦</div>
                <h3 class="font-semibold text-gray-800 mb-1">{{ __('partner.finance.no_shipments_found') }}</h3>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr class="text-xs text-gray-500 uppercase">
                            <th class="text-right py-3 px-5 font-medium">{{ __('common.date') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('partner.finance.shipment_reference') }}</th>
                            <th class="py-3 px-4 text-left font-medium">{{ __('partner.finance.shipping_charged_to_customers') }}</th>
                            <th class="py-3 px-4 text-left font-medium">{{ __('partner.finance.delivery_subsidy') }}</th>
                            @if($hasVendorContribution)
                                <th class="py-3 px-4 text-left font-medium">{{ __('partner.finance.your_delivery_contribution') }}</th>
                            @endif
                            @if($hasExceptionalDeduction)
                                <th class="py-3 px-4 text-left font-medium">{{ __('partner.finance.exceptional_zone_deduction') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($shipments as $shipment)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-5 text-xs text-gray-500 whitespace-nowrap">
                                    {{ $shipment->created_at->format('Y/m/d') }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-mono text-xs text-gray-700">{{ $shipment->sub_order_number }}</span>
                                    <span class="block text-xs text-gray-400">{{ $shipment->order?->order_number }}</span>
                                </td>
                                <td class="py-3 px-4 text-left text-gray-800 whitespace-nowrap">
                                    {{ number_format($shipment->shipping / 100, 2) }} {{ $currency }}
                                </td>
                                <td class="py-3 px-4 text-left text-green-600 whitespace-nowrap">
                                    {{ number_format($shipment->admin_subsidy_amount / 100, 2) }} {{ $currency }}
                                </td>
                                @if($hasVendorContribution)
                                    <td class="py-3 px-4 text-left text-red-500 whitespace-nowrap">
                                        {{ number_format($shipment->vendor_contribution_amount / 100, 2) }} {{ $currency }}
                                    </td>
                                @endif
                                @if($hasExceptionalDeduction)
                                    <td class="py-3 px-4 text-left text-orange-600 whitespace-nowrap">
                                        @if($shipment->shipping_gap > 0)
                                            - {{ number_format($shipment->vendor_contribution_amount / 100, 2) }} {{ $currency }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($shipments->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $shipments->links() }}
                </div>
            @endif
        @endif

    </div>

    <p class="text-xs text-gray-400 mt-4">* {{ __('partner.finance.subsidy_footnote') }}</p>

@endsection
