@php
    $admin = auth('admin')->user();
    $canApprove = $admin->can('marketer_campaigns.approve');
    $canReject = $admin->can('marketer_campaigns.reject');
    $enumValue = fn ($v) => $v instanceof \BackedEnum ? $v->value : (string) $v;
@endphp

<p class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" role="note">{{ __('commissions.campaign_form.scope_note') }}</p>

<div class="mt-5">
    <div class="mb-2 flex items-baseline justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-900">{{ __('commissions.tables.campaigns_table') }}</h3>
        @if ($editor['campaigns']->total() > $editor['campaigns']->count())
            <span class="text-xs text-gray-500">{{ __('commissions.showing', ['shown' => $editor['campaigns']->count(), 'total' => $editor['campaigns']->total()]) }}</span>
        @endif
    </div>

    @if ($editor['campaigns']->isEmpty())
        <p class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">{{ __('commissions.empty') }}</p>
    @else
        <div class="relative overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach (['campaign', 'type', 'marketer_amount', 'platform_amount', 'budget', 'spent', 'status'] as $column)
                            <th scope="col" class="whitespace-nowrap px-3 py-2 text-start font-medium text-gray-500">{{ __('commissions.columns.'.$column) }}</th>
                        @endforeach
                        <th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('commissions.campaign_form.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($editor['campaigns'] as $campaign)
                        @php $pending = $enumValue($campaign->status) === 'pending_admin'; @endphp
                        <tr>
                            <td class="px-3 py-2 text-gray-800"><a class="{{ $btnLink }}" href="{{ route('admin.marketer-campaigns.show', $campaign->id) }}">{{ $campaign->title ?: $campaign->id }}</a></td>
                            <td class="px-3 py-2 text-gray-800">{{ $enumValue($campaign->commission_type) }}</td>
                            @if ($pending && $canApprove)
                                <td class="px-3 py-2" colspan="2">
                                    <form method="POST" action="{{ route('admin.marketer-campaigns.commission.update', $campaign->id) }}" data-ajax class="flex flex-wrap items-end gap-2">
                                        @csrf @method('PATCH')
                                        <div>
                                            <label for="mc-{{ $campaign->id }}-m" class="{{ $label }}">{{ __('commissions.columns.marketer_amount') }}</label>
                                            <input id="mc-{{ $campaign->id }}-m" type="number" name="marketer_commission_amount" min="1" step="1" required value="{{ $campaign->marketer_commission_amount }}" class="{{ $input }} w-28">
                                            <p data-error-for="marketer_commission_amount" class="hidden text-xs text-red-700"></p>
                                        </div>
                                        <div>
                                            <label for="mc-{{ $campaign->id }}-p" class="{{ $label }}">{{ __('commissions.columns.platform_amount') }}</label>
                                            <input id="mc-{{ $campaign->id }}-p" type="number" name="platform_commission_amount" min="0" step="1" required value="{{ $campaign->platform_commission_amount }}" class="{{ $input }} w-28">
                                            <p data-error-for="platform_commission_amount" class="hidden text-xs text-red-700"></p>
                                        </div>
                                        <button type="submit" class="{{ $btnPrimary }} py-1.5">{{ __('commissions.campaign_form.save_amounts') }}</button>
                                        <span data-status role="status" class="text-xs"></span>
                                    </form>
                                </td>
                            @else
                                <td class="px-3 py-2 text-gray-800">{{ $campaign->marketer_commission_amount }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $campaign->platform_commission_amount }}</td>
                            @endif
                            <td class="px-3 py-2 text-gray-800">{{ $campaign->max_commission_budget ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $campaign->commission_budget_spent }}</td>
                            <td class="px-3 py-2 text-gray-800">{{ $enumValue($campaign->status) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-end">
                                @if ($pending && $canApprove)
                                    <form method="POST" action="{{ route('admin.marketer-campaigns.approve', $campaign->id) }}" class="inline" data-confirm="{{ __('commissions.campaign_form.confirm_approve') }}">
                                        @csrf
                                        <button type="submit" class="{{ $btnLink }}">{{ __('commissions.campaign_form.approve') }}</button>
                                    </form>
                                @endif
                                @if ($pending && $canReject)
                                    <form method="POST" action="{{ route('admin.marketer-campaigns.reject', $campaign->id) }}" class="ms-3 inline-flex items-center gap-1" data-confirm="{{ __('commissions.campaign_form.confirm_reject') }}">
                                        @csrf
                                        <label for="mc-{{ $campaign->id }}-r" class="sr-only">{{ __('commissions.campaign_form.rejection_reason') }}</label>
                                        <input id="mc-{{ $campaign->id }}-r" type="text" name="rejection_reason" required maxlength="500" placeholder="{{ __('commissions.campaign_form.rejection_reason') }}" class="{{ $input }} mt-0 w-40 py-1">
                                        <button type="submit" class="{{ $btnDanger }}">{{ __('commissions.campaign_form.reject') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $editor['campaigns']->links() }}</div>
    @endif
</div>
