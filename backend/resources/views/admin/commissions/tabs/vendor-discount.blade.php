@php
    $canEdit = auth('admin')->user()->can('vendors.edit');
    $vendor = $editor['vendor'];
    $typeOf = fn ($v) => $v->commission_discount_type instanceof \BackedEnum ? $v->commission_discount_type->value : (string) $v->commission_discount_type;
    $withDiscount = $editor['vendors']->filter(fn ($v) => $typeOf($v) !== 'none');
@endphp

@include('admin.commissions.partials.vendor-picker')

@if ($vendor)
    @if ($canEdit)
        <form method="POST" action="{{ route('admin.commissions.vendors.commission.update', $vendor->id) }}" data-ajax class="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
            @csrf @method('PATCH')
            <input type="hidden" name="section" value="discount">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ $vendor->store_name }}</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="vd-type" class="{{ $label }}">{{ __('commissions.columns.discount_type') }}</label>
                    <select id="vd-type" name="commission_discount_type" class="{{ $input }}">
                        @foreach (['none', 'flat', 'percentage'] as $type)
                            <option value="{{ $type }}" @selected($typeOf($vendor) === $type)>{{ __('commissions.vendor_form.type_'.$type) }}</option>
                        @endforeach
                    </select>
                    <p data-error-for="commission_discount_type" class="hidden text-xs text-red-700"></p>
                </div>
                <div>
                    <label for="vd-flat" class="{{ $label }}">{{ __('commissions.vendor_form.discount_flat') }}</label>
                    <input id="vd-flat" type="number" name="commission_discount_flat" min="0" step="1" value="{{ $vendor->commission_discount_flat }}" class="{{ $input }}">
                    <p data-error-for="commission_discount_flat" class="hidden text-xs text-red-700"></p>
                </div>
                <div>
                    <label for="vd-pct" class="{{ $label }}">{{ __('commissions.vendor_form.discount_pct') }}</label>
                    <input id="vd-pct" type="number" name="commission_discount_percentage" min="0" max="100" step="0.01" value="{{ $vendor->commission_discount_percentage }}" class="{{ $input }}">
                    <p data-error-for="commission_discount_percentage" class="hidden text-xs text-red-700"></p>
                </div>
                <div>
                    <label for="vd-notes" class="{{ $label }}">{{ __('commissions.columns.notes') }}</label>
                    <input id="vd-notes" type="text" name="commission_discount_notes" maxlength="2000" value="{{ $vendor->commission_discount_notes }}" class="{{ $input }}">
                    <p data-error-for="commission_discount_notes" class="hidden text-xs text-red-700"></p>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">{{ __('commissions.vendor_form.discount_help') }}</p>
            <div class="mt-4 flex items-center gap-3">
                <button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.rule_form.save') }}</button>
                <span data-status role="status" class="text-xs"></span>
            </div>
        </form>
    @else
        <p class="mb-4 text-xs text-gray-500">{{ __('commissions.vendor_form.no_edit_permission') }}</p>
    @endif
@endif

<div class="mt-5">
    <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ __('commissions.tables.vendor_discount_table') }}</h3>
    @if ($withDiscount->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
    @else
        <div class="relative overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50"><tr>
                    @foreach (['vendor', 'discount_type', 'flat', 'pct', 'notes'] as $column)
                        <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.'.$column) }}</th>
                    @endforeach
                    <th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.rule_form.edit') }}</span></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($withDiscount as $v)
                        <tr>
                            <td class="px-3 py-2 text-gray-800">{{ $v->store_name }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $typeOf($v) }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $v->commission_discount_flat }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ rtrim(rtrim(number_format((float) $v->commission_discount_percentage, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="px-3 py-2 text-gray-800">{{ $v->commission_discount_notes ?: '—' }}</td>
                            <td class="px-3 py-2 text-end"><a class="{{ $btnLink }}" href="{{ route('admin.commissions.index', ['tab' => $activeKey, 'vendor' => $v->id]) }}">{{ __('commissions.rule_form.edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
