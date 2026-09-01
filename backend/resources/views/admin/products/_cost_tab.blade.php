{{--
Cost Reference Tab — admin/products/_cost_tab.blade.php
Include in admin.products._form with:
@include('admin.products._cost_tab', ['product' => $product])

Only rendered when the logged-in admin has 'products.cost_data.view'.
--}}

@php
    $canView = auth('admin')->user()?->hasPermissionTo('products.cost_data.view');
    $canEdit = auth('admin')->user()?->hasPermissionTo('products.cost_data.edit');
    $costCurrency = $product->variants()->first()?->vendorListings()->value('currency') ?? 'EGP';
@endphp

@if($canView)
    <div x-data="costReferencePanel('{{ $product->id }}', '{{ $costCurrency }}', @js(route('admin.products.cost.show', $product->id)),
                                                          @js(route('admin.products.cost.save', $product->id)),
                                                          @js(route('admin.products.cost.calculate', $product->id)),
                                                          @js(route('admin.products.cost.check-competitors', $product->id)))"
        x-init="loadRef()" x-show="activeTab === 'cost'"
        class="bg-white rounded-b-xl border border-t-0 border-gray-200 shadow-sm">

        {{-- ─── Loading skeleton ────────────────────────────────────────────── --}}
        <div x-show="loading" class="p-8 text-center text-gray-400 text-sm">
            <svg class="animate-spin h-5 w-5 mx-auto mb-2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ __('admin.products.loading_cost_data') }}
        </div>

        {{-- ─── Below-cost warning banner ──────────────────────────────────── --}}
        <div x-show="!loading && belowCostWarning" x-cloak
            class="flex items-start gap-3 bg-red-50 border-b border-red-200 px-5 py-3">
            <span class="text-xl leading-none mt-0.5">⚠️</span>
            <div>
                <p class="text-sm font-semibold text-red-800">{{ __('admin.products.vendor_below_cost_title') }}</p>
                <p class="text-xs text-red-600 mt-0.5">
                    <span x-html="belowCostBodyHtml"></span>
                    <strong>{{ __('admin.products.admin_only_alert') }}</strong>
                </p>
            </div>
        </div>

        <div x-show="!loading" class="divide-y divide-gray-100">

            {{-- ─── Section 1: Manufacturer ────────────────────────────────── --}}
            <div class="px-5 py-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                        <x-heroicon name="building-office" class="w-4 h-4 text-gray-400" />
                        {{ __('admin.products.manufacturer_details') }}
                    </h4>
                    <span
                        class="text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium uppercase tracking-wide">
                        🔒 {{ __('admin.products.confidential') }}
                    </span>
                </div>

                @if($canEdit)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="label-sm">{{ __('admin.products.manufacturer_name') }}</label>
                            <input type="text" x-model="form.manufacturer_name" class="form-input w-full text-sm"
                                placeholder="{{ __('admin.products.manufacturer_name_placeholder') }}">
                        </div>
                        <div>
                            <label class="label-sm">{{ __('admin.products.manufacturer_url') }}</label>
                            <input type="url" x-model="form.manufacturer_url" class="form-input w-full text-sm" dir="ltr"
                                placeholder="https://manufacturer.com">
                        </div>
                        <div>
                            <label class="label-sm">{{ __('admin.products.manufacturer_sku') }}</label>
                            <input type="text" x-model="form.manufacturer_sku" class="form-input w-full text-sm font-mono"
                                placeholder="{{ __('admin.products.manufacturer_sku_placeholder') }}">
                        </div>
                    </div>
                @else
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-400 text-xs">{{ __('admin.products.manufacturer_label') }}</dt>
                            <dd class="font-medium text-gray-800 mt-0.5" x-text="ref?.manufacturer_name || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-gray-400 text-xs">{{ __('admin.products.url_label') }}</dt>
                            <dd class="mt-0.5">
                                <a x-show="ref?.manufacturer_url" :href="ref?.manufacturer_url" target="_blank"
                                    class="text-blue-600 hover:underline text-xs truncate block"
                                    x-text="ref?.manufacturer_url"></a>
                                <span x-show="!ref?.manufacturer_url" class="text-gray-400">—</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-400 text-xs">{{ __('admin.products.sku_label') }}</dt>
                            <dd class="font-mono text-gray-700 mt-0.5" x-text="ref?.manufacturer_sku || '—'"></dd>
                        </div>
                    </dl>
                @endif
            </div>

            {{-- ─── Section 2: Cost Breakdown ───────────────────────────────── --}}
            <div class="px-5 py-4 space-y-4">
                <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                    <x-heroicon name="banknotes" class="w-4 h-4 text-gray-400" />
                    {{ __('admin.products.cost_breakdown') }}
                </h4>

                @if($canEdit)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="label-sm">{{ __('admin.products.factory_cost_label') }}</label>
                            <input type="number" x-model.number="form.manufacturer_cost" min="0"
                                class="form-input w-full text-sm font-mono" :placeholder="'{{ __('admin.products.factory_cost_placeholder', ['currency' => '']) }}' + currencyCode"
                                @input="syncLandedCost()">
                            <p class="text-xs text-gray-400 mt-0.5" x-text="formatAmount(form.manufacturer_cost, currencyCode)"></p>
                        </div>
                        <div>
                            <label class="label-sm">{{ __('admin.products.shipping_cost_label') }}</label>
                            <input type="number" x-model.number="form.shipping_cost" min="0"
                                class="form-input w-full text-sm font-mono" :placeholder="'{{ __('admin.products.shipping_cost_placeholder', ['currency' => '']) }}' + currencyCode"
                                @input="syncLandedCost()">
                            <p class="text-xs text-gray-400 mt-0.5" x-text="formatAmount(form.shipping_cost, currencyCode)"></p>
                        </div>
                        <div>
                            <label class="label-sm">
                                {{ __('admin.products.landed_cost_label') }}
                                <span class="text-gray-400 font-normal">{{ __('admin.products.landed_cost_auto_override') }}</span>
                            </label>
                            <input type="number" x-model.number="form.landed_cost" min="0"
                                class="form-input w-full text-sm font-mono" placeholder="{{ __('admin.products.landed_cost_placeholder') }}">
                            <p class="text-xs text-gray-400 mt-0.5" x-text="formatAmount(form.landed_cost, currencyCode)"></p>
                        </div>
                    </div>

                    {{-- Cost summary row --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-gray-50 rounded-xl p-3 text-center text-sm">
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">{{ __('admin.products.factory_short') }}</p>
                            <p class="font-semibold text-gray-800" x-text="formatAmount(form.manufacturer_cost, currencyCode) || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">{{ __('admin.products.plus_shipping') }}</p>
                            <p class="font-semibold text-gray-800" x-text="formatAmount(form.shipping_cost, currencyCode) || '—'"></p>
                        </div>
                        <div class="border-l border-gray-200">
                            <p class="text-xs text-gray-400 mb-0.5">{{ __('admin.products.equals_landed_cost') }}</p>
                            <p class="font-bold text-indigo-700" x-text="formatAmount(form.landed_cost, currencyCode) || '—'"></p>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-gray-50 rounded-xl p-3 text-center text-sm">
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">{{ __('admin.products.factory_cost_short') }}</p>
                            <p class="font-semibold text-gray-800" x-text="ref?.manufacturer_cost_formatted || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">{{ __('admin.products.plus_shipping') }}</p>
                            <p class="font-semibold text-gray-800" x-text="ref?.shipping_cost_formatted || '—'"></p>
                        </div>
                        <div class="border-l border-gray-200">
                            <p class="text-xs text-gray-400 mb-0.5">{{ __('admin.products.equals_landed') }}</p>
                            <p class="font-bold text-indigo-700" x-text="ref?.landed_cost_formatted || '—'"></p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ─── Section 3: Margin Calculator ────────────────────────────── --}}
            <div class="px-5 py-4 space-y-4">
                <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                    <x-heroicon name="calculator" class="w-4 h-4 text-gray-400" />
                    {{ __('admin.products.margin_calculator') }}
                </h4>

                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="label-sm">{{ __('admin.products.selling_price_label') }}</label>
                        <input type="number" x-model.number="calcPrice" min="1" class="form-input text-sm font-mono w-44"
                            :placeholder="'{{ __('admin.products.selling_price_placeholder', ['currency' => '']) }}' + currencyCode">
                        <p class="text-xs text-gray-400 mt-0.5" x-text="centsToCurrency(calcPrice, currencyCode)"></p>
                    </div>
                    <div>
                        <label class="label-sm">{{ __('admin.products.landed_cost_override_label') }}</label>
                        <input type="number" x-model.number="calcLanded" min="0" class="form-input text-sm font-mono w-44"
                            :placeholder="'{{ __('admin.products.landed_cost_override_placeholder', ['value' => '']) }}' + (form.landed_cost || 'none')">
                        <p class="text-xs text-gray-400 mt-0.5" x-text="centsToCurrency(calcLanded, currencyCode)"></p>
                    </div>
                    <button type="button" @click="runCalculator()" class="btn btn-secondary btn-sm mb-0.5">
                        {{ __('admin.products.calculate') }}
                    </button>
                </div>

                {{-- Result --}}
                <div x-show="calcResult" x-cloak>
                    <div :class="calcResult?.below_cost ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'"
                        class="border rounded-xl p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center text-sm">
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('admin.products.selling_price_label') }}</p>
                            <p class="font-bold text-gray-900" x-text="calcResult?.selling_formatted"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('admin.products.landed_cost_label') }}</p>
                            <p class="font-bold text-gray-900" x-text="calcResult?.landed_formatted"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('admin.products.gross_profit') }}</p>
                            <p class="font-bold" :class="calcResult?.profit >= 0 ? 'text-green-700' : 'text-red-700'"
                                x-text="calcResult?.profit_formatted"></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">{{ __('admin.products.margin_percent') }}</p>
                            <p class="text-xl font-extrabold"
                                :class="calcResult?.below_cost ? 'text-red-700' : (calcResult?.margin_pct >= 20 ? 'text-green-700' : 'text-yellow-600')"
                                x-text="(calcResult?.margin_pct ?? 0).toFixed(1) + '%'"></p>
                        </div>
                    </div>
                    <p x-show="calcResult?.below_cost"
                        class="text-xs font-semibold text-red-700 mt-2 flex items-center gap-1">
                        ⚠️ {{ __('admin.products.below_cost_calc_warning') }}
                    </p>
                </div>
            </div>

            {{-- ─── Section 4: Competitor Tracking ─────────────────────────── --}}
            <div class="px-5 py-4 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                        <x-heroicon name="magnifying-glass-circle" class="w-4 h-4 text-gray-400" />
                        {{ __('admin.products.competitor_price_tracking') }}
                    </h4>
                    @if($canEdit)
                        <div class="flex gap-2 flex-wrap">
                            <button type="button" @click="addCompetitor()" class="btn btn-ghost btn-xs">{{ __('admin.products.add_short') }}</button>
                            <button type="button" @click="checkCompetitors()" :disabled="checkingPrices"
                                class="btn btn-secondary btn-xs flex items-center gap-1">
                                <span x-show="checkingPrices"
                                    class="w-3 h-3 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></span>
                                🔍 {{ __('admin.products.check_prices') }}
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Competitor rows --}}
                <div x-show="form.competitor_links.length === 0" class="text-sm text-gray-400 italic py-2">
                    {{ __('admin.products.no_competitor_links') }}
                </div>

                <div class="space-y-2">
                    <template x-for="(comp, idx) in form.competitor_links" :key="idx">
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 bg-gray-50 rounded-lg p-2.5">
                            @if($canEdit)
                                <input type="text" x-model="comp.name" class="form-input text-xs w-full sm:w-28 sm:flex-shrink-0"
                                    placeholder="{{ __('admin.products.competitor_name_placeholder') }}">
                                <input type="url" x-model="comp.url" class="form-input text-xs w-full sm:flex-1 font-mono sm:min-w-0" dir="ltr"
                                    placeholder="{{ __('admin.products.competitor_url_placeholder') }}">
                                <input type="number" x-model.number="comp.price"
                                    class="form-input text-xs w-full sm:w-28 font-mono sm:flex-shrink-0" placeholder="{{ __('admin.products.competitor_price_placeholder') }}">
                            @else
                                <span class="text-xs font-medium text-gray-700 w-28 flex-shrink-0 truncate"
                                    x-text="comp.name || '—'"></span>
                                <a :href="comp.url" target="_blank"
                                    class="text-xs text-blue-600 hover:underline flex-1 truncate font-mono"
                                    x-text="comp.url"></a>
                                <span class="text-xs font-semibold text-gray-800 w-28 flex-shrink-0 text-end"
                                    x-text="comp.price ? centsToCurrency(comp.price, currencyCode) : '—'"></span>
                            @endif
                            <div class="text-[10px] text-gray-400 flex-shrink-0 w-28 text-end leading-tight">
                                <span x-show="comp.last_checked">
                                    {{ __('admin.products.checked_label') }}<br>
                                    <span x-text="formatDate(comp.last_checked)"></span>
                                </span>
                                <span x-show="!comp.last_checked">{{ __('admin.products.not_checked') }}</span>
                            </div>
                            @if($canEdit)
                                <button type="button" @click="removeCompetitor(idx)"
                                    class="text-red-400 hover:text-red-600 p-0.5 flex-shrink-0 ml-1">
                                    <x-heroicon name="x-mark" class="w-3.5 h-3.5" />
                                </button>
                            @endif
                        </div>
                    </template>
                </div>

                <p x-show="ref?.competitor_last_checked" x-cloak class="text-xs text-gray-400">
                    {{ __('admin.products.last_batch_check') }} <span x-text="formatDate(ref?.competitor_last_checked)"></span>
                </p>
            </div>

            {{-- ─── Section 5: Notes ────────────────────────────────────────── --}}
            <div class="px-5 py-4 space-y-2">
                <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                    <x-heroicon name="clipboard-document-list" class="w-4 h-4 text-gray-400" />
                    {{ __('admin.products.internal_notes') }}
                </h4>
                @if($canEdit)
                    <textarea x-model="form.notes" rows="3" class="form-input w-full text-sm"
                        placeholder="{{ __('admin.products.internal_notes_placeholder') }}"></textarea>
                @else
                    <p class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3 whitespace-pre-wrap"
                        x-text="ref?.notes || noNotesText"></p>
                @endif
            </div>

            {{-- ─── Footer: Save + Audit Trail ─────────────────────────────── --}}
            <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs text-gray-400 leading-snug">
                    <span x-show="ref?.created_by">{{ __('admin.products.created_by_label') }} <span class="font-medium"
                            x-text="ref?.created_by"></span></span>
                    <span x-show="ref?.updated_by"> · {{ __('admin.products.last_edited_by') }} <span class="font-medium"
                            x-text="ref?.updated_by"></span>
                        (<span x-text="formatDate(ref?.updated_at)"></span>)
                    </span>
                    <span x-show="!ref">{{ __('admin.products.not_yet_saved') }}</span>
                </p>
                @if($canEdit)
                    <button type="button" @click="saveRef()" :disabled="saving"
                        class="btn btn-primary btn-sm flex items-center gap-2 min-w-28 justify-center">
                        <span x-show="saving"
                            class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span x-show="!saving">{{ __('admin.products.save_cost_data') }}</span>
                        <span x-show="saving">{{ __('admin.products.saving_ellipsis') }}</span>
                    </button>
                @endif
            </div>

        </div>{{-- /!loading --}}
    </div>{{-- /x-data costReferencePanel --}}

    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            vendorBelowCostBody: @json(__('admin.products.vendor_below_cost_body', ['price' => '__PRICE__'])),
            costRefLoadError: @json(__('admin.products.cost_ref_load_error')),
            costSaveFailed: @json(__('admin.products.cost_save_failed')),
            networkError: @json(__('admin.products.network_error')),
            enterSellingPrice: @json(__('admin.products.enter_selling_price')),
            enterLandedCostFirst: @json(__('admin.products.enter_landed_cost_first')),
            calculationError: @json(__('admin.products.calculation_error')),
            competitorCheckFailed: @json(__('admin.products.competitor_check_failed')),
            networkErrorChecking: @json(__('admin.products.network_error_checking')),
            noNotes: @json(__('admin.products.no_notes')),
        });

        function costReferencePanel(productId, currencyCode, showUrl, saveUrl, calcUrl, checkUrl) {
            return {
                productId, currencyCode, showUrl, saveUrl, calcUrl, checkUrl,
                loading: true,
                saving: false,
                checkingPrices: false,
                ref: null,
                belowCostWarning: false,
                lowestPriceFormatted: '',
                noNotesText: window.TRANSLATIONS.noNotes,
                form: {
                    manufacturer_name: '',
                    manufacturer_url: '',
                    manufacturer_sku: '',
                    manufacturer_cost: null,
                    shipping_cost: null,
                    landed_cost: null,
                    competitor_links: [],
                    notes: '',
                },
                calcPrice: null,
                calcLanded: null,
                calcResult: null,

                get belowCostBodyHtml() {
                    return window.TRANSLATIONS.vendorBelowCostBody.replace('__PRICE__', `<span>${this.lowestPriceFormatted}</span>`);
                },

                async loadRef() {
                    this.loading = true;
                    try {
                        const res = await fetch(this.showUrl, { headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                        const data = await res.json();
                        this.belowCostWarning = data.below_cost_warning;
                        this.lowestPriceFormatted = data.lowest_price
                            ? this.formatAmount(data.lowest_price, this.currencyCode) : '';
                        if (data.ref) {
                            this.ref = data.ref;
                            this.form = {
                                manufacturer_name: data.ref.manufacturer_name ?? '',
                                manufacturer_url: data.ref.manufacturer_url ?? '',
                                manufacturer_sku: data.ref.manufacturer_sku ?? '',
                                manufacturer_cost: data.ref.manufacturer_cost ?? null,
                                shipping_cost: data.ref.shipping_cost ?? null,
                                landed_cost: data.ref.landed_cost ?? null,
                                competitor_links: data.ref.competitor_links ?? [],
                                notes: data.ref.notes ?? '',
                            };
                        }
                    } catch (e) {
                        console.error(window.TRANSLATIONS.costRefLoadError, e);
                    } finally {
                        this.loading = false;
                    }
                },

                syncLandedCost() {
                    const mfr = parseInt(this.form.manufacturer_cost) || 0;
                    const ship = parseInt(this.form.shipping_cost) || 0;
                    if (mfr + ship > 0) {
                        this.form.landed_cost = mfr + ship;
                    }
                },

                async saveRef() {
                    this.saving = true;
                    try {
                        const res = await fetch(this.saveUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.form),
                        });
                        const data = await res.json();
                        if (data.success) { window.Toast.success(data.message); this.ref = data.ref; }
                        else { window.Toast.error(data.message ?? window.TRANSLATIONS.costSaveFailed); }
                    } catch (e) {
                        window.Toast.error(window.TRANSLATIONS.networkError);
                    } finally {
                        this.saving = false;
                    }
                },

                async runCalculator() {
                    const landed = this.calcLanded || this.form.landed_cost;
                    if (!this.calcPrice) { window.Toast.error(window.TRANSLATIONS.enterSellingPrice); return; }
                    if (!landed) { window.Toast.error(window.TRANSLATIONS.enterLandedCostFirst); return; }
                    try {
                        const res = await fetch(this.calcUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                selling_price: this.calcPrice,
                                landed_cost: landed,
                            }),
                        });
                        this.calcResult = await res.json();
                    } catch (e) {
                        window.Toast.error(window.TRANSLATIONS.calculationError);
                    }
                },

                async checkCompetitors() {
                    this.checkingPrices = true;
                    try {
                        // Save current links first so the server has them
                        await this.saveRef();
                        const res = await fetch(this.checkUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                            body: '{}',
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.form.competitor_links = data.links;
                            if (this.ref) this.ref.competitor_links = data.links;
                            window.Toast.success(data.message);
                        } else {
                            window.Toast.error(data.message ?? window.TRANSLATIONS.competitorCheckFailed);
                        }
                    } catch (e) {
                        window.Toast.error(window.TRANSLATIONS.networkErrorChecking);
                    } finally {
                        this.checkingPrices = false;
                    }
                },

                addCompetitor() {
                    this.form.competitor_links.push({ name: '', url: '', price: null, last_checked: null });
                },

                removeCompetitor(idx) {
                    this.form.competitor_links.splice(idx, 1);
                },

                centsToCurrency(amount, currencyCode) {
                    if (amount === null || amount === undefined || amount === '' || isNaN(amount)) return '';
                    return parseInt(amount).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (currencyCode || '');
                },

                formatAmount(amount, currencyCode) {
                    if (amount === null || amount === undefined || amount === '' || isNaN(amount)) return '';
                    return parseInt(amount).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (currencyCode || '');
                },

                formatDate(iso) {
                    if (!iso) return '';
                    try { return new Date(iso).toLocaleDateString('en-EG', { day: 'numeric', month: 'short', year: 'numeric' }); }
                    catch { return iso; }
                },
            };
        }
    </script>
@endif
