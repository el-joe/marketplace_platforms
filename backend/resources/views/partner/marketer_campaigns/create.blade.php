@extends('layouts.partner')

@section('title', __('partner.marketer_campaigns.create_title'))
@section('page-title', __('partner.marketer_campaigns.create_title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_campaigns.create_title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_campaigns.create_subtitle') }}</p>
    </div>

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        {{ __('partner.marketer_campaigns.auto_replace_notice', ['hours' => (int) setting('marketer_invitation_timeout_hours', 12)]) }}
    </div>

    @if($vendorListing->fulfillment_model !== \App\Enums\FulfillmentModel::Fbn)
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
            {{ __('partner.marketer_campaigns.ship_to_warehouse_notice') }}
        </div>
    @endif

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
                    <div class="rounded-lg border border-gray-200 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-start px-4 py-2 text-gray-600 font-medium">{{ __('partner.marketer_campaigns.marketer_col') }}</th>
                                    <th class="text-center px-4 py-2 text-gray-600 font-medium">{{ __('partner.marketer_campaigns.type_col') }}</th>
                                    <th class="text-center px-4 py-2 text-gray-600 font-medium">{{ __('partner.marketer_campaigns.fees_col') }}</th>
                                    <th class="text-center px-4 py-2 text-gray-600 font-medium">
                                        {{ __('partner.marketer_campaigns.commission_col') }}
                                        <span class="block text-[10px] font-normal text-gray-400">{{ __('partner.marketer_campaigns.set_by_platform_label') }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in quote.rows" :key="row.id">
                                    <tr class="border-t border-gray-100">
                                        <td class="px-4 py-2 text-gray-800" x-text="row.name"></td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-xs bg-purple-100 text-purple-700">{{ __('partner.marketer_campaigns.influencer_label') }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-center font-medium">
                                            <span x-show="row.fee > 0" class="text-orange-700" x-text="money(row.fee)"></span>
                                            <span x-show="!(row.fee > 0)" class="text-green-600">{{ __('partner.marketer_campaigns.free_label') }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-center text-gray-800">
                                            <span x-show="row.commission.amount > 0" x-text="commissionLabel(row.commission)"></span>
                                            <span x-show="!(row.commission.amount > 0)" class="text-xs text-gray-400">{{ __('partner.marketer_campaigns.commission_not_set') }}</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 font-semibold text-gray-700 text-start">
                                        {{ __('partner.marketer_campaigns.platform_fees_total') }}
                                        <span class="block text-xs font-normal text-gray-500">
                                            <span x-text="quote.influencer_count"></span> &times; <span x-text="money(quote.fee_per_influencer)"></span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center font-bold" :class="quote.total_fee > 0 ? 'text-orange-700' : 'text-green-600'">
                                        <span x-show="quote.total_fee > 0" x-text="money(quote.total_fee)"></span>
                                        <span x-show="!(quote.total_fee > 0)">{{ __('partner.marketer_campaigns.free_label') }}</span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        {{ __('partner.marketer_campaigns.platform_fee_note') }}
                        <span x-show="quote.fee_payer === 'vendor'">{{ __('partner.marketer_campaigns.fee_payer_vendor_notice') }}</span>
                        <span x-show="quote.fee_payer !== 'vendor'">{{ __('partner.marketer_campaigns.fee_payer_marketer_notice') }}</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">{{ __('partner.marketer_campaigns.commission_estimate_note', ['price' => number_format($vendorListing->price).' '.$vendorListing->currency]) }}</p>
                </div>
            @endif

            {{-- Affiliates: open to all registered affiliates, no individual picking --}}
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="open_to_affiliates" value="1" class="mt-1 rounded border-gray-300 text-blue-600"
                           @checked(old('open_to_affiliates')) @disabled($affiliateCount === 0)>
                    <span class="text-sm text-blue-900">
                        <span class="font-semibold">{{ __('partner.marketer_campaigns.open_to_affiliates_title') }}</span>
                        <span class="block text-xs mt-1">{{ __('partner.marketer_campaigns.open_to_affiliates_desc', ['count' => $affiliateCount]) }}</span>
                        <span class="block text-xs mt-1">{{ __('partner.marketer_campaigns.affiliate_sample_qty_line', ['qty' => $quote['affiliate_sample_qty']]) }}</span>
                        <span class="block text-xs mt-1 text-green-700">{{ __('partner.marketer_campaigns.affiliate_fee_free') }}</span>
                    </span>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('partner.marketer_campaigns.max_commission_budget_label') }}
                    <span class="text-xs text-gray-400">({{ auth()->guard('vendor')->user()->vendor->country->currency_code ?? '' }})</span>
                </label>
                <input type="number" name="max_commission_budget" min="0" value="{{ old('max_commission_budget') }}"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40"
                       placeholder="0">
            </div>

            <div class="rounded-lg border border-purple-200 bg-purple-50 px-4 py-3 text-sm text-purple-800">
                <i class="fas fa-lock me-1"></i>{{ __('partner.marketer_campaigns.commission_set_by_platform_notice') }}
            </div>

            <div class="p-3 bg-purple-50 rounded-lg text-sm text-purple-800">
                <i class="fas fa-box-open mr-1"></i>
                {{ __('partner.marketer_campaigns.expected_samples_label') }} <strong x-text="quote.influencer_count"></strong> {{ __('partner.marketer_campaigns.marketers_selected_suffix') }}
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
                selectedMarketers: [],
                quote: @js($quote + ['rows' => []]),
                get currency() { return this.quote.currency || ''; },
                selectedAdTypes: [],
                get hasInfluencers() {
                    return this.selectedMarketers.some(m => m.type === 'influencer');
                },
                money(v) {
                    return Number(v || 0).toLocaleString() + ' ' + this.currency;
                },
                commissionLabel(c) {
                    // Read-only, as resolved by the server from admin settings.
                    let txt = this.money(c.amount);
                    if (c.source === 'rule' && c.mode !== 'fixed' && c.rate) {
                        txt += ' (' + c.rate + '%)';
                    }
                    return txt;
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
                    this.refreshQuote();
                },
                async refreshQuote() {
                    if (!this.selectedMarketers.length) {
                        this.quote = Object.assign({}, this.quote, { rows: [], influencer_count: 0, total_fee: 0 });
                        return;
                    }
                    try {
                        const params = new URLSearchParams({ listing_id: '{{ $vendorListing->id }}' });
                        this.selectedMarketers.forEach(m => params.append('marketer_ids[]', m.id));
                        const res = await fetch('{{ route("partner.listings.marketer-fee") }}?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (res.ok) {
                            this.quote = await res.json();
                        }
                    } catch (e) {
                        console.error('Failed to fetch campaign quote', e);
                    }
                },
                init() {},
            };
        }
    </script>
@endpush

@endsection
