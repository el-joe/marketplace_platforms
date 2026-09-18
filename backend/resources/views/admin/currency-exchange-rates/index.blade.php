@extends('layouts.admin')

@section('title', __('admin.international_shipping.fx_title'))

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.international_shipping.fx_title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.international_shipping.fx_subtitle') }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        {{-- ─── Add new snapshot (insert-only — no edit/delete) ───────────── --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('admin.international_shipping.fx_add_snapshot') }}</h2>
            <p class="text-xs text-gray-400">{{ __('admin.international_shipping.fx_append_only_note') }}</p>

            <form method="POST" action="{{ route('admin.currency-exchange-rates.store') }}" novalidate class="space-y-4">
                @csrf

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.from_currency') }} <span class="text-danger-500">*</span></label>
                        <input type="text" name="from_currency_code" value="{{ old('from_currency_code') }}" maxlength="3" placeholder="AED"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                        @error('from_currency_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.to_currency') }} <span class="text-danger-500">*</span></label>
                        <input type="text" name="to_currency_code" value="{{ old('to_currency_code') }}" maxlength="3" placeholder="EGP"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                        @error('to_currency_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.rate_numerator') }} <span class="text-danger-500">*</span></label>
                        <input type="number" name="rate_numerator" value="{{ old('rate_numerator') }}" min="1" step="1"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                        @error('rate_numerator')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.rate_denominator') }} <span class="text-danger-500">*</span></label>
                        <input type="number" name="rate_denominator" value="{{ old('rate_denominator', 1) }}" min="1" step="1"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                        @error('rate_denominator')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="text-xs text-gray-400">{{ __('admin.international_shipping.fx_fraction_help') }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.international_shipping.effective_at') }} <span class="text-danger-500">*</span></label>
                    <input type="datetime-local" name="effective_at" value="{{ old('effective_at', now()->format('Y-m-d\TH:i')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                    @error('effective_at')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn btn-primary btn-sm w-full">{{ __('admin.international_shipping.fx_add_snapshot') }}</button>
            </form>
        </div>

        {{-- ─── History, most recent first, grouped by pair ───────────────── --}}
        <div class="lg:col-span-2 space-y-6">
            @forelse($ratesByPair as $pair => $rates)
                <x-card padding="none">
                    <div class="px-5 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $pair }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-base w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('admin.international_shipping.effective_at') }}</th>
                                    <th class="text-end">{{ __('admin.international_shipping.rate_numerator') }}</th>
                                    <th class="text-end">{{ __('admin.international_shipping.rate_denominator') }}</th>
                                    <th>{{ __('admin.international_shipping.created_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rates as $rate)
                                    <tr>
                                        <td class="font-medium text-gray-900">{{ $rate->effective_at?->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">{{ number_format($rate->rate_numerator) }}</td>
                                        <td class="text-end">{{ number_format($rate->rate_denominator) }}</td>
                                        <td class="text-gray-500">{{ $rate->created_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @empty
                <x-empty-state title="{{ __('admin.international_shipping.fx_no_rates_title') }}" description="{{ __('admin.international_shipping.fx_no_rates_desc') }}" />
            @endforelse
        </div>
    </div>

@endsection
