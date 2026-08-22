@extends('layouts.admin')

@section('title', __('admin.marketer_settings.title'))

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.marketer_settings.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.marketer_settings.subtitle') }}</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ─── Influencer Fee per Country ────────────────────────────────────────── --}}
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('admin.marketer_settings.title') }}</h2>
        <p class="text-xs text-gray-500 mb-4">{{ __('admin.marketer_settings.note_affiliate_free') }}</p>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.marketer_settings.country_column') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.marketer_settings.fee_column') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.marketer_settings.currency_column') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($countries as $country)
                        @php $fee = $influencerFees->get($country->id); @endphp
                        <tr>
                            <form method="POST" action="{{ route('admin.marketer-settings.update-fee') }}" class="contents">
                                @csrf
                                <input type="hidden" name="country_id" value="{{ $country->id }}">
                                <td class="px-3 py-2 text-gray-900">{{ $country->name_ar }}</td>
                                <td class="px-3 py-2">
                                    <input type="number" min="0" step="1" name="fee_per_influencer"
                                        value="{{ old('fee_per_influencer', $fee->fee_per_influencer ?? 0) }}"
                                        class="w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" disabled
                                        value="{{ $country->currency_code }}"
                                        class="w-16 rounded-md border-gray-200 bg-gray-100 text-gray-500 shadow-sm text-sm uppercase">
                                    <input type="hidden" name="currency" value="{{ $country->currency_code }}">
                                </td>
                                <td class="px-3 py-2">
                                    <button type="submit"
                                        class="inline-flex items-center rounded-md bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                                        {{ __('common.save') }}
                                    </button>
                                </td>
                            </form>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
