@extends('layouts.admin')

@section('title', __('admin.international_shipping.rates_title'))

@section('content')

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.international_shipping.rates_title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.international_shipping.rates_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.international-shipping-rates.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.international_shipping.add_rate') }}
        </a>
    </div>

    <x-card padding="none">
        <div class="overflow-x-auto">
            <table class="table-base w-full">
                <thead>
                    <tr>
                        <th>{{ __('admin.international_shipping.origin') }}</th>
                        <th>{{ __('admin.international_shipping.destination') }}</th>
                        <th>{{ __('admin.international_shipping.carrier') }}</th>
                        <th class="text-end">{{ __('admin.international_shipping.base_fee') }}</th>
                        <th class="text-end">{{ __('admin.international_shipping.rate_per_kg') }}</th>
                        <th class="text-end">{{ __('admin.international_shipping.customs_fee') }}</th>
                        <th class="text-center">{{ __('admin.international_shipping.eta') }}</th>
                        <th class="text-center">{{ __('common.active') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rates as $rate)
                        <tr>
                            <td class="font-medium text-gray-900">{{ $rate->originCountry->name_en ?? '—' }}</td>
                            <td class="font-medium text-gray-900">{{ $rate->destinationCountry->name_en ?? '—' }}</td>
                            <td>{{ $rate->carrier->name ?? __('admin.international_shipping.generic_fallback') }}</td>
                            <td class="text-end">{{ number_format($rate->base_fee) }}</td>
                            <td class="text-end">{{ number_format($rate->rate_per_kg) }}</td>
                            <td class="text-end">{{ $rate->customs_fee_flat !== null ? number_format($rate->customs_fee_flat) : '—' }}</td>
                            <td class="text-center text-gray-500">{{ $rate->min_eta_days }}–{{ $rate->max_eta_days }} {{ __('admin.international_shipping.days') }}</td>
                            <td class="text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                             {{ $rate->is_active ? 'bg-success-50 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $rate->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('admin.international-shipping-rates.edit', $rate) }}"
                                       class="p-1 rounded text-gray-400 hover:text-primary-600">
                                        <x-heroicon name="pencil-square" class="w-4 h-4" />
                                    </a>
                                    <form method="POST" action="{{ route('admin.international-shipping-rates.destroy', $rate) }}"
                                          onsubmit="return confirm('{{ __('admin.international_shipping.delete_rate_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 rounded text-gray-400 hover:text-danger-600">
                                            <x-heroicon name="trash" class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-empty-state title="{{ __('admin.international_shipping.no_rates_title') }}" description="{{ __('admin.international_shipping.no_rates_desc') }}" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

@endsection
