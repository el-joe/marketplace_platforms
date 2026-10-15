@extends('layouts.admin')

@push('scripts')
    @vite('resources/js/components/select2.js')
@endpush

@section('title', __('marketer_settings.title'))

@section('content')
    @php
        $input = 'rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-500';
        $btn = 'inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700';
        $locale = app()->getLocale();
        $countryName = fn ($c) => $locale === 'ar' ? ($c->name_ar ?: $c->name_en) : ($c->name_en ?: $c->name_ar);
        $trim = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
        $verticalLabels = collect($verticals)->mapWithKeys(fn ($v) => [$v->value => $v->label()]);

        // Grid values, keyed "countryId:vertical", as strings the inputs can bind to.
        $influencerMap = [];
        $feeMap = [];
        foreach ($countries as $country) {
            foreach ($verticals as $vertical) {
                $row = $rows->get($country->id.':'.$vertical->value);
                $key = $country->id.':'.$vertical->value;
                $influencerMap[$key] = $row && (float) $row->influencer_fixed_amount > 0 ? $trim($row->influencer_fixed_amount) : '';
                $feeMap[$key] = [
                    'pct' => $row && (float) $row->commission_fee_percentage > 0 ? $trim($row->commission_fee_percentage) : '',
                    'fixed' => $row && (float) $row->commission_fee_fixed > 0 ? $trim($row->commission_fee_fixed) : '',
                ];
            }
        }
        $countryMeta = $countries->mapWithKeys(fn ($c) => [$c->id => ['name' => $countryName($c), 'currency' => $c->currency_code]]);
        $tabs = [
            'influencer' => ['icon' => 'banknotes', 'label' => __('marketer_settings.tabs.influencer')],
            'fees' => ['icon' => 'percent-badge', 'label' => __('marketer_settings.tabs.fees')],
            'campaign' => ['icon' => 'megaphone', 'label' => __('marketer_settings.tabs.campaign')],
            'general' => ['icon' => 'cog-6-tooth', 'label' => __('marketer_settings.tabs.general')],
        ];
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('marketer_settings.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('marketer_settings.subtitle') }}</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
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

    @unless ($canEdit)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('marketer_settings.read_only') }}</div>
    @endunless

    <div x-data="{ tab: @js($tab) }" data-marketer-settings>
        <div class="mb-4 flex gap-2 overflow-x-auto border-b border-gray-200" role="tablist">
            @foreach ($tabs as $id => $t)
                <button type="button" role="tab" @click="tab = '{{ $id }}'" data-tab="{{ $id }}"
                    :aria-selected="tab === '{{ $id }}'"
                    :class="tab === '{{ $id }}' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium">
                    <x-heroicon name="{{ $t['icon'] }}" class="h-4 w-4" />
                    {{ $t['label'] }}
                </button>
            @endforeach
        </div>

        {{-- ═══════ 1. Default influencer commission ═══════ --}}
        <div x-show="tab === 'influencer'" data-panel="influencer" class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                 x-data="{
                     amounts: @js($influencerMap),
                     meta: @js($countryMeta),
                     verticals: @js($verticalLabels),
                     t: @js(['example' => __('marketer_settings.influencer.example'), 'empty' => __('marketer_settings.influencer.example_empty')]),
                     fmt(n) { n = Math.round((Number(n) || 0) * 100) / 100; return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                     get example() {
                         const key = Object.keys(this.amounts).find(k => Number(this.amounts[k]) > 0);
                         if (!key) return this.t.empty;
                         const [country, vertical] = key.split(':');
                         return this.t.example
                             .split(':amount').join(this.fmt(this.amounts[key]))
                             .split(':currency').join(this.meta[country].currency)
                             .split(':country').join(this.meta[country].name)
                             .split(':vertical').join(this.verticals[vertical]);
                     },
                 }">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('marketer_settings.influencer.title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('marketer_settings.influencer.intro') }}</p>

                <div class="mt-4 flex items-start gap-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800" data-influencer-example>
                    <x-heroicon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span x-text="example"></span>
                </div>

                <form method="POST" action="{{ route('admin.marketer-settings.influencer-defaults.update') }}" class="mt-5">
                    @csrf
                    <fieldset @disabled(! $canEdit)>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.country') }}</th>
                                        @foreach ($verticals as $vertical)
                                            <th class="px-3 py-2 text-start font-medium text-gray-500">{{ $vertical->label() }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($countries as $country)
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-gray-900">{{ $countryName($country) }}</td>
                                            @foreach ($verticals as $vertical)
                                                @php $key = $country->id.':'.$vertical->value; @endphp
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" min="0" step="0.01" inputmode="decimal"
                                                               name="amounts[{{ $country->id }}][{{ $vertical->value }}]"
                                                               x-model="amounts['{{ $key }}']"
                                                               placeholder="0.00" aria-label="{{ $countryName($country) }} · {{ $vertical->label() }}"
                                                               class="w-32 {{ $input }}">
                                                        <span class="text-xs text-gray-400">{{ $country->currency_code }}</span>
                                                    </div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">{{ __('marketer_settings.influencer.helper') }}</p>
                    </fieldset>
                    @if ($canEdit)
                        <div class="mt-4"><button type="submit" class="{{ $btn }}">{{ __('common.save') }}</button></div>
                    @endif
                </form>
            </div>

            {{-- Category overrides --}}
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('marketer_settings.influencer.overrides_title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('marketer_settings.influencer.overrides_hint') }}</p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm" data-influencer-overrides>
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.country') }}</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.vertical') }}</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.category') }}</th>
                                <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.influencer.amount_per_campaign') }}</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($overrides as $override)
                                <tr>
                                    <td class="px-3 py-2">{{ $override->country ? $countryName($override->country) : '—' }}</td>
                                    <td class="px-3 py-2">{{ $override->category_type->label() }}</td>
                                    <td class="px-3 py-2">{{ $override->category_name }}</td>
                                    <td class="px-3 py-2 font-semibold">{{ \App\Support\Money::format($override->influencer_fixed_amount) }} {{ $override->country?->currency_code }}</td>
                                    <td class="px-3 py-2 text-end">
                                        @if ($canEdit)
                                            <form method="POST" action="{{ route('admin.marketer-settings.influencer-overrides.destroy', $override) }}"
                                                  onsubmit="return confirm(@js(__('marketer_settings.influencer.override_delete_confirm')));">
                                                @csrf @method('DELETE')
                                                <button class="text-xs font-semibold text-red-600 hover:underline">{{ __('common.delete') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">{{ __('marketer_settings.influencer.no_overrides') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($canEdit)
                    <form method="POST" action="{{ route('admin.marketer-settings.influencer-overrides.store') }}"
                          class="mt-5 flex flex-wrap items-end gap-3 rounded-md bg-gray-50 p-4"
                          x-data="{ vertical: @js(old('category_type', 'product')), cats: @js($categoryOptions) }"
                          x-effect="vertical; $nextTick(() => { window.initSelect2 && window.initSelect2($($el)); })">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">{{ __('marketer_settings.country') }}</label>
                            <select name="country_id" class="{{ $input }}" required>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected(old('country_id') === $country->id)>{{ $countryName($country) }} ({{ $country->currency_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">{{ __('marketer_settings.vertical') }}</label>
                            <select name="category_type" x-model="vertical" class="{{ $input }}">
                                @foreach ($verticals as $vertical)
                                    <option value="{{ $vertical->value }}">{{ $vertical->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">{{ __('marketer_settings.category') }}</label>
                            <select name="category_id" data-select2-init class="{{ $input }} min-w-[220px]" required>
                                <template x-for="c in cats[vertical]" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-600">{{ __('marketer_settings.influencer.amount_per_campaign') }}</label>
                            <input type="number" min="0" step="0.01" name="influencer_fixed_amount" value="{{ old('influencer_fixed_amount') }}" required class="w-32 {{ $input }}">
                        </div>
                        <button type="submit" class="{{ $btn }}">{{ __('marketer_settings.influencer.add_override') }}</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ═══════ 2. Commission fees ═══════ --}}
        <div x-show="tab === 'fees'" x-cloak data-panel="fees" class="space-y-6"
             x-data="{
                 sample: 100,
                 fees: @js($feeMap),
                 fmt(n) { n = Math.round((Number(n) || 0) * 100) / 100; return Number.isInteger(n) ? n.toLocaleString('en-US') : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                 fee(key) {
                     const f = this.fees[key], gross = Math.max(0, Number(this.sample) || 0);
                     return Math.min(gross, Math.max(0, gross * (Number(f.pct) || 0) / 100 + (Number(f.fixed) || 0)));
                 },
                 net(key) { return Math.max(0, (Number(this.sample) || 0) - this.fee(key)); },
             }">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('marketer_settings.fees.title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('marketer_settings.fees.intro') }}</p>

                <div class="mt-4 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800" data-fee-worked-example>
                    <div class="font-semibold">{{ __('marketer_settings.fees.formula') }}</div>
                    <div class="mt-1">{{ __('marketer_settings.fees.worked_example') }}</div>
                </div>

                <form method="POST" action="{{ route('admin.marketer-settings.commission-fees.update') }}" class="mt-5">
                    @csrf
                    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                        <label for="fee-sample" class="font-medium text-gray-700">{{ __('marketer_settings.fees.sample_label') }}</label>
                        <input id="fee-sample" type="number" min="0" step="1" x-model="sample" class="w-28 {{ $input }}">
                        <span class="text-xs text-gray-400">{{ __('marketer_settings.fees.sample_hint') }}</span>
                    </div>

                    <fieldset @disabled(! $canEdit)>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.country') }}</th>
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.vertical') }}</th>
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.fees.percentage') }}</th>
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.fees.fixed') }}</th>
                                        <th class="px-3 py-2 text-start font-medium text-gray-500">{{ __('marketer_settings.fees.example_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($countries as $country)
                                        @foreach ($verticals as $vertical)
                                            @php $key = $country->id.':'.$vertical->value; @endphp
                                            <tr @class(['border-t-2 border-gray-200' => $loop->first && ! $loop->parent->first])>
                                                <td class="px-3 py-2 font-medium text-gray-900">{{ $loop->first ? $countryName($country) : '' }}</td>
                                                <td class="px-3 py-2 text-gray-600">{{ $vertical->label() }}</td>
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-1">
                                                        <input type="number" min="0" max="100" step="0.01" inputmode="decimal"
                                                               name="fees[{{ $country->id }}][{{ $vertical->value }}][percentage]"
                                                               x-model="fees['{{ $key }}'].pct" placeholder="0"
                                                               aria-label="{{ $countryName($country) }} · {{ $vertical->label() }} %"
                                                               class="w-24 {{ $input }}">
                                                        <span class="text-xs text-gray-400">%</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-1">
                                                        <input type="number" min="0" step="0.01" inputmode="decimal"
                                                               name="fees[{{ $country->id }}][{{ $vertical->value }}][fixed]"
                                                               x-model="fees['{{ $key }}'].fixed" placeholder="0.00"
                                                               aria-label="{{ $countryName($country) }} · {{ $vertical->label() }} {{ $country->currency_code }}"
                                                               class="w-28 {{ $input }}">
                                                        <span class="text-xs text-gray-400">{{ $country->currency_code }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2 text-xs text-gray-600">
                                                    <span x-text="fmt(sample) + ' → ' + @js(__('marketer_settings.fees.fee_word')) + ' ' + fmt(fee('{{ $key }}')) + ' · ' + @js(__('marketer_settings.fees.net_word')) + ' ' + fmt(net('{{ $key }}'))"></span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-xs text-gray-500">{{ __('marketer_settings.fees.helper') }}</p>
                    </fieldset>
                    @if ($canEdit)
                        <div class="mt-4"><button type="submit" class="{{ $btn }}">{{ __('common.save') }}</button></div>
                    @endif
                </form>
            </div>
        </div>

        {{-- ═══════ 3. Campaign fees ═══════ --}}
        <div x-show="tab === 'campaign'" x-cloak data-panel="campaign" class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('marketer_settings.campaign.title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('marketer_settings.campaign.intro') }}</p>

                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-md border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('marketer_settings.campaign.fixed_title') }}</dt>
                        <dd class="mt-1 text-sm text-gray-600">{{ __('marketer_settings.campaign.fixed_body') }}</dd>
                        <dd class="mt-2 text-2xl font-bold text-gray-900" data-campaign-fixed-count>{{ $campaignFees['fixed_rows'] }}</dd>
                        <dd class="text-xs text-gray-400">{{ __('marketer_settings.campaign.fixed_count') }}</dd>
                    </div>
                    <div class="rounded-md border border-gray-200 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('marketer_settings.campaign.variable_title') }}</dt>
                        <dd class="mt-1 text-sm text-gray-600">{{ __('marketer_settings.campaign.variable_body') }}</dd>
                        <dd class="mt-2 text-2xl font-bold text-gray-900" data-campaign-variable-count>{{ $campaignFees['variable_rows'] }}</dd>
                        <dd class="text-xs text-gray-400">{{ __('marketer_settings.campaign.variable_count') }}</dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <div class="font-semibold">{{ __('marketer_settings.campaign.formula') }}</div>
                    <div class="mt-1">{{ __('marketer_settings.campaign.worked_example') }}</div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('admin.marketing-fees.index', ['tab' => 'fixed']) }}" class="{{ $btn }}">{{ __('marketer_settings.campaign.open_fixed') }}</a>
                    <a href="{{ route('admin.marketing-fees.index', ['tab' => 'variable']) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('marketer_settings.campaign.open_variable') }}</a>
                </div>
            </div>
        </div>

        {{-- ═══════ 4. General ═══════ --}}
        <div x-show="tab === 'general'" x-cloak data-panel="general" class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" data-peer-min-stock>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('marketer_settings.general.title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('marketer_settings.general.intro') }}</p>

                <form method="POST" action="{{ route('admin.marketer-settings.general.update') }}" class="mt-5">
                    @csrf
                    <fieldset @disabled(! $canEdit) class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        @foreach ([
                            'marketer_referral_grace_days' => ['min' => 0, 'max' => 90, 'key' => 'referral_grace'],
                            'marketer_payout_clearing_days' => ['min' => 0, 'max' => 60, 'key' => 'clearing'],
                            'marketer_attribution_window_days' => ['min' => 1, 'max' => 365, 'key' => 'attribution'],
                            'marketer_peer_campaign_min_stock' => ['min' => 1, 'max' => 100000, 'key' => 'peer_min_stock'],
                        ] as $name => $cfg)
                            <div>
                                <label for="{{ $name }}" class="block text-sm font-semibold text-gray-800">{{ __('marketer_settings.general.'.$cfg['key'].'_label') }}</label>
                                <input id="{{ $name }}" type="number" name="{{ $name }}" min="{{ $cfg['min'] }}" max="{{ $cfg['max'] }}" step="1"
                                       value="{{ old($name, $general[$name]) }}" class="mt-1 w-32 {{ $input }}">
                                <p class="mt-1 text-xs text-gray-500">{{ __('marketer_settings.general.'.$cfg['key'].'_hint') }}</p>
                                @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </fieldset>
                    @if ($canEdit)
                        <div class="mt-6"><button type="submit" class="{{ $btn }}">{{ __('common.save') }}</button></div>
                    @endif
                </form>
            </div>
        </div>
    </div>
@endsection
