{{--
    Commissions section of the category form: per-country FBP/FBM/FBN overrides
    (country_commissions[<country>][...]) and price tiers (tiers[i][...]), both
    saved with the whole form. Works on create too.
    Expects: $countries, $activeCountries, $countryCommissions (keyed by country id), $commissionTiers
--}}
@php
    $tierCountryIds = $commissionTiers->pluck('country_id')->filter()->unique();
    $tierScopes = collect([['id' => '', 'name' => __('admin.categories.tier_global_label'), 'sub' => __('admin.categories.tier_global_sublabel'), 'flag' => '', 'currency' => '—']])
        ->concat(
            $countries->filter(fn ($c) => $c->is_active || $tierCountryIds->contains($c->id))
                ->map(fn ($c) => ['id' => $c->id, 'name' => localized($c), 'sub' => '', 'flag' => $c->flag_emoji ?? '', 'currency' => $c->currency_code ?? '—'])
        )->values();

    $initialTiers = $commissionTiers->map(fn ($t) => [
        'id' => $t->id,
        'country_id' => $t->country_id ?? '',
        'price_from' => (string) $t->price_from,
        'price_to' => $t->price_to === null ? '' : (string) $t->price_to,
        'commission_rate' => (string) $t->commission_rate,
        'min_commission' => (string) ($t->min_commission ?? 0),
    ])->values();

    $commissionColumns = [
        'fbp' => ['label' => __('admin.categories.fbp_label'), 'color' => 'text-blue-600'],
        'fbm' => ['label' => __('admin.categories.fbm_label'), 'color' => 'text-amber-600'],
        'fbn' => ['label' => __('admin.categories.fbn_label'), 'color' => 'text-green-600'],
    ];
@endphp
<div class="space-y-8 p-6">

    {{-- Per-country commission overrides --}}
    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">{{ __('category_form.commissions.country_title') }}</h3>
            <p class="text-xs text-gray-500">{{ __('category_form.commissions.country_hint') }}</p>
        </div>

        @forelse($activeCountries as $country)
            @php $override = $countryCommissions->get($country->id); @endphp
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <span class="text-base">{{ $country->flag_emoji }}</span>
                    <span class="text-sm font-semibold text-gray-700">{{ localized($country) }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-500">{{ $country->currency_code ?? '—' }}</span>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach($commissionColumns as $model => $meta)
                        <div class="space-y-2">
                            <p class="text-xs font-bold uppercase tracking-wide {{ $meta['color'] }}">{{ $meta['label'] }}</p>
                            @foreach(['pct' => ['label' => __('admin.categories.percentage_rate'), 'suffix' => '%', 'step' => '0.01'], 'fixed' => ['label' => __('admin.categories.fixed_fee_per_unit'), 'suffix' => $country->currency_code ?? '', 'step' => '0.001']] as $kind => $field)
                                @php $fieldName = "commission_{$model}_{$kind}"; @endphp
                                <div class="space-y-1">
                                    <label class="block text-xs font-medium text-gray-500" for="cc_{{ $country->id }}_{{ $fieldName }}">{{ $field['label'] }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" id="cc_{{ $country->id }}_{{ $fieldName }}"
                                            name="country_commissions[{{ $country->id }}][{{ $fieldName }}]"
                                            value="{{ old("country_commissions.{$country->id}.{$fieldName}", $override?->{$fieldName}) }}"
                                            step="{{ $field['step'] }}" min="0" @if($kind === 'pct') max="100" @endif
                                            placeholder="{{ __('admin.categories.inherit_global') }}" class="input w-full text-sm">
                                        <span class="whitespace-nowrap font-mono text-xs text-gray-400">{{ $field['suffix'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm italic text-gray-400">—</p>
        @endforelse
    </div>

    {{-- Price tiers --}}
    <div class="space-y-3" x-data="categoryTiersSection(@js($initialTiers))">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">{{ __('category_form.commissions.tiers_title') }}</h3>
            <p class="text-xs text-gray-500">{{ __('admin.categories.tier_global_hint') }}</p>
        </div>

        @foreach($tierScopes as $scope)
            <div class="overflow-hidden rounded-lg border border-gray-200">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        @if($scope['flag'])<span class="text-base leading-none">{{ $scope['flag'] }}</span>@endif
                        <span class="text-sm font-semibold text-gray-700">{{ $scope['name'] }}</span>
                        @if($scope['sub'])<span class="text-xs text-gray-400">{{ $scope['sub'] }}</span>@endif
                        @if($scope['id'])<span class="rounded-md border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $scope['currency'] }}</span>@endif
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" @click="add(@js($scope['id']))">
                        <x-heroicon name="plus" class="h-4 w-4" />
                        {{ __('category_form.commissions.add_tier') }}
                    </button>
                </div>

                <p x-show="countFor(@js($scope['id'])) === 0" class="px-4 py-3 text-xs italic text-gray-400">{{ __('category_form.commissions.no_tiers') }}</p>

                <div x-show="countFor(@js($scope['id'])) > 0" class="divide-y divide-gray-100">
                    <template x-for="tier in tiers" :key="tier.key">
                        <div x-show="tier.country_id === @js($scope['id'])" class="grid grid-cols-2 items-end gap-3 px-4 py-3 sm:grid-cols-5">
                            <template x-if="tier.country_id === @js($scope['id'])">
                                <div class="contents">
                                    <input type="hidden" :name="`tiers[${index(tier)}][id]`" :value="tier.id">
                                    <input type="hidden" :name="`tiers[${index(tier)}][country_id]`" :value="tier.country_id">
                                    <label class="space-y-1">
                                        <span class="block text-xs font-medium text-gray-500">{{ __('admin.categories.tier_price_from') }}</span>
                                        <input type="number" step="0.01" min="0" class="input w-full text-sm" :name="`tiers[${index(tier)}][price_from]`" x-model="tier.price_from">
                                    </label>
                                    <label class="space-y-1">
                                        <span class="block text-xs font-medium text-gray-500">{{ __('admin.categories.tier_price_to') }}</span>
                                        <input type="number" step="0.01" min="0" class="input w-full text-sm" :name="`tiers[${index(tier)}][price_to]`" x-model="tier.price_to"
                                               placeholder="{{ __('admin.categories.tier_no_limit') }}">
                                    </label>
                                    <label class="space-y-1">
                                        <span class="block text-xs font-medium text-gray-500">{{ __('admin.categories.tier_rate') }}</span>
                                        <input type="number" step="0.01" min="0" max="100" class="input w-full text-sm" :name="`tiers[${index(tier)}][commission_rate]`" x-model="tier.commission_rate">
                                    </label>
                                    <label class="space-y-1">
                                        <span class="block text-xs font-medium text-gray-500">{{ __('admin.categories.tier_min') }}</span>
                                        <input type="number" step="0.01" min="0" class="input w-full text-sm" :name="`tiers[${index(tier)}][min_commission]`" x-model="tier.min_commission">
                                    </label>
                                    <div class="flex justify-end pb-2">
                                        <button type="button" class="text-xs text-danger-600 hover:underline" @click="remove(tier)">{{ __('category_form.commissions.remove_tier') }}</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
function categoryTiersSection(initial) {
    let seq = 0;
    return {
        tiers: initial.map(t => ({ ...t, key: ++seq })),

        index(tier) {
            return this.tiers.indexOf(tier);
        },

        countFor(countryId) {
            return this.tiers.filter(t => t.country_id === countryId).length;
        },

        add(countryId) {
            const last = this.tiers.filter(t => t.country_id === countryId).at(-1);
            this.tiers.push({
                key: ++seq,
                id: '',
                country_id: countryId,
                price_from: last && last.price_to !== '' ? String(Number(last.price_to) + 0.01) : (last ? '' : '0'),
                price_to: '',
                commission_rate: '0',
                min_commission: '0',
            });
            this.$dispatch('category-form:changed');
        },

        remove(tier) {
            this.tiers.splice(this.tiers.indexOf(tier), 1);
            this.$dispatch('category-form:changed');
        },
    };
}
</script>
