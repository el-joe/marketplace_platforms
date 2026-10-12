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
                                    <input type="number" min="0" step="0.01" name="fee_per_influencer"
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

    {{-- ─── Referral code grace window (P14) ───────────────────────────────────── --}}
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm mt-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('admin.marketer_settings.referral_grace_title') }}</h2>
        <p class="text-xs text-gray-500 mb-4">{{ __('admin.marketer_settings.referral_grace_hint') }}</p>
        <form method="POST" action="{{ route('admin.marketer-settings.update-referral-grace') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketer_settings.referral_grace_days') }}</label>
                <input type="number" name="marketer_referral_grace_days" min="0" max="90" value="{{ old('marketer_referral_grace_days', $referralGraceDays) }}"
                       class="w-28 rounded border border-gray-300 px-2 py-1 text-sm">
            </div>
            <button type="submit" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ __('common.save') }}</button>
        </form>
    </div>

    {{-- ─── Peer promotion minimum stock (P13) ─────────────────────────────────── --}}
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm mt-6" data-peer-min-stock>
        <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('admin.marketer_settings.peer_min_stock_title') }}</h2>
        <p class="text-xs text-gray-500 mb-4">{{ __('admin.marketer_settings.peer_min_stock_hint') }}</p>
        <form method="POST" action="{{ route('admin.marketer-settings.update-peer-min-stock') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('admin.marketer_settings.peer_min_stock_label') }}</label>
                <input type="number" name="marketer_peer_campaign_min_stock" min="1" max="100000" value="{{ old('marketer_peer_campaign_min_stock', $peerMinStock) }}"
                       class="w-28 rounded border border-gray-300 px-2 py-1 text-sm">
                @error('marketer_peer_campaign_min_stock')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">{{ __('common.save') }}</button>
        </form>
    </div>

    {{-- ─── Monthly promotion minimums per influencer category ────────────────── --}}
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm mt-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('admin.marketer_settings.promotion_targets_title') }}</h2>
        <p class="text-xs text-gray-500 mb-4">{{ __('admin.marketer_settings.promotion_targets_hint') }}</p>

        <form method="POST" action="{{ route('admin.marketer-settings.promotion-targets.update') }}" class="flex flex-wrap items-end gap-3 mb-5">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketers.promotion_category') }}</label>
                <select name="promotion_category" class="rounded-md border-gray-300 text-sm">
                    @foreach (\App\Enums\PromotionCategory::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->value }} — {{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketer_settings.country_column') }}</label>
                <select name="country_id" class="rounded-md border-gray-300 text-sm">
                    <option value="">{{ __('admin.marketer_settings.all_countries') }}</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name_ar }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketer_settings.monthly_minimum') }}</label>
                <input type="number" min="0" max="1000" name="monthly_minimum" value="3" class="w-24 rounded-md border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketer_settings.penalty_amount') }}</label>
                <input type="number" step="0.01" min="0" name="penalty_amount" value="0" class="w-28 rounded-md border-gray-300 text-sm">
            </div>
            <button type="submit" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700">{{ __('common.save') }}</button>
        </form>

        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.marketers.promotion_category') }}</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.marketer_settings.country_column') }}</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.marketer_settings.monthly_minimum') }}</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.marketer_settings.penalty_amount') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($promotionTargets as $target)
                    <tr>
                        <td class="px-3 py-2">{{ $target->promotion_category->value }} — {{ $target->promotion_category->label() }}</td>
                        <td class="px-3 py-2">{{ $target->country?->name_ar ?? __('admin.marketer_settings.all_countries') }}</td>
                        <td class="px-3 py-2 font-semibold">{{ $target->monthly_minimum }}</td>
                        <td class="px-3 py-2">{{ number_format($target->penalty_amount) }}</td>
                        <td class="px-3 py-2 text-left">
                            <form method="POST" action="{{ route('admin.marketer-settings.promotion-targets.destroy', $target) }}" onsubmit="return confirm('{{ __('common.delete') }}?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">{{ __('common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4 text-center text-gray-400">{{ __('admin.marketer_settings.no_promotion_targets') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

@endsection
