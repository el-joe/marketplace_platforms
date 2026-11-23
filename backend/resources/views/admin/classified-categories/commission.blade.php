@extends('layouts.admin')

@section('title', __('classified_commission.title', ['category' => localized($category)]))

@section('content')

<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold text-gray-900">{{ __('classified_commission.title', ['category' => localized($category)]) }}</h1>
        @if($category->parent)
            <p class="text-sm text-gray-500">{{ __('classified_commission.parent', ['parent' => localized($category->parent)]) }}</p>
        @endif
    </div>
    <a href="{{ route('admin.classifieds.categories.index') }}" class="btn btn-ghost btn-sm">{{ __('common.back') }}</a>
</div>

<div class="mb-6 rounded-xl border border-primary-100 bg-primary-50/50 p-4 text-sm text-gray-700">
    <ul class="space-y-2 list-disc ps-5">
        <li>{{ __('classified_commission.help.platform') }}</li>
        <li>{{ __('classified_commission.help.marketer') }} <a href="{{ route('admin.marketers.index') }}" class="text-primary-600 hover:underline">{{ __('classified_commission.help.marketer_link') }}</a></li>
        <li>{{ __('classified_commission.help.inherit') }} <a href="{{ route('admin.marketer-settings.index') }}" class="text-primary-600 hover:underline">{{ __('classified_commission.help.defaults_link') }}</a></li>
    </ul>
</div>

<form method="POST" action="{{ route('admin.classifieds.categories.commission.update', $category) }}">
    @csrf
    @method('PUT')
    <x-card>
        <div class="table-responsive">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-gray-500 border-b border-gray-100">
                        <th class="py-2 pe-4 text-start">{{ __('classified_commission.columns.country') }}</th>
                        <th class="py-2 pe-4 text-start">{{ __('classified_commission.columns.influencer') }}</th>
                        <th class="py-2 pe-4 text-start">{{ __('classified_commission.columns.fee_percentage') }}</th>
                        <th class="py-2 pe-4 text-start">{{ __('classified_commission.columns.fee_fixed') }}</th>
                        <th class="py-2 pe-4 text-start">{{ __('classified_commission.columns.campaign_fee') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($countries as $country)
                        @php
                            $row = $rows->get($country->id);
                            $inh = $inherited[$country->id];
                            $cell = fn (string $field) => old("settings.{$country->id}.{$field}", $row?->{$field} === null ? '' : (float) $row->{$field});
                        @endphp
                        <tr class="border-b border-gray-50 align-top">
                            <td class="py-2 pe-4 font-medium text-gray-900">{{ localized($country) }} <span class="text-xs text-gray-400">{{ $country->currency_code }}</span></td>
                            <td class="py-2 pe-4">
                                <input type="number" step="0.01" min="0" name="settings[{{ $country->id }}][influencer_fixed_amount]" value="{{ $cell('influencer_fixed_amount') }}" placeholder="{{ \App\Support\Money::format($inh['influencer']['amount']) }}" class="form-input w-32" @disabled(! $canEdit)>
                                <p class="text-[11px] text-gray-400 mt-1">{{ __('classified_commission.inherits_value', ['value' => \App\Support\Money::format($inh['influencer']['amount']).' '.$country->currency_code]) }}</p>
                            </td>
                            <td class="py-2 pe-4">
                                <input type="number" step="0.01" min="0" max="100" name="settings[{{ $country->id }}][commission_fee_percentage]" value="{{ $cell('commission_fee_percentage') }}" placeholder="{{ rtrim(rtrim(number_format($inh['fee']['percentage'], 4, '.', ''), '0'), '.') }}" class="form-input w-24" @disabled(! $canEdit)>
                                <p class="text-[11px] text-gray-400 mt-1">{{ __('classified_commission.inherits_value', ['value' => rtrim(rtrim(number_format($inh['fee']['percentage'], 4, '.', ''), '0'), '.').'%']) }}</p>
                            </td>
                            <td class="py-2 pe-4">
                                <input type="number" step="0.01" min="0" name="settings[{{ $country->id }}][commission_fee_fixed]" value="{{ $cell('commission_fee_fixed') }}" placeholder="{{ \App\Support\Money::format($inh['fee']['fixed']) }}" class="form-input w-28" @disabled(! $canEdit)>
                                <p class="text-[11px] text-gray-400 mt-1">{{ __('classified_commission.inherits_value', ['value' => \App\Support\Money::format($inh['fee']['fixed']).' '.$country->currency_code]) }}</p>
                            </td>
                            <td class="py-2 pe-4 text-xs text-gray-600">
                                @if($inh['campaign_fee'])
                                    {{ \App\Support\Money::format($inh['campaign_fee']->fixed_amount) }} {{ $country->currency_code }} + {{ rtrim(rtrim(number_format((float) $inh['campaign_fee']->percentage, 4, '.', ''), '0'), '.') }}%
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                                <a href="{{ route('admin.marketing-fees.index') }}" class="block text-primary-600 hover:underline">{{ __('classified_commission.campaign_fee_link') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($errors->any())
            <p class="mt-3 text-sm text-danger-600">{{ $errors->first() }}</p>
        @endif
        @if($canEdit)
            <div class="mt-4 flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.save') }}</button>
            </div>
        @endif
    </x-card>
</form>

@endsection
