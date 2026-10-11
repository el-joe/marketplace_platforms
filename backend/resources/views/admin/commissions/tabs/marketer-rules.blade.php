@php
    $canManage = auth('admin')->user()->can('marketers.manage');
    $editing = $editor['editing'];
    $scopeValue = old('scope', $editing?->scope ?? 'products');
    $marketerValue = old('marketer_id', $editing?->marketer_id);
    $categoryValue = old('category_id', $editing?->category_id);
    $excludedValue = old('excluded_category_ids', $editing?->excluded_category_ids ?? []);
    $val = fn (string $name, $fallback = null) => old($name, $editing?->{$name} ?? $fallback);
    $categoryName = fn ($rule) => $rule->category_id ? ($rule->category?->name_en ?: $rule->category?->name_ar ?: '—') : __('commissions.rule_form.any_category');
@endphp

@if ($canManage)
    <form method="POST" id="marketer-rule-form" action="{{ $marketerValue ? route('admin.marketers.category-commissions.store', $marketerValue) : '#' }}"
          class="my-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
        @csrf
        <h3 class="mb-1 text-sm font-semibold text-gray-900">{{ $editing ? __('commissions.marketer_form.heading_edit') : __('commissions.marketer_form.heading_create') }}</h3>
        <p class="mb-3 text-xs text-gray-500">{{ __('commissions.marketer_form.help') }}</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="mr-marketer" class="{{ $label }}">{{ __('commissions.columns.marketer') }}</label>
                <select id="mr-marketer" name="marketer_id" required @disabled($editing) class="{{ $input }}">
                    <option value="" data-action="#">—</option>
                    @foreach ($editor['marketers'] as $marketer)
                        <option value="{{ $marketer->id }}" data-action="{{ route('admin.marketers.category-commissions.store', $marketer->id) }}" @selected($marketerValue === $marketer->id)>{{ $marketer->name }} ({{ $marketer->email }})</option>
                    @endforeach
                </select>
                @if ($editing)<input type="hidden" name="marketer_id" value="{{ $editing->marketer_id }}">@endif
            </div>
            <div>
                <label for="mr-scope" class="{{ $label }}">{{ __('commissions.columns.scope') }}</label>
                <select id="mr-scope" name="scope" class="{{ $input }}">
                    <option value="products" @selected($scopeValue === 'products')>{{ __('commissions.marketer_form.scope_products') }}</option>
                    <option value="open_market" @selected($scopeValue === 'open_market')>{{ __('commissions.marketer_form.scope_open_market') }}</option>
                    <option value="travel" @selected($scopeValue === 'travel')>{{ __('commissions.marketer_form.scope_travel') }}</option>
                </select>
            </div>
            <div>
                <label for="mr-category" class="{{ $label }}">{{ __('commissions.columns.category') }}</label>
                <select id="mr-category" name="category_id" class="{{ $input }}"></select>
            </div>
            <div>
                <label for="mr-excluded" class="{{ $label }}">{{ __('commissions.columns.excluded') }}</label>
                <select id="mr-excluded" name="excluded_category_ids[]" multiple size="3" class="{{ $input }}"></select>
                <p class="mt-1 text-xs text-gray-400">{{ __('commissions.marketer_form.excluded_help') }}</p>
            </div>
            <div>
                <label for="mr-rate" class="{{ $label }}">{{ __('commissions.marketer_form.rate') }}</label>
                <input id="mr-rate" type="number" name="commission_rate" min="0" max="100" step="0.01" value="{{ $val('commission_rate') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="mr-flat" class="{{ $label }}">{{ __('commissions.marketer_form.flat') }}</label>
                <input id="mr-flat" type="number" name="commission_flat_amount" min="0" step="1" value="{{ $val('commission_flat_amount') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="mr-min" class="{{ $label }}">{{ __('commissions.marketer_form.min') }}</label>
                <input id="mr-min" type="number" name="commission_min_amount" min="0" step="1" value="{{ $val('commission_min_amount') }}" class="{{ $input }}">
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">{{ __('commissions.marketer_form.mode_help') }}</p>
        <p id="mr-travel-warning" class="mt-2 hidden rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ __('commissions.notices.marketer_rules_travel_unconsumed') }}</p>

        <div class="mt-4 flex gap-2">
            <button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.rule_form.save') }}</button>
            @if ($editing)
                <a href="{{ route('admin.commissions.index', ['tab' => 'marketer-rules']) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('commissions.rule_form.cancel') }}</a>
            @endif
        </div>
    </form>

    <script>
    (() => {
        const form = document.getElementById('marketer-rule-form');
        const byScope = @json($editor['categoriesByScope']);
        const state = { category: @json($categoryValue), excluded: @json($excludedValue) };
        const marketer = form.querySelector('#mr-marketer');
        const scope = form.querySelector('#mr-scope');
        const category = form.querySelector('#mr-category');
        const excluded = form.querySelector('#mr-excluded');
        const anyCategory = @json(__('commissions.rule_form.any_category'));
        const fill = () => {
            const list = byScope[scope.value] || [];
            category.innerHTML = '<option value="">' + anyCategory + '</option>' + list.map((c) => `<option value="${c.id}">${c.name.replace(/</g, '&lt;')}</option>`).join('');
            excluded.innerHTML = list.map((c) => `<option value="${c.id}">${c.name.replace(/</g, '&lt;')}</option>`).join('');
            if (state.category && list.some((c) => c.id === state.category)) category.value = state.category;
            Array.from(excluded.options).forEach((o) => { o.selected = (state.excluded || []).includes(o.value); });
            excluded.disabled = category.value !== '';
            document.getElementById('mr-travel-warning').classList.toggle('hidden', scope.value !== 'travel');
        };
        scope.addEventListener('change', () => { state.category = null; state.excluded = []; fill(); });
        category.addEventListener('change', () => { state.category = category.value; excluded.disabled = category.value !== ''; });
        marketer.addEventListener('change', () => { form.action = marketer.selectedOptions[0].dataset.action || '#'; });
        form.addEventListener('submit', (e) => { if (form.action.endsWith('#')) { e.preventDefault(); marketer.focus(); } });
        fill();
    })();
    </script>
@else
    <p class="my-4 text-xs text-gray-500">{{ __('commissions.rule_form.read_only') }}</p>
@endif

<div class="mt-5">
    <div class="mb-2 flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900">{{ __('commissions.tables.marketer_rules_table') }}</h3>
        @if ($editor['rules']->total() > $editor['rules']->count())
            <span class="text-xs text-gray-500">{{ __('commissions.showing', ['shown' => $editor['rules']->count(), 'total' => $editor['rules']->total()]) }}</span>
        @endif
    </div>

    @if ($editor['rules']->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
    @else
        <div class="relative overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach (['marketer', 'scope', 'category', 'mode', 'rate_pct', 'flat', 'min', 'excluded'] as $column)
                            <th scope="col" class="whitespace-nowrap px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.'.$column) }}</th>
                        @endforeach
                        @if ($canManage)<th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.rule_form.edit') }}</span></th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($editor['rules'] as $rule)
                        <tr>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->marketer?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->scope }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $categoryName($rule) }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->commission_mode instanceof \BackedEnum ? $rule->commission_mode->value : $rule->commission_mode }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ rtrim(rtrim(number_format((float) $rule->commission_rate, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->commission_flat_amount ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->commission_min_amount ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ count($rule->excluded_category_ids ?? []) }}</td>
                            @if ($canManage)
                                <td class="whitespace-nowrap px-3 py-2 text-end">
                                    <a href="{{ route('admin.commissions.index', ['tab' => 'marketer-rules', 'edit' => $rule->id]) }}" class="{{ $btnLink }}">{{ __('commissions.rule_form.edit') }}</a>
                                    <form method="POST" action="{{ route('admin.marketers.category-commissions.destroy', [$rule->marketer_id, $rule->id]) }}" class="ms-3 inline"
                                          data-confirm="{{ __('commissions.marketer_form.confirm_delete') }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="{{ $btnDanger }}">{{ __('commissions.rule_form.delete') }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $editor['rules']->links() }}</div>
    @endif
</div>
