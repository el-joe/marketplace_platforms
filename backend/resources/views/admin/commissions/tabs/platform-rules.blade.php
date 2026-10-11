@php
    $canEdit = auth('admin')->user()->can('categories.edit');
    $category = $editor['category'];
    $countryName = fn ($c) => $c ? ($c->name_en ?: $c->name_ar) : '—';
    $countriesById = $editor['countries']->keyBy('id');
    $overrideFields = [
        'commission_fbp_pct' => ['col' => 'fbp_pct', 'step' => '0.01', 'max' => '100'],
        'commission_fbp_fixed' => ['col' => 'fbp_fixed', 'step' => '1', 'max' => ''],
        'commission_fbn_pct' => ['col' => 'fbn_pct', 'step' => '0.01', 'max' => '100'],
        'commission_fbn_fixed' => ['col' => 'fbn_fixed', 'step' => '1', 'max' => ''],
    ];
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.').'%';
@endphp

<form method="GET" action="{{ route('admin.commissions.index') }}" class="my-5 flex flex-wrap items-end gap-3">
    <input type="hidden" name="tab" value="platform-rules">
    <div class="min-w-[16rem] flex-1">
        <label for="pr-category" class="{{ $label }}">{{ __('commissions.platform_form.choose_category') }}</label>
        <select id="pr-category" name="category" class="{{ $input }}" onchange="this.form.submit()">
            <option value="">—</option>
            @foreach ($editor['categories'] as $c)
                <option value="{{ $c->id }}" @selected($category?->id === $c->id)>{{ $c->name_en ?: $c->name_ar }}</option>
            @endforeach
        </select>
    </div>
    <noscript><button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.vendor_form.load') }}</button></noscript>
</form>

@if ($category)
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <h3 class="text-sm font-semibold text-gray-900">{{ $category->name_en ?: $category->name_ar }}</h3>
        <p class="mt-1 text-xs text-gray-500">{{ __('commissions.platform_form.base_readonly') }}</p>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div><dt class="text-xs text-gray-500">{{ __('commissions.columns.fbp_pct') }}</dt><dd class="font-medium">{{ $pct($category->commission_fbp_pct) }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('commissions.columns.fbp_fixed') }}</dt><dd class="font-medium">{{ $category->commission_fbp_fixed ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('commissions.columns.fbn_pct') }}</dt><dd class="font-medium">{{ $pct($category->commission_fbn_pct) }}</dd></div>
            <div><dt class="text-xs text-gray-500">{{ __('commissions.columns.fbn_fixed') }}</dt><dd class="font-medium">{{ $category->commission_fbn_fixed ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Country overrides --}}
    <section class="mt-6" aria-labelledby="pr-overrides-title">
        <h3 id="pr-overrides-title" class="mb-1 text-sm font-semibold text-gray-900">{{ __('commissions.platform_form.overrides_title') }}</h3>
        <p class="mb-3 text-xs text-gray-500">{{ __('commissions.platform_form.overrides_help') }}</p>

        @if ($canEdit)
            <form method="POST" id="pr-override-form" action="{{ route('admin.categories.country-commission.update', $category->id) }}" data-ajax class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label for="po-country" class="{{ $label }}">{{ __('commissions.columns.country') }}</label>
                        <select id="po-country" name="country_id" required class="{{ $input }}">
                            <option value="">—</option>
                            @foreach ($editor['countries'] as $country)
                                <option value="{{ $country->id }}">{{ $countryName($country) }}</option>
                            @endforeach
                        </select>
                        <p data-error-for="country_id" class="hidden text-xs text-red-700"></p>
                    </div>
                    @foreach ($overrideFields as $name => $meta)
                        <div>
                            <label for="po-{{ $name }}" class="{{ $label }}">{{ __('commissions.columns.'.$meta['col']) }}</label>
                            <input id="po-{{ $name }}" type="number" name="{{ $name }}" min="0" max="{{ $meta['max'] }}" step="{{ $meta['step'] }}" class="{{ $input }}">
                            <p data-error-for="{{ $name }}" class="hidden text-xs text-red-700"></p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ __('commissions.platform_form.blank_inherits') }}</p>
                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.rule_form.save') }}</button>
                    <span data-status role="status" class="text-xs"></span>
                </div>
            </form>
        @endif

        @if ($editor['overrides']->isEmpty())
            <p class="rounded-lg border border-dashed border-gray-300 px-4 py-5 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
        @else
            <div class="relative overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50"><tr>
                        @foreach (['country', 'fbp_pct', 'fbp_fixed', 'fbn_pct', 'fbn_fixed'] as $column)
                            <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.'.$column) }}</th>
                        @endforeach
                        @if ($canEdit)<th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.rule_form.edit') }}</span></th>@endif
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($editor['overrides'] as $o)
                            <tr>
                                <td class="px-3 py-2 text-gray-800">{{ $countryName($o->country) }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $pct($o->commission_fbp_pct) }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $o->commission_fbp_fixed ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $pct($o->commission_fbn_pct) }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $o->commission_fbn_fixed ?? '—' }}</td>
                                @if ($canEdit)
                                    <td class="px-3 py-2 text-end">
                                        @php
                                            $overrideFill = [
                                                'country_id' => $o->country_id,
                                                'commission_fbp_pct' => $o->commission_fbp_pct,
                                                'commission_fbp_fixed' => $o->commission_fbp_fixed,
                                                'commission_fbn_pct' => $o->commission_fbn_pct,
                                                'commission_fbn_fixed' => $o->commission_fbn_fixed,
                                            ];
                                        @endphp
                                        <button type="button" class="{{ $btnLink }}" data-fill-override="{{ json_encode($overrideFill) }}">{{ __('commissions.rule_form.edit') }}</button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Price tiers --}}
    <section class="mt-8" aria-labelledby="pr-tiers-title">
        <h3 id="pr-tiers-title" class="mb-1 text-sm font-semibold text-gray-900">{{ __('commissions.platform_form.tiers_title') }}</h3>
        <p class="mb-3 text-xs text-gray-500">{{ __('commissions.platform_form.tiers_help') }}</p>

        @if ($canEdit)
            <form method="POST" id="pr-tier-form" action="{{ route('admin.categories.commission-tiers.store', $category->id) }}" data-ajax class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                @csrf
                <input type="hidden" name="_method" value="POST" id="pt-method">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label for="pt-country" class="{{ $label }}">{{ __('commissions.columns.country') }}</label>
                        <select id="pt-country" name="country_id" class="{{ $input }}">
                            <option value="">{{ __('commissions.platform_form.global_tier') }}</option>
                            @foreach ($editor['countries'] as $country)
                                <option value="{{ $country->id }}">{{ $countryName($country) }}</option>
                            @endforeach
                        </select>
                        <p data-error-for="country_id" class="hidden text-xs text-red-700"></p>
                    </div>
                    <div>
                        <label for="pt-from" class="{{ $label }}">{{ __('commissions.platform_form.price_from') }}</label>
                        <input id="pt-from" type="number" name="price_from" min="0" step="1" required class="{{ $input }}">
                        <p data-error-for="price_from" class="hidden text-xs text-red-700"></p>
                    </div>
                    <div>
                        <label for="pt-to" class="{{ $label }}">{{ __('commissions.platform_form.price_to') }}</label>
                        <input id="pt-to" type="number" name="price_to" min="1" step="1" class="{{ $input }}">
                        <p data-error-for="price_to" class="hidden text-xs text-red-700"></p>
                    </div>
                    <div>
                        <label for="pt-rate" class="{{ $label }}">{{ __('commissions.columns.rate_pct') }}</label>
                        <input id="pt-rate" type="number" name="commission_rate" min="0" max="100" step="0.01" required class="{{ $input }}">
                        <p data-error-for="commission_rate" class="hidden text-xs text-red-700"></p>
                    </div>
                    <div>
                        <label for="pt-min" class="{{ $label }}">{{ __('commissions.columns.min') }}</label>
                        <input id="pt-min" type="number" name="min_commission" min="0" step="1" class="{{ $input }}">
                        <p data-error-for="min_commission" class="hidden text-xs text-red-700"></p>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="{{ $btnPrimary }}" id="pt-submit">{{ __('commissions.platform_form.add_tier') }}</button>
                    <button type="button" id="pt-cancel" class="hidden rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('commissions.rule_form.cancel') }}</button>
                    <span data-status role="status" class="text-xs"></span>
                </div>
            </form>
        @endif

        @if ($editor['tiers']->isEmpty())
            <p class="rounded-lg border border-dashed border-gray-300 px-4 py-5 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
        @else
            <div class="relative overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50"><tr>
                        <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.country') }}</th>
                        <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.platform_form.price_from') }}</th>
                        <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.platform_form.price_to') }}</th>
                        <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.rate_pct') }}</th>
                        <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.min') }}</th>
                        @if ($canEdit)<th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.rule_form.edit') }}</span></th>@endif
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($editor['tiers'] as $t)
                            <tr>
                                <td class="px-3 py-2 text-gray-800">{{ $t->country_id ? $countryName($countriesById->get($t->country_id)) : __('commissions.platform_form.global_tier') }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $t->price_from }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $t->price_to ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $pct($t->commission_rate) }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $t->min_commission }}</td>
                                @if ($canEdit)
                                    <td class="whitespace-nowrap px-3 py-2 text-end">
                                        @php
                                            $tierFill = [
                                                'action' => route('admin.categories.commission-tiers.update', [$category->id, $t->id]),
                                                'country_id' => $t->country_id,
                                                'price_from' => $t->price_from,
                                                'price_to' => $t->price_to,
                                                'commission_rate' => $t->commission_rate,
                                                'min_commission' => $t->min_commission,
                                            ];
                                        @endphp
                                        <button type="button" class="{{ $btnLink }}" data-fill-tier="{{ json_encode($tierFill) }}">{{ __('commissions.rule_form.edit') }}</button>
                                        <form method="POST" action="{{ route('admin.categories.commission-tiers.destroy', [$category->id, $t->id]) }}" data-ajax data-confirm="{{ __('commissions.platform_form.confirm_delete_tier') }}" class="ms-3 inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $btnDanger }}">{{ __('commissions.rule_form.delete') }}</button>
                                            <span data-status role="status" class="text-xs"></span>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($canEdit)
        <script>
        (() => {
            const overrideForm = document.getElementById('pr-override-form');
            document.querySelectorAll('[data-fill-override]').forEach((btn) => btn.addEventListener('click', () => {
                const d = JSON.parse(btn.dataset.fillOverride);
                Object.entries(d).forEach(([k, v]) => { const el = overrideForm.elements[k]; if (el) el.value = v ?? ''; });
                overrideForm.querySelector('[name=country_id]').focus();
            }));

            const tierForm = document.getElementById('pr-tier-form');
            const createAction = tierForm.action;
            const reset = () => {
                tierForm.reset(); tierForm.action = createAction; document.getElementById('pt-method').value = 'POST';
                document.getElementById('pt-submit').textContent = @json(__('commissions.platform_form.add_tier'));
                document.getElementById('pt-cancel').classList.add('hidden');
            };
            document.querySelectorAll('[data-fill-tier]').forEach((btn) => btn.addEventListener('click', () => {
                const d = JSON.parse(btn.dataset.fillTier);
                tierForm.action = d.action; document.getElementById('pt-method').value = 'PUT';
                ['country_id', 'price_from', 'price_to', 'commission_rate', 'min_commission'].forEach((k) => { tierForm.elements[k].value = d[k] ?? ''; });
                document.getElementById('pt-submit').textContent = @json(__('commissions.rule_form.save'));
                document.getElementById('pt-cancel').classList.remove('hidden');
                tierForm.elements.price_from.focus();
            }));
            document.getElementById('pt-cancel').addEventListener('click', reset);
        })();
        </script>
    @endif
@else
    <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.platform_form.pick_category') }}</p>
@endif
