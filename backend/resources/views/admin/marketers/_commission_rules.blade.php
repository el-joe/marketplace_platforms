{{--
    Per-category commission rules of a marketer, one card per marketer type the marketer holds.

    influencer -> fixed amount per campaign only (percentage disabled)
    affiliate  -> percentage only (fixed disabled)
    broker     -> fixed, percentage or both

    Each rule may override the platform commission fee (fee_percentage / fee_fixed); empty = default from Marketer Settings.

    Expects: $marketer, $commissionRules, $commissionCategories, $commissionCurrency, $commissionTypes, $commissionDefaults
--}}
@php
    $cardTypes = count($commissionTypes) ? $commissionTypes : [null];
    $oldType = old('marketer_type');
    $categoryOptions = $commissionCategories->map(fn ($c) => $c->map(fn ($x) => ['id' => $x->id, 'name' => $x->name_ar ?: $x->name_en])->values());
    $scopeLabels = collect(\App\Models\MarketerCommissionRule::SCOPES)
        ->mapWithKeys(fn ($sc) => [$sc => \App\Models\MarketerCommissionRule::verticalFor($sc)->label()]);
    $previewStrings = [
        'default_influencer' => __('marketer_commissions.preview_default_influencer'),
        'default_none' => __('marketer_commissions.preview_default_none'),
        'default_fee' => __('marketer_commissions.preview_default_fee'),
        'fee_none' => __('marketer_commissions.preview_fee_none'),
        'example_fixed' => __('marketer_commissions.preview_example_fixed'),
        'example_sale' => __('marketer_commissions.preview_example_sale'),
    ];
@endphp

@foreach ($cardTypes as $type)
    @php
        $typeKey = $type?->value;
        $isFirstCard = $loop->first;
        $allowFixed = $type ? $type->allowsFixed() : true;
        $allowPct = $type ? $type->allowsPercentage() : true;
        $rulesForCard = $commissionRules->filter(fn ($r) => $r->marketer_type === $typeKey || ($isFirstCard && $r->marketer_type === null));
        $showErrors = $oldType ? $oldType === $typeKey : $isFirstCard;
        $cardId = 'commission-rules-'.($typeKey ?? 'generic');
        $textKey = $typeKey ?? 'generic';
    @endphp

    <div class="bg-white rounded-xl border shadow-sm overflow-hidden" id="{{ $cardId }}" data-commission-card="{{ $textKey }}">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 font-bold text-emerald-600">{{ $allowFixed && ! $allowPct ? '#' : '%' }}</span>
            <div class="min-w-0">
                <h3 class="font-bold text-gray-800">{{ __('marketer_commissions.heading_'.$textKey) }}</h3>
                <p class="text-xs text-gray-500 mt-1" @if($isFirstCard) data-commission-precedence-note @endif>{{ __('marketer_commissions.subtitle_'.$textKey) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ __('marketing_fees.reports.commission_precedence_note') }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="px-6 py-3 text-start">{{ __('marketer_commissions.col_scope_category') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('marketer_commissions.col_commission') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('marketer_commissions.col_fee') }}</th>
                        <th class="px-6 py-3 text-center"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rulesForCard as $cc)
                        @php
                            $ruleDefaults = $commissionDefaults[$cc->scope] ?? ['influencer' => 0, 'fee_percentage' => 0, 'fee_fixed' => 0, 'categories' => []];
                            $isInfluencerRow = $cc->marketer_type === 'influencer' || ($cc->marketer_type === null && $typeKey === 'influencer');
                            $commissionParts = collect([
                                (float) $cc->commission_rate > 0 ? number_format($cc->commission_rate, 2).'%' : null,
                                (float) $cc->commission_flat_amount > 0
                                    ? \App\Support\Money::format($cc->commission_flat_amount).' '.$commissionCurrency.($isInfluencerRow ? ' '.__('marketer_commissions.per_campaign') : '')
                                    : null,
                            ])->filter();
                            $hasFeeOverride = $cc->fee_percentage !== null || $cc->fee_fixed !== null;
                            $legacyMismatch = $cc->marketer_type === null && $type
                                && ((! $allowPct && (float) $cc->commission_rate > 0) || (! $allowFixed && (float) $cc->commission_flat_amount > 0));
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition-colors" data-commission-rule="{{ $cc->id }}">
                            <td class="px-6 py-3 font-medium">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">{{ \App\Models\MarketerCommissionRule::verticalFor($cc->scope)->label() }}</span>
                                <span class="ms-1">{{ $cc->category ? ($cc->category->name_ar ?: $cc->category->name_en) : __('admin.marketer_commission_default_all') }}</span>
                                @if (! $cc->category && $cc->excluded_category_ids)
                                    <div class="text-xs text-red-500 font-normal">{{ __('admin.marketer_commission_except') }}: {{ ($commissionCategories[$cc->scope] ?? collect())->whereIn('id', $cc->excluded_category_ids)->map(fn ($x) => $x->name_ar ?: $x->name_en)->implode('، ') }}</div>
                                @endif
                                @if ($cc->marketer_type === null)
                                    <div class="text-[11px] font-normal text-amber-600">{{ __('marketer_commissions.legacy_rule') }}@if($legacyMismatch) · {{ __('marketer_commissions.legacy_rule_mismatch') }}@endif</div>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">{{ $commissionParts->implode(' + ') ?: '0' }}</span>
                                @if ((float) $cc->commission_min_amount > 0)
                                    <div class="text-[11px] text-gray-400 mt-0.5">{{ __('marketer_commissions.min_per_unit', ['amount' => \App\Support\Money::format($cc->commission_min_amount).' '.$commissionCurrency]) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-center text-xs">
                                @if ($hasFeeOverride)
                                    <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-semibold" data-fee-override>
                                        {{ collect([
                                            $cc->fee_percentage !== null ? rtrim(rtrim(number_format((float) $cc->fee_percentage, 4, '.', ''), '0'), '.').'%' : null,
                                            $cc->fee_fixed !== null ? \App\Support\Money::format($cc->fee_fixed).' '.$commissionCurrency : null,
                                        ])->filter()->implode(' + ') }}
                                    </span>
                                @else
                                    <span class="text-gray-400" data-fee-default>
                                        {{ __('marketer_commissions.fee_default_inline', [
                                            'pct' => rtrim(rtrim(number_format((float) $ruleDefaults['fee_percentage'], 4, '.', ''), '0'), '.') ?: '0',
                                            'fixed' => \App\Support\Money::format($ruleDefaults['fee_fixed']),
                                            'currency' => $commissionCurrency,
                                        ]) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-center">
                                <form method="POST" action="{{ route('admin.marketers.category-commissions.destroy', [$marketer, $cc->id]) }}"
                                      onsubmit="return confirm(@js(__('marketer_commissions.delete_confirm')));">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-500 hover:text-red-700 text-xs font-semibold">{{ __('admin.static_text.admin_marketers_show.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">{{ __('marketer_commissions.empty_'.$textKey) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.marketers.category-commissions.store', $marketer) }}"
              class="p-4 border-t border-gray-100 bg-gray-50 space-y-4"
              data-commission-form="{{ $textKey }}"
              x-data="{
                  type: @js($typeKey),
                  allowFixed: @js($allowFixed),
                  allowPct: @js($allowPct),
                  cur: @js($commissionCurrency),
                  scope: @js(old('scope', 'products')),
                  category: @js((string) old('category_id', '')),
                  rate: @js((string) old('commission_rate', '')),
                  flat: @js((string) old('commission_flat_amount', '')),
                  feePct: @js((string) old('fee_percentage', '')),
                  feeFixed: @js((string) old('fee_fixed', '')),
                  cats: @js($categoryOptions),
                  defaults: @js($commissionDefaults),
                  t: @js($previewStrings),
                  fmt(n) { n = Math.round((Number(n) || 0) * 100) / 100; return Number.isInteger(n) ? n.toLocaleString('en-US') : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                  fill(s, map) { Object.keys(map).forEach(k => { s = s.split(':' + k).join(map[k]); }); return s; },
                  get def() { return this.defaults[this.scope] || { influencer: 0, fee_percentage: 0, fee_fixed: 0, categories: {} }; },
                  get hasCategoryDefault() { return this.category !== '' && Object.prototype.hasOwnProperty.call(this.def.categories, this.category); },
                  get defaultInfluencer() { return this.hasCategoryDefault ? this.def.categories[this.category] : this.def.influencer; },
                  get commissionHint() {
                      if (this.type !== 'influencer' || this.flat !== '') return '';
                      return this.defaultInfluencer > 0
                          ? this.fill(this.t.default_influencer, { amount: this.fmt(this.defaultInfluencer), currency: this.cur })
                          : this.t.default_none;
                  },
                  get effFeePct() { return this.feePct !== '' ? Number(this.feePct) : Number(this.def.fee_percentage); },
                  get effFeeFixed() { return this.feeFixed !== '' ? Number(this.feeFixed) : Number(this.def.fee_fixed); },
                  get feeHint() {
                      if (this.feePct !== '' && this.feeFixed !== '') return '';
                      if (Number(this.def.fee_percentage) === 0 && Number(this.def.fee_fixed) === 0) return this.t.fee_none;
                      return this.fill(this.t.default_fee, { pct: this.fmt(this.def.fee_percentage), fixed: this.fmt(this.def.fee_fixed), currency: this.cur });
                  },
                  get isFixedOnly() { return this.type === 'influencer'; },
                  get gross() {
                      if (this.isFixedOnly) return this.flat !== '' ? Number(this.flat) : Number(this.defaultInfluencer);
                      return (this.allowPct ? (Number(this.rate) || 0) * 10 : 0) + (this.allowFixed ? Number(this.flat) || 0 : 0);
                  },
                  get example() {
                      const gross = this.gross;
                      if (!(gross > 0)) return '';
                      const fee = Math.min(gross, Math.max(0, gross * this.effFeePct / 100 + this.effFeeFixed));
                      return this.fill(this.isFixedOnly ? this.t.example_fixed : this.t.example_sale, { gross: this.fmt(gross), fee: this.fmt(fee), net: this.fmt(gross - fee), currency: this.cur });
                  },
              }"
              x-effect="scope; category; $nextTick(() => {
                  window.initSelect2 && window.initSelect2($($el));
                  $refs.excl && $($refs.excl).prop('disabled', category !== '').trigger('change.select2');
              })">
            @csrf
            @if ($typeKey)
                <input type="hidden" name="marketer_type" value="{{ $typeKey }}">
            @endif

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketer_commission_type') }}</label>
                    <select name="scope" x-model="scope" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[160px]">
                        @foreach ($scopeLabels as $sc => $label)
                            <option value="{{ $sc }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketers.col_category') }}</label>
                    <select name="category_id" data-select2-init x-model="category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[200px]">
                        <option value="">{{ __('admin.marketer_commission_default_all_type') }}</option>
                        <template x-for="c in cats[scope]" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                    </select>
                </div>
                <div x-show="category === ''" x-cloak x-effect="scope; $refs.excl && Array.from($refs.excl.options).forEach(o => o.selected = false)">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketer_commission_except') }}</label>
                    <select name="excluded_category_ids[]" x-ref="excl" data-select2-init multiple :disabled="category !== ''" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[200px]">
                        <template x-for="c in cats[scope]" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">{{ __('admin.marketer_commission_except_hint') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Commission --}}
                <fieldset class="rounded-lg border border-gray-200 bg-white p-4">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wide text-gray-500">{{ __('marketer_commissions.group_commission') }}</legend>
                    <div class="flex flex-wrap gap-4">
                        <div @unless($allowPct) title="{{ __('marketer_commissions.tooltip_percentage_disabled_'.$textKey) }}" @endunless>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('marketer_commissions.field_percentage') }}</label>
                            <input type="number" step="0.01" min="0" max="100" name="commission_rate" x-model="rate"
                                   @unless($allowPct) disabled @endunless
                                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400">
                            @unless ($allowPct)
                                <p class="mt-1 max-w-[12rem] text-[11px] text-gray-400">{{ __('marketer_commissions.tooltip_percentage_disabled_'.$textKey) }}</p>
                            @endunless
                            @if ($showErrors) @error('commission_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror @endif
                        </div>
                        <div @unless($allowFixed) title="{{ __('marketer_commissions.tooltip_fixed_disabled_'.$textKey) }}" @endunless>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                {{ $typeKey === 'influencer' ? __('marketer_commissions.field_fixed_per_campaign') : __('marketer_commissions.field_fixed') }}
                                ({{ $commissionCurrency }})
                            </label>
                            <input type="number" step="0.01" min="0" name="commission_flat_amount" x-model="flat"
                                   @unless($allowFixed) disabled @endunless
                                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400">
                            @unless ($allowFixed)
                                <p class="mt-1 max-w-[12rem] text-[11px] text-gray-400">{{ __('marketer_commissions.tooltip_fixed_disabled_'.$textKey) }}</p>
                            @endunless
                            @if ($showErrors) @error('commission_flat_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror @endif
                        </div>
                        @if ($typeKey !== 'influencer')
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketer_commission_min') }} ({{ $commissionCurrency }})</label>
                                <input type="number" step="0.01" min="0" name="commission_min_amount" value="{{ old('commission_min_amount') }}"
                                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
                            </div>
                        @endif
                    </div>
                    <p class="mt-2 text-[11px] text-gray-500" x-show="commissionHint" x-text="commissionHint" data-commission-effective-hint></p>
                </fieldset>

                {{-- Commission fee override --}}
                <fieldset class="rounded-lg border border-gray-200 bg-white p-4">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wide text-gray-500">{{ __('marketer_commissions.group_fee') }}</legend>
                    <div class="flex flex-wrap gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('marketer_commissions.field_fee_percentage') }}</label>
                            <input type="number" step="0.01" min="0" max="100" name="fee_percentage" x-model="feePct"
                                   :placeholder="fmt(def.fee_percentage)"
                                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
                            @if ($showErrors) @error('fee_percentage')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror @endif
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('marketer_commissions.field_fee_fixed') }} ({{ $commissionCurrency }})</label>
                            <input type="number" step="0.01" min="0" name="fee_fixed" x-model="feeFixed"
                                   :placeholder="fmt(def.fee_fixed)"
                                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
                            @if ($showErrors) @error('fee_fixed')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror @endif
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-500" x-show="feeHint" x-text="feeHint" data-fee-effective-hint></p>
                    <p class="mt-1 text-[11px] font-medium text-emerald-700" x-show="example" x-text="example" data-commission-example></p>
                </fieldset>
            </div>

            @if ($showErrors)
                @error('marketer_type')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('category_id')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('excluded_category_ids')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @endif

            <div>
                <button class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500 transition-colors">{{ __('admin.marketers.add_or_update') }}</button>
            </div>
        </form>
    </div>
@endforeach
