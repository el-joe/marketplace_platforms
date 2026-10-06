@extends('layouts.partner')

@section('title', __('partner.marketer_campaigns.create_title'))
@section('page-title', __('partner.marketer_campaigns.create_title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_campaigns.create_title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_campaigns.create_subtitle') }}</p>
    </div>

    @if(session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Listing summary --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center gap-3">
            @if($vendorListing->productVariant->product->image_url ?? false)
                <img src="{{ $vendorListing->productVariant->product->image_url }}" class="w-14 h-14 rounded-lg object-cover">
            @endif
            <div>
                <div class="font-semibold text-gray-900">
                    {{ $vendorListing->productVariant->product->name_en ?? '—' }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ $vendorListing->currency }} {{ number_format($vendorListing->price) }}
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('partner.marketer-campaigns.store') }}" x-data="campaignCreateForm()">
        @csrf
        <input type="hidden" name="vendor_listing_id" value="{{ $vendorListing->id }}">

        <div class="bg-white rounded-2xl border border-purple-200 p-6 space-y-4">
            @if($marketerVendors->isEmpty())
                <p class="text-xs text-amber-600 bg-amber-50 rounded-lg p-3">
                    {{ __('partner.marketer_campaigns.no_marketers_available') }}
                </p>
            @else
                <x-form.select
                    name="marketer_ids"
                    label="{{ __('partner.marketer_campaigns.select_marketers_label') }}"
                    :multiple="true"
                    :select2="true"
                    placeholder="{{ __('partner.marketer_campaigns.select_marketers_placeholder') }}"
                    x-on:change="updateSelectedMarketers($event)"
                >
                    @foreach($marketerVendors as $m)
                        <option value="{{ $m->id }}"
                                data-type="{{ $m->marketerJobs->first()?->key }}"
                                data-name="{{ $m->name }}"
                                data-story-price="{{ $m->marketerProfile?->story_price }}"
                                data-post-price="{{ $m->marketerProfile?->post_price }}"
                                data-video-price="{{ $m->marketerProfile?->video_price }}">
                            {{ $m->name }} — {{ $m->isInfluencer() ? __('partner.marketer_campaigns.influencer_label') : __('partner.marketer_campaigns.affiliate_label') }}
                        </option>
                    @endforeach
                </x-form.select>

                <div x-show="selectedMarketers.length > 0" x-cloak class="mt-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-receipt text-orange-500 mr-1"></i>
                        {{ __('partner.marketer_campaigns.fee_details_title') }}
                    </h5>
                    <div class="rounded-lg border border-gray-200 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-right px-4 py-2 text-gray-600 font-medium">{{ __('partner.marketer_campaigns.marketer_col') }}</th>
                                    <th class="text-center px-4 py-2 text-gray-600 font-medium">{{ __('partner.marketer_campaigns.type_col') }}</th>
                                    <th class="text-center px-4 py-2 text-gray-600 font-medium">{{ __('partner.marketer_campaigns.fees_col') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="marketer in selectedMarketers" :key="marketer.id">
                                    <tr class="border-t border-gray-100">
                                        <td class="px-4 py-2 text-gray-800" x-text="marketer.name"></td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-xs"
                                                  :class="marketer.type === 'influencer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                                                  x-text="marketer.type === 'influencer' ? '{{ __('partner.marketer_campaigns.influencer_label') }}' : '{{ __('partner.marketer_campaigns.affiliate_label') }}'">
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-center font-medium"
                                            :class="marketer.type === 'influencer' && feePerInfluencer > 0 ? 'text-orange-700' : 'text-green-600'">
                                            <span x-show="marketer.type === 'influencer' && feePerInfluencer > 0"
                                                  x-text="feePerInfluencer + ' ' + currency"></span>
                                            <span x-show="!(marketer.type === 'influencer' && feePerInfluencer > 0)" class="text-green-600">{{ __('partner.marketer_campaigns.free_label') }}</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 font-semibold text-gray-700 text-right">{{ __('partner.marketer_campaigns.platform_fees_total') }}</td>
                                    <td class="px-4 py-2 text-center font-bold"
                                        :class="totalInfluencerFee > 0 ? 'text-orange-700' : 'text-green-600'">
                                        <span x-show="totalInfluencerFee > 0" x-text="totalInfluencerFee + ' ' + currency"></span>
                                        <span x-show="totalInfluencerFee === 0" class="text-green-600">{{ __('partner.marketer_campaigns.free_label') }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        {{ __('partner.marketer_campaigns.platform_fee_note') }}
                    </p>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.marketer_campaigns.commission_type_label') }}</label>
                <select name="commission_type" x-model="commissionType"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40">
                    <option value="fixed">{{ __('partner.listings.commission_type_fixed') }}</option>
                    <option value="tiered">{{ __('partner.listings.commission_type_tiered') }}</option>
                    <option value="last_click">{{ __('partner.listings.commission_type_last_click') }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('partner.marketer_campaigns.max_commission_budget_label') }}
                    <span class="text-xs text-gray-400">({{ auth()->guard('vendor')->user()->vendor->country->currency_code ?? '' }})</span>
                </label>
                <input type="number" name="max_commission_budget" min="0"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40"
                       placeholder="0">
            </div>

            <div x-show="commissionType === 'tiered'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('partner.marketer_campaigns.tiered_rules_label') }}</label>
                <div class="space-y-2">
                    <template x-for="(rule, i) in tieredRules" :key="i">
                        <div class="flex gap-2 items-center">
                            <input type="number" :name="`tiered_rules[${i}][from_sale_number]`"
                                   x-model="rule.from_sale_number"
                                   placeholder="{{ __('partner.marketer_campaigns.sale_number_placeholder') }}"
                                   class="w-1/2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
                            <input type="number" :name="`tiered_rules[${i}][commission_amount]`"
                                   x-model="rule.commission_amount"
                                   placeholder="{{ __('partner.marketer_campaigns.commission_amount_placeholder') }}"
                                   class="w-1/2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
                            <button type="button" @click="tieredRules.splice(i, 1)" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="tieredRules.push({from_sale_number: '', commission_amount: ''})"
                        class="mt-2 text-sm text-purple-600 hover:underline">
                    {{ __('partner.marketer_campaigns.add_tier_btn') }}
                </button>
            </div>

            <div class="p-3 bg-purple-50 rounded-lg text-sm text-purple-800">
                <i class="fas fa-box-open mr-1"></i>
                {{ __('partner.marketer_campaigns.expected_samples_label') }} <strong x-text="selectedMarketers.length"></strong> {{ __('partner.marketer_campaigns.marketers_selected_suffix') }}
                <span class="block text-xs text-gray-500 mt-1">
                    {{ __('partner.marketer_campaigns.samples_auto_note') }}
                </span>
            </div>

            {{-- Ad type selection — shown only when influencers are selected --}}
            <div x-show="hasInfluencers" x-cloak>
                <div class="border-t border-gray-200 pt-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-3">
                        <i class="fas fa-photo-film text-purple-500 mr-1"></i>
                        {{ __('partner.marketer_campaigns.ad_types_label') }}
                    </h5>
                    <div class="space-y-3">
                        <template x-for="type in availableAdTypes" :key="type.key">
                            <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer hover:bg-purple-50 transition-colors"
                                   :class="selectedAdTypes.includes(type.key) ? 'border-purple-400 bg-purple-50' : ''">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox"
                                           name="selected_ad_types[]"
                                           :value="type.key"
                                           @change="toggleAdType(type.key)"
                                           :checked="selectedAdTypes.includes(type.key)"
                                           class="rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                    <span class="font-medium text-gray-800 text-sm" x-text="type.label"></span>
                                </div>
                                <div class="text-sm font-semibold" x-show="type.minPrice !== null">
                                    <span class="text-purple-700" x-text="type.priceRange"></span>
                                    <span class="text-gray-400 text-xs ms-1" x-text="currency"></span>
                                </div>
                                <div class="text-xs text-gray-400" x-show="type.minPrice === null">
                                    {{ __('partner.marketer_campaigns.ad_type_price_varies') }}
                                </div>
                            </label>
                        </template>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        {{ __('partner.marketer_campaigns.ad_types_note') }}
                    </p>
                </div>
            </div>

            {{-- Notes for influencer --}}
            <div x-show="hasInfluencers" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('partner.marketer_campaigns.vendor_ad_notes_label') }}
                    <span class="text-xs text-gray-400">({{ __('partner.marketer_campaigns.optional') }})</span>
                </label>
                <textarea name="vendor_ad_notes" rows="3"
                          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40"
                          placeholder="{{ __('partner.marketer_campaigns.vendor_ad_notes_placeholder') }}"></textarea>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold px-6 py-3 rounded-xl transition-colors text-sm">
                {{ __('partner.marketer_campaigns.create_btn') }}
            </button>
            <a href="{{ route('partner.marketer-campaigns.index') }}"
               class="border border-gray-200 text-gray-700 font-semibold px-6 py-3 rounded-xl text-sm hover:bg-gray-50">
                {{ __('partner.marketer_campaigns.cancel_btn') }}
            </a>
        </div>
    </form>

</div>

@push('scripts')
    <script>
        function campaignCreateForm() {
            return {
                commissionType: 'fixed',
                tieredRules: [],
                selectedMarketers: [],
                feePerInfluencer: 0,
                currency: '',
                selectedAdTypes: [],
                get hasInfluencers() {
                    return this.selectedMarketers.some(m => m.type === 'influencer');
                },
                get totalInfluencerFee() {
                    const count = this.selectedMarketers.filter(m => m.type === 'influencer').length;
                    return count * this.feePerInfluencer;
                },
                get availableAdTypes() {
                    const influencers = this.selectedMarketers.filter(m => m.type === 'influencer');
                    const adTypeKeys = ['story', 'post', 'video'];
                    const labels = {
                        story: '{{ __("partner.marketer_campaigns.ad_type_story") }}',
                        post:  '{{ __("partner.marketer_campaigns.ad_type_post") }}',
                        video: '{{ __("partner.marketer_campaigns.ad_type_video") }}',
                    };
                    return adTypeKeys.map(key => {
                        const prices = influencers
                            .map(m => m[key + 'Price'])
                            .filter(p => p !== null && p !== undefined && p !== '');
                        const minPrice = prices.length ? Math.min(...prices) : null;
                        const maxPrice = prices.length ? Math.max(...prices) : null;
                        let priceRange = null;
                        if (minPrice !== null) {
                            priceRange = minPrice === maxPrice ? String(minPrice) : minPrice + ' – ' + maxPrice;
                        }
                        return { key, label: labels[key], minPrice, priceRange };
                    });
                },
                toggleAdType(key) {
                    const idx = this.selectedAdTypes.indexOf(key);
                    if (idx === -1) {
                        this.selectedAdTypes.push(key);
                    } else {
                        this.selectedAdTypes.splice(idx, 1);
                    }
                },
                updateSelectedMarketers(event) {
                    const select = event.target;
                    this.selectedMarketers = Array.from(select.selectedOptions || []).map(opt => ({
                        id: opt.value,
                        name: opt.dataset.name || opt.text,
                        type: opt.dataset.type || 'affiliate',
                        storyPrice: opt.dataset.storyPrice ? parseInt(opt.dataset.storyPrice) : null,
                        postPrice: opt.dataset.postPrice ? parseInt(opt.dataset.postPrice) : null,
                        videoPrice: opt.dataset.videoPrice ? parseInt(opt.dataset.videoPrice) : null,
                    }));
                    // Reset ad types if no influencers remain
                    if (!this.hasInfluencers) {
                        this.selectedAdTypes = [];
                    }
                },
                async fetchInfluencerFee() {
                    try {
                        const res = await fetch('{{ route("partner.listings.marketer-fee") }}', {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        this.feePerInfluencer = data.fee_per_influencer ?? 0;
                        this.currency = data.currency ?? '';
                    } catch (e) {
                        console.error('Failed to fetch influencer fee', e);
                    }
                },
                init() {
                    this.fetchInfluencerFee();
                },
            };
        }
    </script>
@endpush

@endsection
