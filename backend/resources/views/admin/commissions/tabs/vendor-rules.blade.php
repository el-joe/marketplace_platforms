@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500';
    $isEditing = $editing !== null;
    $field = fn (string $name, $fallback = null) => old($name, $isEditing ? ($editing->{$name} instanceof \BackedEnum ? $editing->{$name}->value : $editing->{$name}) : $fallback);
    $dateField = fn (string $name) => old($name, $isEditing ? $editing->{$name}?->toDateString() : ($name === 'effective_from' ? now()->toDateString() : null));
@endphp

@if ($canManageRules)
    <form method="POST"
          action="{{ $isEditing ? route('admin.commissions.vendor-rules.update', $editing) : route('admin.commissions.vendor-rules.store') }}"
          class="my-5 rounded-lg border border-gray-200 bg-gray-50 p-4"
          onsubmit="const b = this.querySelector('[type=submit]'); b.disabled = true; b.textContent = @js(__('commissions.rule_form.saving'));">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <h3 class="mb-3 text-sm font-semibold text-gray-900">
            {{ $isEditing ? __('commissions.rule_form.heading_edit') : __('commissions.rule_form.heading_create') }}
        </h3>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="rule-vendor" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.vendor') }}</label>
                <select id="rule-vendor" name="vendor_id" class="{{ $input }}">
                    <option value="">{{ __('commissions.rule_form.any_vendor') }}</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}" @selected($field('vendor_id') === $vendor->id)>{{ $vendor->store_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rule-category" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.category') }}</label>
                <select id="rule-category" name="category_id" class="{{ $input }}">
                    <option value="">{{ __('commissions.rule_form.any_category') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($field('category_id') === $category->id)>{{ $category->name_en ?: $category->name_ar }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rule-rate" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.rate_pct') }}</label>
                <input id="rule-rate" type="number" name="rate_pct" min="0" max="100" step="0.01" required value="{{ $field('rate_pct') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="rule-type" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.rate_type') }}</label>
                <select id="rule-type" name="rate_type" class="{{ $input }}">
                    <option value="flat" selected>{{ __('commissions.rule_form.flat') }}</option>
                </select>
            </div>
            <div>
                <label for="rule-min" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.min') }}</label>
                <input id="rule-min" type="number" name="min_commission" min="0" step="1" value="{{ $field('min_commission', 0) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="rule-max" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.max') }}</label>
                <input id="rule-max" type="number" name="max_commission" min="0" step="1" value="{{ $field('max_commission') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="rule-from" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.effective_from') }}</label>
                <input id="rule-from" type="date" name="effective_from" required value="{{ $dateField('effective_from') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="rule-until" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.effective_until') }}</label>
                <input id="rule-until" type="date" name="effective_until" value="{{ $dateField('effective_until') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="rule-priority" class="block text-xs font-medium text-gray-600">{{ __('commissions.rule_form.priority') }}</label>
                <input id="rule-priority" type="number" name="priority" min="0" max="1000" step="1" value="{{ $field('priority', 0) }}" class="{{ $input }}">
            </div>
        </div>

        <div class="mt-4 flex items-start gap-2">
            <input id="rule-confirm" type="checkbox" name="confirm_platform_wide" value="1" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" @checked(old('confirm_platform_wide'))>
            <label for="rule-confirm" class="text-xs text-gray-600">{{ __('commissions.rule_form.confirm_platform_wide') }}</label>
        </div>

        <div class="mt-4 flex gap-2">
            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-60">
                {{ __('commissions.rule_form.save') }}
            </button>
            @if ($isEditing)
                <a href="{{ route('admin.commissions.index', ['tab' => 'vendor-rules']) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('commissions.rule_form.cancel') }}</a>
            @endif
        </div>
    </form>
@else
    <p class="my-4 text-xs text-gray-500">{{ __('commissions.rule_form.read_only') }}</p>
@endif

<div class="mt-5">
    <div class="mb-2 flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900">{{ __('commissions.tables.vendor_rules_table') }}</h3>
        @if ($rules->total() > $rules->count())
            <span class="text-xs text-gray-500">{{ __('commissions.showing', ['shown' => $rules->count(), 'total' => $rules->total()]) }}</span>
        @endif
    </div>

    @if ($rules->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
    @else
        <div class="relative overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach (['vendor', 'category', 'rate_pct', 'rate_type', 'min', 'max', 'effective_from', 'effective_until', 'priority'] as $column)
                            <th scope="col" class="whitespace-nowrap px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.'.$column) }}</th>
                        @endforeach
                        @if ($canManageRules)
                            <th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.rule_form.edit') }}</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rules as $rule)
                        <tr>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->vendor?->store_name ?? __('commissions.rule_form.any_vendor') }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->category ? ($rule->category->name_en ?: $rule->category->name_ar) : __('commissions.rule_form.any_category') }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ rtrim(rtrim(number_format((float) $rule->rate_pct, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->rate_type->value }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->min_commission }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->max_commission ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->effective_from?->toDateString() }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->effective_until?->toDateString() ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $rule->priority }}</td>
                            @if ($canManageRules)
                                <td class="whitespace-nowrap px-3 py-2 text-end">
                                    <a href="{{ route('admin.commissions.index', ['tab' => 'vendor-rules', 'edit' => $rule->id]) }}" class="text-primary-600 hover:underline">{{ __('commissions.rule_form.edit') }}</a>
                                    <form method="POST" action="{{ route('admin.commissions.vendor-rules.destroy', $rule) }}" class="ms-3 inline"
                                          onsubmit="return confirm(@js(__('commissions.rule_form.confirm_delete')));">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-danger-600 hover:underline">{{ __('commissions.rule_form.delete') }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $rules->links() }}</div>
    @endif
</div>
