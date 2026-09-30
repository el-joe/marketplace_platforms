@extends('layouts.marketer')
@section('title', $slot->name)
@section('page-title', $slot->name)

@push('scripts')
    <script>
        window.AD_SLOT_CONFIG = {
            slot: {!! json_encode([
                'id' => $slot->id,
                'name' => $slot->name,
                'currency' => $slot->country?->currency_code,
                'pricing_model' => $slot->pricing_model->value,
                'min_booking_days' => $slot->min_booking_days,
                'max_booking_days' => $slot->max_booking_days,
                'min_budget' => $slot->min_budget,
                'creative_spec' => $slot->creativeSpec(),
            ]) !!},
            calendarUrl: "{{ route('marketer.promote.calendar', $slot->id) }}",
            quoteUrl: "{{ route('marketer.promote.quote', $slot->id) }}",
            destinationsUrl: "{{ route('marketer.promote.destinations') }}",
            walletBalanceUrl: "{{ route('marketer.promote.wallet-balance') }}",
            storeBookingUrl: "{{ route('marketer.promote.bookings.store') }}",
            uploadCreativeUrlTemplate: "{{ route('marketer.promote.bookings.creative', ['booking' => '__ID__']) }}",
            submitUrlTemplate: "{{ route('marketer.promote.bookings.submit', ['booking' => '__ID__']) }}",
            bookingShowUrlTemplate: "{{ route('marketer.promote.bookings.show', ['booking' => '__ID__']) }}",
        };
    </script>
    @vite('resources/js/marketer/promote-wizard.js')
@endpush

@section('content')
<div x-data="promoteWizard()" x-init="init()" class="bg-white rounded-2xl border border-gray-200 p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-gray-900">{{ __('marketer.promote.wizard_heading') }}</h2>
        <span class="text-xs text-gray-500">{{ __('marketer.promote.wizard_step_label') }} <span x-text="step"></span> {{ __('marketer.promote.wizard_step_of') }} 5</span>
    </div>

    <div x-show="error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="error"></div>

    <!-- Step 1: Dates -->
    <div x-show="step === 1" class="space-y-4">
        <label class="block text-sm font-medium text-gray-700">{{ __('marketer.promote.step1_dates_label') }}</label>
        <div class="grid grid-cols-2 gap-4">
            <input type="date" x-model="form.booked_from" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
            <input type="date" x-model="form.booked_until" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
        </div>
        <template x-if="isMetered">
            <div>
                <label class="block text-sm font-medium text-gray-700 mt-3">{{ __('marketer.promote.step1_budget_label') }} (<span x-text="slot.currency"></span>)</label>
                <input type="number" min="1" x-model.number="form.budget" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm w-full">
            </div>
        </template>
        <p class="text-xs text-gray-400" x-text="dateHint"></p>
    </div>

    <!-- Step 2: Quote -->
    <div x-show="step === 2" class="space-y-3">
        <div class="rounded-xl border border-gray-100 bg-gray-50 divide-y divide-gray-100 text-sm" x-show="quote">
            <div class="flex justify-between px-4 py-2"><span>{{ __('marketer.promote.step2_units_label') }}</span><span x-text="quote?.units + ' × ' + quote?.unit_rate + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2"><span>{{ __('marketer.promote.step2_subtotal_label') }}</span><span x-text="quote?.subtotal + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2"><span>{{ __('marketer.promote.step2_tax_label') }}</span><span x-text="quote?.tax_amount + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2 font-semibold"><span>{{ __('marketer.promote.step2_total_label') }}</span><span x-text="quote?.total + ' ' + quote?.currency"></span></div>
        </div>
    </div>

    <!-- Step 3: Destination -->
    <div x-show="step === 3" class="space-y-3">
        <label class="block text-sm font-medium text-gray-700">{{ __('marketer.promote.step3_destination_label') }}</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button type="button" @click="chooseDestinationType('marketer_profile')"
                class="text-start rounded-xl border p-4 text-sm"
                :class="form.destination_type === 'marketer_profile' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'">
                <div class="font-semibold text-gray-900">{{ __('marketer.promote.step3_my_profile_title') }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ __('marketer.promote.step3_my_profile_hint') }}</div>
            </button>
            <button type="button" @click="chooseDestinationType('campaign')"
                class="text-start rounded-xl border p-4 text-sm"
                :class="form.destination_type === 'campaign' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'">
                <div class="font-semibold text-gray-900">{{ __('marketer.promote.step3_my_campaign_title') }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ __('marketer.promote.step3_my_campaign_hint') }}</div>
            </button>
        </div>

        <template x-if="form.destination_type === 'marketer_profile'">
            <div class="mt-2">
                <template x-if="!profileReady">
                    <div class="text-sm text-red-600">{{ __('marketer.promote.step3_profile_not_ready') }} <a href="{{ route('marketer.profile') }}" class="underline">{{ __('marketer.promote.step3_complete_profile_link') }}</a>.</div>
                </template>
            </div>
        </template>

        <template x-if="form.destination_type === 'campaign'">
            <div class="mt-2 max-h-56 overflow-y-auto divide-y divide-gray-100 border border-gray-100 rounded-lg">
                <template x-for="opt in campaignOptions" :key="opt.id">
                    <div class="px-3 py-2 text-sm flex items-center justify-between"
                         :class="opt.available ? 'cursor-pointer hover:bg-gray-50' : 'opacity-50 cursor-not-allowed'"
                         @click="opt.available && (form.destination_reference_id = opt.id)">
                        <span :class="form.destination_reference_id === opt.id ? 'text-primary-700 font-medium' : ''" x-text="opt.label"></span>
                        <span x-show="!opt.available" class="text-xs text-red-500" x-text="opt.reason"></span>
                    </div>
                </template>
                <div x-show="!campaignOptions.length" class="px-3 py-4 text-sm text-gray-400">{{ __('marketer.promote.step3_no_campaigns') }}</div>
            </div>
        </template>
    </div>

    <!-- Step 4: Creative -->
    <div x-show="step === 4" class="space-y-4">
        <p class="text-xs text-gray-500">
            {{ __('marketer.promote.step4_dimensions_hint') }}
            {{ __('marketer.promote.desktop_label') }} <span x-text="slot.creative_spec?.desktop?.w"></span>×<span x-text="slot.creative_spec?.desktop?.h"></span>px،
            {{ __('marketer.promote.mobile_label') }} <span x-text="slot.creative_spec?.mobile?.w"></span>×<span x-text="slot.creative_spec?.mobile?.h"></span>px
        </p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('marketer.promote.step4_desktop_label') }} *</label>
                <input type="file" accept="image/*" @change="onFile($event, 'desktop_en')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('marketer.promote.step4_desktop_ar_label') }}</label>
                <input type="file" accept="image/*" @change="onFile($event, 'desktop_ar')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('marketer.promote.step4_mobile_label') }} *</label>
                <input type="file" accept="image/*" @change="onFile($event, 'mobile_en')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('marketer.promote.step4_mobile_ar_label') }}</label>
                <input type="file" accept="image/*" @change="onFile($event, 'mobile_ar')">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <input type="text" placeholder="{{ __('marketer.promote.step4_title_en_placeholder') }}" x-model="form.title_en" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
            <input type="text" placeholder="{{ __('marketer.promote.step4_title_ar_placeholder') }}" x-model="form.title_ar" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm" dir="rtl">
        </div>
    </div>

    <!-- Step 5: Payment -->
    <div x-show="step === 5" class="space-y-4">
        <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm space-y-1">
            <div class="flex justify-between"><span>{{ __('marketer.promote.step5_wallet_balance') }}</span><span x-text="wallet.balance + ' ' + wallet.currency"></span></div>
            <div class="flex justify-between font-semibold"><span>{{ __('marketer.promote.step5_total_required') }}</span><span x-text="quote?.total + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between"><span>{{ __('marketer.promote.step5_balance_after') }}</span><span x-text="(wallet.balance - (quote?.total || 0)) + ' ' + wallet.currency"></span></div>
        </div>
        <p class="text-xs text-gray-400">{{ __('marketer.promote.step5_payment_hint') }}</p>
        <label class="flex items-center gap-2 text-sm mt-2">
            <input type="checkbox" x-model="form.terms"> {{ __('marketer.promote.step5_terms_label') }}
        </label>
    </div>

    <div class="flex items-center justify-between mt-8 pt-4 border-t border-gray-100">
        <button x-show="step > 1" @click="prev()" class="rounded-lg border border-gray-200 px-4 py-2 text-sm">{{ __('marketer.promote.prev_button') }}</button>
        <span></span>
        <button x-show="step < 5" @click="next()" :disabled="loading" class="rounded-lg bg-primary-600 text-white px-5 py-2 text-sm font-semibold disabled:opacity-60">{{ __('marketer.promote.next_button') }}</button>
        <button x-show="step === 5" @click="submit()" :disabled="loading || !form.terms" class="rounded-lg bg-emerald-600 text-white px-5 py-2 text-sm font-semibold disabled:opacity-60">{{ __('marketer.promote.submit_button') }}</button>
    </div>
</div>
@endsection
