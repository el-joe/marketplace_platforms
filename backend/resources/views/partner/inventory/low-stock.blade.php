@extends('layouts.partner')

@section('title', __('partner.inventory.low_stock'))
@section('page-title', __('partner.inventory.low_stock'))

@section('content')

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-gray-500">{{ __('partner.inventory.low_stock_desc') }}</p>
        <div class="flex gap-2">
            <a href="{{ route('partner.inventory.out-of-stock') }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                🚫 {{ __('partner.inventory.out_of_stock') }}
            </a>
            <a href="{{ route('partner.inventory.index') }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                {{ __('partner.inventory.back_to_inventory') }}
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

        @if($rows->isEmpty())
            <div class="py-16 text-center">
                <div class="text-4xl mb-3">✅</div>
                <h3 class="font-semibold text-gray-800 mb-1">{{ __('partner.inventory.low_stock_empty_title') }}</h3>
                <p class="text-sm text-gray-400">{{ __('partner.inventory.low_stock_empty_desc') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr class="text-xs text-gray-500 uppercase">
                            <th class="text-right py-3 px-5 font-medium">{{ __('partner.inventory.table.product') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('partner.inventory.table_extra.variant') }}</th>
                            <th class="text-right py-3 px-4 font-medium">{{ __('partner.inventory.table.warehouse') }}</th>
                            <th class="py-3 px-4 text-center font-medium">{{ __('partner.inventory.table.on_hand') }}</th>
                            <th class="py-3 px-4 text-center font-medium">{{ __('partner.inventory.table.reserved') }}</th>
                            <th class="py-3 px-4 text-center font-medium">{{ __('partner.inventory.table.available') }}</th>
                            <th class="py-3 px-4 text-center font-medium">{{ __('partner.inventory.table_extra.alert_threshold') }}</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($rows as $row)
                            @php
                                $available = $row->quantity_on_hand - $row->quantity_reserved;
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-5">
                                    <p class="font-medium text-gray-800 leading-tight">
                                        {{ $row->product_name_ar ?: $row->product_name_en }}</p>
                                    @if($row->product_name_ar && $row->product_name_en)
                                        <p class="text-xs text-gray-400">{{ $row->product_name_en }}</p>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <p class="text-gray-700 text-xs">{{ $row->variant_name ?: __('partner.inventory.default_variant') }}</p>
                                    <p class="font-mono text-gray-400 text-xs">{{ $row->sku }}</p>
                                </td>
                                <td class="py-3 px-4 text-gray-600 text-xs">{{ $row->warehouse_name }}</td>
                                <td class="py-3 px-4 text-center font-medium text-gray-800">{{ $row->quantity_on_hand }}</td>
                                <td class="py-3 px-4 text-center text-gray-500">{{ $row->quantity_reserved }}</td>
                                <td class="py-3 px-4 text-center">
                                    <span
                                        class="font-bold {{ $available <= 0 ? 'text-red-600' : 'text-orange-500' }}">{{ $available }}</span>
                                </td>
                                <td class="py-3 px-4 text-center text-gray-400 text-xs">{{ $row->low_stock_threshold }}</td>
                                <td class="py-3 px-4 text-right">
                                    <a href="{{ route('partner.listings.show', $row->listing_id) }}"
                                        class="text-xs text-blue-600 hover:underline whitespace-nowrap">{{ __('partner.inventory.view_listing') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($rows->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $rows->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection