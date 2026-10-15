@extends('layouts.partner')

@php
    $product = $marketerCampaign->vendorListing?->productVariant?->product;
    $currentAdTypes = old('selected_ad_types', $marketerCampaign->selected_ad_types ?? []);
@endphp

@section('title', __('partner.marketer_campaigns_my.edit'))
@section('page-title', __('partner.marketer_campaigns_my.edit'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_campaigns_my.edit') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_campaigns_my.edit_subtitle') }}</p>
        <p class="mt-1 text-sm font-medium text-gray-700">{{ $marketerCampaign->getPromotedTitle() }}</p>
    </div>

    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('partner.marketer-campaigns.update', $marketerCampaign) }}">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-2xl border border-purple-200 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.marketer_campaigns_my.field_title') }}</label>
                <input type="text" name="title" maxlength="255" value="{{ old('title', $marketerCampaign->title) }}"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.marketer_campaigns_my.field_notes') }}</label>
                <textarea name="notes" rows="2" maxlength="2000"
                          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40">{{ old('notes', $marketerCampaign->notes) }}</textarea>
            </div>

            <div class="rounded-lg border border-purple-200 bg-purple-50 px-4 py-3 text-sm text-purple-800">
                <i class="fas fa-lock me-1"></i>{{ __('marketer_campaigns.wizard.commission_set_by_platform') }}
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('partner.marketer_campaigns.max_commission_budget_label') }}
                    <span class="text-xs text-gray-400">({{ $marketerCampaign->currency }})</span>
                </label>
                <input type="number" name="max_commission_budget" min="0.01" step="0.01" value="{{ old('max_commission_budget', $marketerCampaign->max_commission_budget) }}"
                       @disabled($marketerCampaign->status !== 'pending_admin')
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40 disabled:bg-gray-50 disabled:text-gray-400"
                       placeholder="0">
                <p class="mt-1 text-xs text-gray-500">{{ __('marketer_campaigns.wizard.budget_edit_help', ['fixed' => \App\Support\Money::format($marketerCampaign->influencer_fixed_reserved).' '.$marketerCampaign->currency]) }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="ends_at">
                    {{ __('marketer_campaigns.wizard.ends_at_label') }}
                    <span class="text-xs text-gray-400">({{ __('partner.marketer_campaigns.optional') }})</span>
                </label>
                <input type="date" id="ends_at" name="ends_at" min="{{ now()->addDay()->toDateString() }}" value="{{ old('ends_at', $marketerCampaign->ends_at?->toDateString()) }}"
                       class="w-full sm:w-64 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40">
                <p class="mt-1 text-xs text-gray-500">{{ __('marketer_campaigns.wizard.ends_at_help') }}</p>
            </div>

            <div class="space-y-4">
                <div class="border-t border-gray-200 pt-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-3">{{ __('partner.marketer_campaigns.ad_types_label') }}</h5>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['story', 'post', 'video'] as $adType)
                            <label class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm cursor-pointer hover:bg-purple-50">
                                <input type="checkbox" name="selected_ad_types[]" value="{{ $adType }}" @checked(in_array($adType, (array) $currentAdTypes, true))
                                       class="rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                {{ __('partner.marketer_campaigns.ad_type_'.$adType) }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.marketer_campaigns.vendor_ad_notes_label') }}</label>
                    <textarea name="vendor_ad_notes" rows="3" maxlength="2000"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40"
                              placeholder="{{ __('partner.marketer_campaigns.vendor_ad_notes_placeholder') }}">{{ old('vendor_ad_notes', $marketerCampaign->vendor_ad_notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold px-6 py-3 rounded-xl transition-colors text-sm">
                {{ __('partner.marketer_campaigns_my.save_changes') }}
            </button>
            <a href="{{ route('partner.marketer-campaigns.show', $marketerCampaign) }}"
               class="border border-gray-200 text-gray-700 font-semibold px-6 py-3 rounded-xl text-sm hover:bg-gray-50">
                {{ __('partner.marketer_campaigns_my.back') }}
            </a>
        </div>
    </form>
</div>
@endsection
