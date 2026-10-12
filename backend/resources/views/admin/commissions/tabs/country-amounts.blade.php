@php
    $canEdit = auth('admin')->user()->can('marketer_commission_settings.edit');
    $nameOf = fn ($m) => $m ? ($m->name_en ?: $m->name_ar) : '—';
@endphp

@if ($canEdit)
    <form method="POST" id="ca-form" action="{{ old('category_id') ? route('admin.categories.marketer-commission.update', old('category_id')) : '#' }}" class="my-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
        @csrf
        <h3 class="mb-1 text-sm font-semibold text-gray-900">{{ __('commissions.amounts_form.heading') }}</h3>
        <p class="mb-3 text-xs text-gray-500">{{ __('commissions.amounts_form.help') }}</p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="ca-category" class="{{ $label }}">{{ __('commissions.columns.category') }}</label>
                <select id="ca-category" name="category_id" required class="{{ $input }}">
                    <option value="" data-action="#">—</option>
                    @foreach ($editor['categories'] as $c)
                        <option value="{{ $c->id }}" data-action="{{ route('admin.categories.marketer-commission.update', $c->id) }}" @selected(old('category_id') === $c->id)>{{ $nameOf($c) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ca-country" class="{{ $label }}">{{ __('commissions.columns.country') }}</label>
                <select id="ca-country" name="country_id" required class="{{ $input }}">
                    <option value="" data-currency="">—</option>
                    @foreach ($editor['countries'] as $country)
                        <option value="{{ $country->id }}" data-currency="{{ $country->currency_code }}" @selected(old('country_id') === $country->id)>{{ $nameOf($country) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ca-inf" class="{{ $label }}">{{ __('commissions.columns.influencer_amount') }}</label>
                <input id="ca-inf" type="number" name="influencer_commission_amount" min="0" step="0.01" required value="{{ old('influencer_commission_amount') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="ca-aff" class="{{ $label }}">{{ __('commissions.columns.affiliate_amount') }}</label>
                <input id="ca-aff" type="number" name="affiliate_commission_amount" min="0" step="0.01" required value="{{ old('affiliate_commission_amount') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="ca-cur" class="{{ $label }}">{{ __('commissions.columns.currency') }}</label>
                <input id="ca-cur" type="text" name="currency" maxlength="3" minlength="3" required readonly value="{{ old('currency') }}" class="{{ $input }} bg-gray-100 uppercase">
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">{{ __('commissions.notices.country_amounts_fallback') }}</p>
        <div class="mt-4"><button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.rule_form.save') }}</button></div>
    </form>
    <script>
    (() => {
        const form = document.getElementById('ca-form');
        const category = document.getElementById('ca-category');
        const country = document.getElementById('ca-country');
        const currency = document.getElementById('ca-cur');
        category.addEventListener('change', () => { form.action = category.selectedOptions[0].dataset.action || '#'; });
        country.addEventListener('change', () => { currency.value = country.selectedOptions[0].dataset.currency || ''; });
        form.addEventListener('submit', (e) => { if (form.action.endsWith('#')) { e.preventDefault(); category.focus(); } });
        document.querySelectorAll('[data-fill-amount]').forEach((btn) => btn.addEventListener('click', () => {
            const d = JSON.parse(btn.dataset.fillAmount);
            category.value = d.category_id; category.dispatchEvent(new Event('change'));
            country.value = d.country_id; country.dispatchEvent(new Event('change'));
            document.getElementById('ca-inf').value = d.influencer; document.getElementById('ca-aff').value = d.affiliate;
            category.focus();
        }));
    })();
    </script>
@else
    <p class="my-4 text-xs text-gray-500">{{ __('commissions.rule_form.read_only') }}</p>
@endif

<div class="mt-5">
    <div class="mb-2 flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900">{{ __('commissions.tables.country_amounts_table') }}</h3>
        @if ($editor['rows']->total() > $editor['rows']->count())
            <span class="text-xs text-gray-500">{{ __('commissions.showing', ['shown' => $editor['rows']->count(), 'total' => $editor['rows']->total()]) }}</span>
        @endif
    </div>
    @if ($editor['rows']->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
    @else
        <div class="relative overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50"><tr>
                    @foreach (['category', 'country', 'influencer_amount', 'affiliate_amount', 'currency'] as $column)
                        <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.'.$column) }}</th>
                    @endforeach
                    @if ($canEdit)<th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.rule_form.edit') }}</span></th>@endif
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($editor['rows'] as $row)
                        <tr>
                            <td class="px-3 py-2 text-gray-800">{{ $nameOf($row->category) }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $nameOf($row->country) }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $row->influencer_commission_amount }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $row->affiliate_commission_amount }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $row->currency }}</td>
                            @if ($canEdit)
                                <td class="px-3 py-2 text-end">@php $amountFill = ['category_id' => $row->category_id, 'country_id' => $row->country_id, 'influencer' => $row->influencer_commission_amount, 'affiliate' => $row->affiliate_commission_amount]; @endphp
                                    <button type="button" class="{{ $btnLink }}" data-fill-amount="{{ json_encode($amountFill) }}">{{ __('commissions.rule_form.edit') }}</button></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $editor['rows']->links() }}</div>
    @endif
</div>
