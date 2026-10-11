@php
    $canEdit = auth('admin')->user()->can('vendors.edit');
@endphp

@if ($canEdit)
    <form method="POST" id="acq-assign-form" action="#" data-ajax class="my-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
        @csrf
        <h3 class="mb-1 text-sm font-semibold text-gray-900">{{ __('commissions.acquisition_form.heading_create') }}</h3>
        <p class="mb-3 text-xs text-gray-500">{{ __('commissions.acquisition_form.help') }}</p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="acq-vendor" class="{{ $label }}">{{ __('commissions.columns.vendor') }}</label>
                <select id="acq-vendor" required class="{{ $input }}">
                    <option value="" data-action="#">—</option>
                    @foreach ($editor['vendors'] as $v)
                        <option value="{{ $v->id }}" data-action="{{ route('admin.commissions.vendors.acquisition.store', $v->id) }}">{{ $v->store_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="acq-agent" class="{{ $label }}">{{ __('commissions.columns.agent') }}</label>
                <select id="acq-agent" name="admin_id" required class="{{ $input }}">
                    <option value="">—</option>
                    @foreach ($editor['agents'] as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->email }})</option>
                    @endforeach
                </select>
                <p data-error-for="admin_id" class="hidden text-xs text-red-700"></p>
            </div>
            <div>
                <label for="acq-rate" class="{{ $label }}">{{ __('commissions.acquisition_form.rate_bps') }}</label>
                <input id="acq-rate" type="number" name="commission_rate" min="1" max="10000" step="1" required class="{{ $input }}">
                <p data-error-for="commission_rate" class="hidden text-xs text-red-700"></p>
            </div>
            <div>
                <label for="acq-duration" class="{{ $label }}">{{ __('commissions.acquisition_form.duration') }}</label>
                <input id="acq-duration" type="number" name="duration_months" min="1" max="60" step="1" required class="{{ $input }}">
                <p data-error-for="duration_months" class="hidden text-xs text-red-700"></p>
            </div>
            <div>
                <label for="acq-min" class="{{ $label }}">{{ __('commissions.columns.min_sales') }}</label>
                <input id="acq-min" type="number" name="monthly_min_sales" min="0" step="1" required class="{{ $input }}">
                <p data-error-for="monthly_min_sales" class="hidden text-xs text-red-700"></p>
            </div>
            <div>
                <label for="acq-max" class="{{ $label }}">{{ __('commissions.columns.max_sales') }}</label>
                <input id="acq-max" type="number" name="monthly_max_sales" min="0" step="1" required class="{{ $input }}">
                <p data-error-for="monthly_max_sales" class="hidden text-xs text-red-700"></p>
            </div>
            <div class="sm:col-span-2">
                <label for="acq-notes" class="{{ $label }}">{{ __('commissions.columns.notes') }}</label>
                <input id="acq-notes" type="text" name="notes" maxlength="2000" class="{{ $input }}">
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">{{ __('commissions.notices.acquisition_bps') }}</p>
        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.acquisition_form.assign') }}</button>
            <span data-status role="status" class="text-xs"></span>
        </div>
    </form>
    <script>
    (() => {
        const form = document.getElementById('acq-assign-form');
        const vendor = document.getElementById('acq-vendor');
        vendor.addEventListener('change', () => { form.action = vendor.selectedOptions[0].dataset.action || '#'; });
        form.addEventListener('submit', (e) => { if (form.action.endsWith('#')) { e.stopImmediatePropagation(); e.preventDefault(); vendor.focus(); } }, true);
    })();
    </script>
@else
    <p class="my-4 text-xs text-gray-500">{{ __('commissions.vendor_form.no_edit_permission') }}</p>
@endif

<div class="mt-5">
    <div class="mb-2 flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900">{{ __('commissions.tables.acquisition_table') }}</h3>
        @if ($editor['deals']->total() > $editor['deals']->count())
            <span class="text-xs text-gray-500">{{ __('commissions.showing', ['shown' => $editor['deals']->count(), 'total' => $editor['deals']->total()]) }}</span>
        @endif
    </div>

    @if ($editor['deals']->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
    @else
        <div class="relative overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50"><tr>
                    @foreach (['vendor', 'agent', 'rate_bps', 'min_sales', 'max_sales', 'valid_until', 'status', 'total_earned'] as $column)
                        <th scope="col" class="whitespace-nowrap px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.'.$column) }}</th>
                    @endforeach
                    <th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.campaign_form.actions') }}</span></th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($editor['deals'] as $deal)
                        @php $active = $deal->status === 'active'; @endphp
                        <tr>
                            <td class="px-3 py-2 text-gray-800">{{ $deal->vendor?->store_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $deal->admin?->name ?? '—' }}</td>
                            @if ($active && $canEdit)
                                <td class="px-3 py-2" colspan="3">
                                    <form method="POST" action="{{ route('admin.commissions.vendors.acquisition.update', [$deal->vendor_id, $deal->id]) }}" data-ajax class="flex flex-wrap items-end gap-2">
                                        @csrf @method('PUT')
                                        <div>
                                            <label for="ad-{{ $deal->id }}-r" class="{{ $label }}">{{ __('commissions.columns.rate_bps') }}</label>
                                            <input id="ad-{{ $deal->id }}-r" type="number" name="commission_rate" min="1" max="10000" required value="{{ $deal->commission_rate }}" class="{{ $input }} w-24">
                                            <p data-error-for="commission_rate" class="hidden text-xs text-red-700"></p>
                                        </div>
                                        <div>
                                            <label for="ad-{{ $deal->id }}-a" class="{{ $label }}">{{ __('commissions.columns.min_sales') }}</label>
                                            <input id="ad-{{ $deal->id }}-a" type="number" name="monthly_min_sales" min="0" required value="{{ $deal->monthly_min_sales }}" class="{{ $input }} w-24">
                                            <p data-error-for="monthly_min_sales" class="hidden text-xs text-red-700"></p>
                                        </div>
                                        <div>
                                            <label for="ad-{{ $deal->id }}-b" class="{{ $label }}">{{ __('commissions.columns.max_sales') }}</label>
                                            <input id="ad-{{ $deal->id }}-b" type="number" name="monthly_max_sales" min="0" required value="{{ $deal->monthly_max_sales }}" class="{{ $input }} w-24">
                                            <p data-error-for="monthly_max_sales" class="hidden text-xs text-red-700"></p>
                                        </div>
                                        <input type="hidden" name="notes" value="{{ $deal->notes }}">
                                        <button type="submit" class="{{ $btnPrimary }} py-1.5">{{ __('commissions.campaign_form.save_amounts') }}</button>
                                        <span data-status role="status" class="text-xs"></span>
                                    </form>
                                </td>
                            @else
                                <td class="px-3 py-2 text-gray-800">{{ $deal->commission_rate }} bps</td>
                                <td class="px-3 py-2 text-gray-800">{{ $deal->monthly_min_sales }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $deal->monthly_max_sales }}</td>
                            @endif
                            <td class="px-3 py-2 text-gray-800">{{ $deal->valid_until?->toDateString() ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $deal->status }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $deal->total_earned }} {{ $deal->currency }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-end">
                                @if ($active && $canEdit)
                                    <form method="POST" action="{{ route('admin.commissions.vendors.acquisition.revoke', [$deal->vendor_id, $deal->id]) }}" data-ajax data-confirm="{{ __('commissions.acquisition_form.confirm_revoke') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="{{ $btnDanger }}">{{ __('commissions.acquisition_form.revoke') }}</button>
                                        <span data-status role="status" class="text-xs"></span>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $editor['deals']->links() }}</div>
    @endif
</div>
