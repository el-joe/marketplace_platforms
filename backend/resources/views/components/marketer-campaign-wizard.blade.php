@props([
    'vertical' => 'product',
    'subject',
    'influencers',
    'affiliateCount' => 0,
    'quote',
    'action',
    'quoteUrl',
    'cancelUrl',
    'walletUrl',
    'itemInputs' => [],
    'quoteParams' => [],
    'submitLabel' => null,
])

@php
    use App\Support\Money;

    $v = in_array($vertical, ['classified', 'travel'], true) ? $vertical : 'product';
    $ccy = $subject->currency;
    $walletBalance = $quote['wallet']['balance'] ?? 0;
    $walletCurrency = $quote['wallet']['currency'] ?? $ccy;
@endphp

{{-- The shared marketer-campaign wizard: used by the vendor product / classified wizards and the travel agency's trip
     wizard. Everything vertical-specific is wording (marketer_campaigns.vertical.*) or a slot; every figure in the cost
     summary comes from the server quote, so the page does no money math. --}}

{{-- Item summary --}}
<div class="bg-white rounded-2xl border border-gray-200 p-6" data-testid="campaign-item-summary">
    <div class="flex items-center gap-3">
        @if($subject->imageUrl)
            <img src="{{ $subject->imageUrl }}" alt="" class="w-14 h-14 rounded-lg object-cover">
        @endif
        <div class="min-w-0">
            <div class="text-[11px] font-medium uppercase tracking-wide text-purple-600">{{ \App\Enums\CommissionVertical::from($v)->label() }}</div>
            <div class="font-semibold text-gray-900 truncate">{{ $subject->title ?: '—' }}</div>
            <div class="text-xs text-gray-500">{{ $ccy }} {{ Money::format($subject->price) }}</div>
        </div>
    </div>
</div>

<form method="POST" action="{{ $action }}" x-data="campaignCreateForm(@js(['vertical' => $v, 'quote' => $quote, 'quoteUrl' => $quoteUrl, 'quoteParams' => $quoteParams, 'openAffiliates' => (bool) old('open_to_affiliates'), 'budget' => old('max_commission_budget', '')]))">
    @csrf
    @foreach($itemInputs as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    <div class="lg:col-span-2 space-y-6">

        {{-- 1. Who promotes --}}
        <div class="bg-white rounded-2xl border border-purple-200 p-6 space-y-5">
            <div>
                <h2 class="text-base font-semibold text-gray-900"><span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 text-purple-700 text-xs me-2">1</span>{{ __('marketer_campaigns.wizard.step_who') }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('marketer_campaigns.wizard.step_who_help') }}</p>
            </div>

            @if($influencers->isEmpty())
                <p class="text-xs text-amber-600 bg-amber-50 rounded-lg p-3">{{ __('marketer_campaigns.wizard.no_influencers') }}</p>
            @else
                <x-form.select
                    name="marketer_ids"
                    label="{{ __('marketer_campaigns.wizard.influencers_label') }}"
                    :multiple="true"
                    :select2="true"
                    placeholder="{{ __('marketer_campaigns.wizard.influencers_placeholder') }}"
                >
                    @foreach($influencers as $m)
                        <option value="{{ $m['id'] }}"
                                data-type="influencer"
                                data-name="{{ $m['name'] }}"
                                data-story-price="{{ $m['story_price'] }}"
                                data-post-price="{{ $m['post_price'] }}"
                                data-video-price="{{ $m['video_price'] }}"
                                @selected(in_array($m['id'], (array) old('marketer_ids', []), true))>
                            {{ $m['name'] }} — {{ __('marketer_campaigns.wizard.option_fixed', ['amount' => Money::format($m['amount']).' '.$ccy]) }}
                        </option>
                    @endforeach
                </x-form.select>
                <p class="text-xs text-gray-500 -mt-2">{{ __('marketer_campaigns.vertical.'.$v.'.influencers_help') }}</p>

                <div x-show="quote.rows.length > 0" x-cloak class="rounded-lg border border-gray-200 overflow-x-auto" data-testid="influencer-fixed-table">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-start px-4 py-2 text-gray-600 font-medium">{{ __('partner.marketer_campaigns.marketer_col') }}</th>
                                <th class="text-end px-4 py-2 text-gray-600 font-medium">{{ __('marketer_campaigns.wizard.fixed_col') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in quote.rows" :key="row.id">
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-2 text-gray-800" x-text="row.name"></td>
                                    <td class="px-4 py-2 text-end font-medium text-purple-700">
                                        <span x-show="row.fixed > 0" x-text="money(row.fixed)"></span>
                                        <span x-show="!(row.fixed > 0)" class="text-xs font-normal text-amber-600">{{ __('marketer_campaigns.wizard.fixed_not_set') }}</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Affiliates and brokers: open to all registered ones, no individual picking --}}
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="open_to_affiliates" value="1" class="mt-1 rounded border-gray-300 text-blue-600"
                           x-model="openAffiliates" @change="refreshQuote()" @checked(old('open_to_affiliates'))>
                    <span class="text-sm text-blue-900">
                        <span class="font-semibold">{{ __('marketer_campaigns.wizard.open_title') }}</span>
                        <span class="block text-xs mt-1">{{ __('marketer_campaigns.vertical.'.$v.'.open_desc', ['count' => $affiliateCount]) }}</span>
                        <span class="block text-xs mt-1">{{ __('marketer_campaigns.vertical.'.$v.'.open_how') }}</span>
                        @isset($openExtra)
                            {{ $openExtra }}
                        @endisset
                        @if($affiliateCount === 0)
                            <span class="block text-xs mt-1 text-amber-700">{{ __('partner.marketer_campaigns.no_affiliates_available') }}</span>
                        @endif
                    </span>
                </label>
            </div>
        </div>

        {{-- 2. Budget and dates --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <div>
                <h2 class="text-base font-semibold text-gray-900"><span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 text-purple-700 text-xs me-2">2</span>{{ __('marketer_campaigns.wizard.step_budget') }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('marketer_campaigns.wizard.step_budget_help') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="max_commission_budget">
                    {{ __('partner.marketer_campaigns.max_commission_budget_label') }}
                    <span class="text-xs text-gray-400">({{ $ccy }})</span>
                </label>
                <input type="number" id="max_commission_budget" name="max_commission_budget" min="0.01" step="0.01" required
                       x-model.debounce.400ms="budget" @input.debounce.400ms="refreshQuote()"
                       value="{{ old('max_commission_budget') }}"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40"
                       placeholder="{{ __('marketer_campaigns.wizard.budget_placeholder') }}">
                <p class="mt-1 text-xs text-gray-500">{{ __('marketer_campaigns.wizard.budget_help') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="ends_at">
                    {{ __('marketer_campaigns.wizard.ends_at_label') }}
                </label>
                <input type="date" id="ends_at" name="ends_at" min="{{ now()->addDay()->toDateString() }}" value="{{ old('ends_at') }}"
                       class="w-full sm:w-64 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40">
                <p class="mt-1 text-xs text-gray-500">{{ __('marketer_campaigns.wizard.ends_at_help') }}</p>
            </div>

            <div class="rounded-lg border border-purple-200 bg-purple-50 px-4 py-3 text-sm text-purple-800">
                <i class="fas fa-lock me-1"></i>{{ __('marketer_campaigns.wizard.commission_set_by_platform') }}
            </div>
        </div>

        {{-- 3. Brief (and, for products, the sample plan) --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-base font-semibold text-gray-900"><span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-100 text-purple-700 text-xs me-2">3</span>{{ __('marketer_campaigns.wizard.step_brief') }}</h2>

            @isset($samples)
                {{ $samples }}
            @endisset

            {{-- Ad type selection — shown only when influencers are selected --}}
            <div x-show="hasInfluencers" x-cloak>
                <div class="{{ isset($samples) ? 'border-t border-gray-200 pt-4' : '' }}">
                    <h5 class="text-sm font-semibold text-gray-700 mb-3">
                        <i class="fas fa-photo-film text-purple-500 mr-1"></i>
                        {{ __('partner.marketer_campaigns.ad_types_label') }}
                    </h5>
                    <div class="space-y-3">
                        <template x-for="type in availableAdTypes" :key="type.key">
                            <label class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 cursor-pointer hover:bg-purple-50 transition-colors"
                                   :class="selectedAdTypes.includes(type.key) ? 'border-purple-400 bg-purple-50' : ''">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" name="selected_ad_types[]" :value="type.key"
                                           @change="toggleAdType(type.key)" :checked="selectedAdTypes.includes(type.key)"
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
                          placeholder="{{ __('partner.marketer_campaigns.vendor_ad_notes_placeholder') }}">{{ old('vendor_ad_notes') }}</textarea>
            </div>

            <p x-show="!hasInfluencers" class="text-xs text-gray-500">{{ __('marketer_campaigns.wizard.brief_no_influencers') }}</p>
        </div>

    </div>{{-- /left column --}}

    {{-- Live cost summary: every figure comes from the server quote --}}
    <aside class="lg:sticky lg:top-6 space-y-4" data-testid="campaign-cost-summary">
        <div class="bg-white rounded-2xl border border-orange-200 overflow-hidden">
            <div class="px-5 py-4 bg-orange-50/60 border-b border-orange-100 flex items-start justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ __('marketer_campaigns.summary.title') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('marketer_campaigns.summary.subtitle') }}</p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[11px] bg-white border border-orange-200 text-gray-700 whitespace-nowrap">
                    {{ __('marketing_fees.campaign.fulfillment_type') }}: <strong x-text="quote.fulfillment_type_label"></strong>
                </span>
            </div>

            <dl class="px-5 py-3 divide-y divide-gray-100 text-sm">
                <div class="py-3">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="font-medium text-gray-800">{{ __('marketer_campaigns.summary.campaign_fee') }}</dt>
                        <dd class="font-semibold text-gray-900" data-testid="quote-marketing-total" x-text="money(quote.marketing.total)"></dd>
                    </div>
                    <div class="mt-1 ps-3 space-y-0.5 text-xs text-gray-500">
                        <div class="flex justify-between"><span>{{ __('marketer_campaigns.summary.fixed_fee') }}</span><span x-text="money(quote.marketing.fixed_fee)"></span></div>
                        <div class="flex justify-between"><span>{{ __('marketer_campaigns.summary.variable_fee') }}</span><span x-text="money(quote.marketing.variable_fee)"></span></div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-gray-400">{{ __('marketer_campaigns.summary.campaign_fee_help') }}</p>
                </div>

                <div class="py-3">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="font-medium text-gray-800">
                            {{ __('marketer_campaigns.summary.influencers_fixed') }}
                            <span class="text-xs font-normal text-gray-400">(<span x-text="quote.influencer_count"></span>)</span>
                        </dt>
                        <dd class="font-semibold text-purple-700" data-testid="quote-influencer-fixed" x-text="money(quote.influencer_fixed_total)"></dd>
                    </div>
                    <p class="mt-1.5 text-[11px] text-gray-400">{{ __('marketer_campaigns.summary.influencers_fixed_help') }}</p>
                </div>

                <div class="py-3">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="font-medium text-gray-800">{{ __('marketer_campaigns.summary.remaining') }}</dt>
                        <dd class="font-semibold" :class="quote.fits_budget ? 'text-blue-700' : 'text-red-600'" data-testid="quote-remaining" x-text="money(quote.remaining_budget)"></dd>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden" x-show="Number(quote.budget) > 0">
                        <div class="h-full bg-purple-500" :style="'width:' + fixedPct + '%'"></div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-gray-400">{{ __('marketer_campaigns.summary.remaining_help_'.$v) }}</p>
                    <p x-show="!quote.fits_budget" x-cloak class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" data-testid="budget-too-small">
                        {{ __('marketer_campaigns.summary.exceeds_budget') }}
                    </p>
                </div>

                <div class="py-3">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="font-medium text-gray-800">{{ __('marketer_campaigns.summary.budget') }}</dt>
                        <dd class="font-semibold text-gray-900" data-testid="quote-subtotal" x-text="money(quote.campaign_subtotal)"></dd>
                    </div>
                </div>

                <div class="py-3 bg-orange-50/40 -mx-5 px-5">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="font-bold text-gray-900">{{ __('marketer_campaigns.summary.grand_total') }}</dt>
                        <dd class="text-lg font-bold text-orange-700" data-testid="quote-grand-total" x-text="money(quote.campaign_grand_total)"></dd>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500">{{ __('marketer_campaigns.summary.grand_total_help') }}</p>
                </div>
            </dl>

            <div class="px-5 pb-4 space-y-2">
                <div class="rounded-xl border border-gray-200 bg-white p-3 text-sm flex items-center justify-between" data-testid="wallet-balance">
                    <span class="text-gray-600">{{ __('partner.marketer_campaigns.wallet_balance') }}</span>
                    <a href="{{ $walletUrl }}" class="font-semibold {{ $walletBalance <= 0 ? 'text-red-600' : 'text-gray-800' }}">{{ Money::format($walletBalance) }} {{ $walletCurrency }}</a>
                </div>
                <p x-show="quote.wallet && quote.wallet.sufficient && quote.marketing.total > 0" class="text-xs text-green-700">{{ __('marketer_campaigns.summary.wallet_enough') }}</p>
                <p x-show="quote.wallet && !quote.wallet.sufficient" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" data-testid="wallet-shortfall-warning">
                    {!! str_replace(':shortfall', '<strong x-text="money(quote.wallet.shortfall)"></strong>', e(__('marketer_campaigns.summary.wallet_short_'.$v, ['shortfall' => ':shortfall']))) !!}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 space-y-1.5">
            <div class="font-semibold text-gray-800 text-sm">{{ __('marketer_campaigns.summary.how_title') }}</div>
            <p>{{ __('marketer_campaigns.summary.how_influencer') }}</p>
            <p>{{ __('marketer_campaigns.vertical.'.$v.'.how_affiliate') }}</p>
            <p>{{ __('marketer_campaigns.vertical.'.$v.'.approval_hint') }}</p>
            <p>{{ __('marketer_campaigns.summary.how_done') }}</p>
        </div>

        <div class="flex gap-3">
            <button type="submit" x-bind:disabled="stockBlocked || !quote.fits_budget" class="flex-1 disabled:opacity-50 disabled:cursor-not-allowed bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold px-6 py-3 rounded-xl transition-colors text-sm">
                {{ $submitLabel ?? __('partner.marketer_campaigns.create_btn') }}
            </button>
            <a href="{{ $cancelUrl }}" class="border border-gray-200 text-gray-700 font-semibold px-6 py-3 rounded-xl text-sm hover:bg-gray-50">
                {{ __('partner.marketer_campaigns.cancel_btn') }}
            </a>
        </div>
    </aside>
    </div>{{-- /grid --}}
</form>

@once
@push('scripts')
    @vite(['resources/js/components/select2.js'])
    <script>
        function campaignCreateForm(cfg) {
            return {
                vertical: cfg.vertical,
                selectedMarketers: [],
                openAffiliates: cfg.openAffiliates,
                budget: cfg.budget,
                quote: cfg.quote,
                selectedAdTypes: [],
                get isProduct() { return this.vertical === 'product'; },
                get fixedPct() {
                    const b = Number(this.quote.budget || 0);
                    return b > 0 ? Math.min(100, Math.round(Number(this.quote.influencer_fixed_total || 0) / b * 100)) : 0;
                },
                get sampleTotal() {
                    const q = this.quote;
                    return Number(q.platform_sample_qty || 0)
                        + Number(q.influencer_count || 0) * Number(q.influencer_sample_qty || 0)
                        + (this.openAffiliates ? Number(q.affiliate_sample_qty || 0) : 0);
                },
                get stockLeft() {
                    return Number(this.quote.stock_available || 0)
                        - Number(this.quote.stock_reserved_by_other_campaigns || 0)
                        - this.sampleTotal;
                },
                // Stock only gates product campaigns; classified listings and trips carry no samples.
                get stockBlocked() {
                    return this.isProduct && this.stockLeft < Number(this.quote.stock_min || 0);
                },
                get currency() { return this.quote.currency || ''; },
                get hasInfluencers() {
                    return this.selectedMarketers.some(m => m.type === 'influencer');
                },
                money(v) {
                    // Whole amounts stay "2", fractional ones show two decimals ("0.10").
                    const n = Number(v || 0);
                    const hasFraction = Math.abs(n - Math.round(n)) > 0.000001;
                    return n.toLocaleString(undefined, {
                        minimumFractionDigits: hasFraction ? 2 : 0,
                        maximumFractionDigits: 2,
                    }) + ' ' + this.currency;
                },
                get availableAdTypes() {
                    const influencers = this.selectedMarketers.filter(m => m.type === 'influencer');
                    const labels = {
                        story: @js(__('partner.marketer_campaigns.ad_type_story')),
                        post: @js(__('partner.marketer_campaigns.ad_type_post')),
                        video: @js(__('partner.marketer_campaigns.ad_type_video')),
                    };
                    return ['story', 'post', 'video'].map(key => {
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
                        storyPrice: opt.dataset.storyPrice ? parseFloat(opt.dataset.storyPrice) : null,
                        postPrice: opt.dataset.postPrice ? parseFloat(opt.dataset.postPrice) : null,
                        videoPrice: opt.dataset.videoPrice ? parseFloat(opt.dataset.videoPrice) : null,
                    }));
                    if (!this.hasInfluencers) {
                        this.selectedAdTypes = [];
                    }
                    this.refreshQuote();
                },
                async refreshQuote() {
                    try {
                        const params = new URLSearchParams({
                            ...cfg.quoteParams,
                            budget: this.budget || 0,
                            open_to_affiliates: this.openAffiliates ? 1 : 0,
                        });
                        this.selectedMarketers.forEach(m => params.append('marketer_ids[]', m.id));
                        const res = await fetch(cfg.quoteUrl + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (res.ok) {
                            this.quote = await res.json();
                        }
                    } catch (e) {
                        console.error('Failed to fetch campaign quote', e);
                    }
                },
                init() {
                    // Select2 fires jQuery-only change events, which Alpine's native x-on:change never sees.
                    this.$nextTick(() => {
                        const el = this.$root.querySelector('select[name="marketer_ids[]"]');
                        if (el && window.jQuery) {
                            window.jQuery(el).on('change', (e) => this.updateSelectedMarketers(e));
                            // Restore the cost summary after a validation error re-render.
                            if (el.selectedOptions.length || this.budget) {
                                this.updateSelectedMarketers({ target: el });
                            }
                        }
                    });
                },
            };
        }
    </script>
@endpush
@endonce
