@php
    $canEdit = auth('admin')->user()->can('vendors.edit');
    $vendor = $editor['vendor'];
    $withOverride = $editor['vendors']->filter(fn ($v) => (float) $v->commission_rate > 0);
@endphp

@include('admin.commissions.partials.vendor-picker')

@if ($vendor)
    @if ($canEdit)
        <form method="POST" action="{{ route('admin.commissions.vendors.commission.update', $vendor->id) }}" data-ajax class="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
            @csrf @method('PATCH')
            <input type="hidden" name="section" value="flat_rate">
            <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ $vendor->store_name }}</h3>
            <div class="max-w-xs">
                <label for="vf-rate" class="{{ $label }}">{{ __('commissions.vendor_form.flat_rate') }}</label>
                <input id="vf-rate" type="number" name="commission_rate" min="0" max="100" step="0.01" value="{{ $vendor->commission_rate }}" class="{{ $input }}" aria-describedby="vf-rate-help">
                <p id="vf-rate-help" class="mt-1 text-xs text-gray-500">{{ __('commissions.vendor_form.flat_rate_help') }}</p>
                <p data-error-for="commission_rate" class="hidden text-xs text-red-700"></p>
            </div>
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
    <h3 class="mb-2 text-sm font-semibold text-gray-900">{{ __('commissions.tables.vendor_flat_table') }}</h3>
    @if ($withOverride->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
    @else
        <div class="relative overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50"><tr>
                    <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.vendor') }}</th>
                    <th scope="col" class="px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.rate_pct') }}</th>
                    <th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.rule_form.edit') }}</span></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($withOverride as $v)
                        <tr>
                            <td class="px-3 py-2 text-gray-800">{{ $v->store_name }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ rtrim(rtrim(number_format((float) $v->commission_rate, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="px-3 py-2 text-end"><a class="{{ $btnLink }}" href="{{ route('admin.commissions.index', ['tab' => $activeKey, 'vendor' => $v->id]) }}">{{ __('commissions.rule_form.edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
