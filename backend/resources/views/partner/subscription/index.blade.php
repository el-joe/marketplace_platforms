@extends('layouts.partner')

@section('title', __('partner.nav.subscription'))

@section('content')

<div class="max-w-5xl mx-auto py-6 px-4 space-y-8" dir="{{ session('locale', 'ar') === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- ─── Page Header ─────────────────────────────────────────────────────── --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.subscriptions.page_title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('partner.subscriptions.page_subtitle') }}</p>
    </div>

    {{-- ─── Current Subscription Card ──────────────────────────────────────── --}}
    @if($activeSub)
    <div class="bg-gradient-to-l from-indigo-50 to-white rounded-2xl border border-indigo-100 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center text-2xl">⭐</div>
            <div>
                <p class="text-xs text-indigo-400 uppercase tracking-wide mb-0.5">{{ __('partner.subscriptions.current_plan') }}</p>
                <p class="text-lg font-extrabold text-gray-900">{{ session('locale', 'ar') === 'ar' ? $activePlan->name_ar : $activePlan->name_en }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ __('partner.subscriptions.valid_until', ['date' => $activeSub->period_end?->format('d M Y'), 'days' => $activeSub->daysRemaining()]) }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="badge badge-{{ $activeSub->statusColor() }} text-sm px-3 py-1">{{ ucfirst($activeSub->status->value) }}</span>
            <div class="text-xs text-gray-500 bg-white rounded-lg border px-3 py-2 text-center">
                <p class="font-bold text-gray-800 text-base">
                    {{ $activeSub->listings_used }} / {{ $activePlan->hasUnlimitedListings() ? '∞' : $activePlan->max_listings }}
                </p>
                <p>{{ __('partner.subscriptions.listings_used') }}</p>
            </div>
            <button type="button" id="btn-cancel-sub" class="btn btn-ghost btn-xs text-red-500 border-red-200">
                {{ __('partner.subscriptions.cancel_subscription') }}
            </button>
        </div>
    </div>
    @else
    <div class="bg-gray-50 rounded-2xl border border-dashed border-gray-200 p-8 text-center">
        <p class="text-3xl mb-2">📦</p>
        <p class="text-gray-600 font-semibold">{{ __('partner.subscriptions.no_active_subscription') }}</p>
        <p class="text-sm text-gray-400 mt-1">{{ __('partner.subscriptions.no_active_subscription_hint') }}</p>
    </div>
    @endif

    {{-- ─── Plans Grid ──────────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-base font-bold text-gray-700 mb-4">{{ __('partner.subscriptions.available_plans') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach($plans as $plan)
                @php
                    $isCurrent = $activePlan && $activePlan->id === $plan->id;
                    $tierColors = [
                        'bronze'   => 'border-orange-300 hover:border-orange-400',
                        'silver'   => 'border-gray-300 hover:border-gray-400',
                        'gold'     => 'border-yellow-400 hover:border-yellow-500',
                        'platinum' => 'border-purple-400 hover:border-purple-500',
                    ];
                    $tc = $tierColors[strtolower($plan->name_en)] ?? 'border-gray-200';
                    $featureLabels = [
                        'commission_discount'     => __('partner.subscriptions.feature_commission_discount'),
                        'free_shipping'           => __('partner.subscriptions.feature_free_shipping'),
                        'priority_support'        => __('partner.subscriptions.feature_priority_support'),
                        'dedicated_manager'       => __('partner.subscriptions.feature_dedicated_manager'),
                        'early_flash_sale_access' => __('partner.subscriptions.feature_early_flash_sale_access'),
                    ];
                @endphp
                <div class="relative bg-white rounded-2xl border-2 {{ $isCurrent ? 'border-indigo-500 ring-2 ring-indigo-200' : $tc }} transition-all p-5 flex flex-col">
                    @if($isCurrent)
                        <div class="absolute -top-3 right-4 bg-indigo-500 text-white text-xs font-bold px-3 py-0.5 rounded-full shadow">
                            {{ __('partner.subscriptions.current_plan') }}
                        </div>
                    @endif

                    <p class="text-base font-extrabold text-gray-900 mb-0.5">{{ session('locale', 'ar') === 'ar' ? $plan->name_ar : $plan->name_en }}</p>
                    <p class="text-xs text-gray-400 mb-3">{{ session('locale', 'ar') === 'ar' ? $plan->description_ar : $plan->description_en }}</p>

                    <p class="text-2xl font-extrabold text-gray-900 mb-1">
                        {{ number_format($plan->price ) }}
                        <span class="text-sm font-medium text-gray-400">{{ $plan->currency }}{{ __('partner.subscriptions.per_month') }}</span>
                    </p>

                    <ul class="space-y-1.5 text-xs text-gray-600 mb-5 flex-1">
                        <li class="flex items-center gap-1.5">
                            <span class="text-indigo-400">📦</span>
                            {{ $plan->hasUnlimitedListings() ? __('partner.subscriptions.unlimited_listings') : __('partner.subscriptions.up_to_listings', ['count' => number_format($plan->max_listings)]) }}
                        </li>
                        @if($plan->commission_discount_pct > 0)
                        <li class="flex items-center gap-1.5">
                            <span class="text-green-400">💸</span>
                            {{ $plan->commission_discount_pct }}% {{ __('partner.subscriptions.commission_discount') }}
                        </li>
                        @endif
                        @if($plan->free_shipping_included)
                        <li class="flex items-center gap-1.5">
                            <span class="text-blue-400">🚚</span>
                            {{ __('partner.subscriptions.free_shipping_fbp') }}
                        </li>
                        @endif
                        @foreach(($plan->features ?? []) as $feature)
                            @if(!in_array($feature, ['commission_discount', 'free_shipping']))
                            <li class="flex items-center gap-1.5">
                                <span class="text-gray-300">✓</span>
                                {{ $featureLabels[$feature] ?? ucwords(str_replace('_', ' ', $feature)) }}
                            </li>
                            @endif
                        @endforeach
                    </ul>

                    @if($isCurrent)
                        <button type="button" disabled class="btn btn-sm w-full bg-indigo-50 text-indigo-400 border border-indigo-100 cursor-default">
                            {{ __('partner.subscriptions.currently_subscribed') }}
                        </button>
                    @else
                        <button type="button" class="btn btn-primary btn-sm w-full btn-subscribe" data-plan-id="{{ $plan->id }}" data-plan-name="{{ session('locale', 'ar') === 'ar' ? $plan->name_ar : $plan->name_en }}">
                            {{ $activeSub ? __('partner.subscriptions.upgrade_to_plan') : __('partner.subscriptions.subscribe_now') }}
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ─── Invoice History ─────────────────────────────────────────────────── --}}
    @if($invoices->isNotEmpty())
    <div>
        <h2 class="text-base font-bold text-gray-700 mb-4">{{ __('partner.subscriptions.invoice_history') }}</h2>
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-right">{{ __('partner.subscriptions.invoice_number') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.subscriptions.amount') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('common.status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.subscriptions.period') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.subscriptions.payment_date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($invoices as $invoice)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-700 text-right">{{ $invoice->invoice_number }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900 text-right">{{ $invoice->amountFormatted() }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="badge badge-{{ $invoice->statusColor() }} text-xs">{{ ucfirst($invoice->status->value) }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 text-right">
                            {{ $invoice->period_start?->format('d M') }} → {{ $invoice->period_end?->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 text-right">
                            {{ $invoice->paid_at?->format('d M Y') ?? '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

{{-- ─── Cancel Modal ────────────────────────────────────────────────────────── --}}
<x-modal id="cancel-modal" size="sm" title="{{ __('partner.subscriptions.cancel_subscription_title') }}">
    <p class="text-sm text-gray-500 mb-3">
        {{ __('partner.subscriptions.cancel_confirm_text') }}
    </p>
    <div>
        <label class="label-sm">{{ __('partner.subscriptions.cancel_reason_optional') }}</label>
        <textarea id="cancel-reason" rows="3" class="form-input w-full text-sm" placeholder="{{ __('partner.subscriptions.cancel_reason_placeholder') }}"></textarea>
    </div>
    <x-slot name="footer">
        <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('partner.subscriptions.go_back') }}</button>
        <button type="button" id="cancel-modal-confirm" class="btn btn-danger btn-sm">{{ __('partner.subscriptions.confirm_cancellation') }}</button>
    </x-slot>
</x-modal>

{{-- ─── Subscribe Confirm Modal ────────────────────────────────────────────── --}}
<x-modal id="subscribe-confirm-modal" size="sm" title="{{ __('partner.subscriptions.confirm_subscription_title') }}">
    <div class="text-center">
        <div class="text-5xl mb-3">🌟</div>
        <p class="text-sm text-gray-500 mb-1">{{ __('partner.subscriptions.confirm_subscription_prefix') }} <strong id="sc-plan-name"></strong> {{ __('partner.subscriptions.confirm_subscription_suffix') }}</p>
        <input type="hidden" id="sc-plan-id">
    </div>
    <x-slot name="footer">
        <button type="button" data-modal-close class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
        <button type="button" id="sc-confirm" class="btn btn-primary btn-sm px-8">{{ __('partner.subscriptions.confirm') }}</button>
    </x-slot>
</x-modal>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tok = '{{ csrf_token() }}';
    const genericError = @json(__('partner.subscriptions.generic_error'));

    // ── Subscribe ──────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-subscribe', function () {
        $('#sc-plan-id').val($(this).data('plan-id'));
        $('#sc-plan-name').text($(this).data('plan-name'));
        $('#subscribe-confirm-modal').modal('open');
    });
    $('#sc-confirm').on('click', function () {
        fetch('{{ route('partner.subscription.subscribe') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: $('#sc-plan-id').val() }),
        }).then(r => r.json()).then(data => {
            if (data.success) { window.Toast.success(data.message); location.reload(); }
            else { window.Toast.error(data.message ?? genericError); }
        });
    });

    // ── Cancel ─────────────────────────────────────────────────────────────────
    $('#btn-cancel-sub').on('click', () => {
        $('#cancel-reason').val('');
        $('#cancel-modal').modal('open');
    });
    $('#cancel-modal-confirm').on('click', function () {
        fetch('{{ route('partner.subscription.cancel') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': tok, 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason: $('#cancel-reason').val() }),
        }).then(r => r.json()).then(data => {
            if (data.success) { window.Toast.success(data.message); location.reload(); }
            else { window.Toast.error(data.message ?? genericError); }
        });
    });
});
</script>
@endpush
