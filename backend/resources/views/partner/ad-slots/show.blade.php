@extends('layouts.partner')
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
                'vendor_type' => auth('vendor')->user()->vendor->vendor_type->value ?? 'product_vendor',
            ]) !!},
            calendarUrl: "{{ route('partner.ad-slots.calendar', $slot->id) }}",
            quoteUrl: "{{ route('partner.ad-slots.quote', $slot->id) }}",
            destinationsUrl: "{{ route('partner.ad-slots.destinations') }}",
            storeBookingUrl: "{{ route('partner.ad-bookings.store') }}",
            uploadCreativeUrlTemplate: "{{ route('partner.ad-bookings.creative', ['booking' => '__ID__']) }}",
            submitUrlTemplate: "{{ route('partner.ad-bookings.submit', ['booking' => '__ID__']) }}",
            walletBalanceUrl: "{{ route('partner.wallet.index') }}",
        };
    </script>
    @vite('resources/js/partner/ad-slot-wizard.js')
@endpush

@section('content')
<div x-data="adBookingWizard()" x-init="init()" class="bg-white rounded-2xl border border-gray-200 p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-gray-900">{{ __('partner.ad_slots.wizard_title') }}</h2>
        <span class="text-xs text-gray-500">{{ __('partner.ad_slots.step') }} <span x-text="step"></span> / 5</span>
    </div>

    <div x-show="error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="error"></div>

    <!-- Step 1: Dates -->
    <div x-show="step === 1" class="space-y-4">
        <label class="block text-sm font-medium text-gray-700">{{ __('partner.ad_slots.dates') }}</label>
        <div class="grid grid-cols-2 gap-4">
            <input type="date" x-model="form.booked_from" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
            <input type="date" x-model="form.booked_until" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
        </div>
        <template x-if="isMetered">
            <div>
                <label class="block text-sm font-medium text-gray-700 mt-3">{{ __('partner.ad_slots.budget') }} (<span x-text="slot.currency"></span>)</label>
                <input type="number" min="1" x-model.number="form.budget" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm w-full">
            </div>
        </template>
        <p class="text-xs text-gray-400" x-text="dateHint"></p>
    </div>

    <!-- Step 2: Quote -->
    <div x-show="step === 2" class="space-y-3">
        <div class="rounded-xl border border-gray-100 bg-gray-50 divide-y divide-gray-100 text-sm" x-show="quote">
            <div class="flex justify-between px-4 py-2"><span>{{ __('partner.ad_slots.units') }}</span><span x-text="quote?.units + ' × ' + quote?.unit_rate + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2"><span>{{ __('partner.ad_slots.subtotal') }}</span><span x-text="quote?.subtotal + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2"><span>{{ __('partner.ad_slots.vat') }}</span><span x-text="quote?.tax_amount + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2 font-semibold"><span>{{ __('partner.ad_slots.total') }}</span><span x-text="quote?.total + ' ' + quote?.currency"></span></div>
        </div>
    </div>

    <!-- Step 3: Destination -->
    <div x-show="step === 3" class="space-y-3">
        <label class="block text-sm font-medium text-gray-700">{{ __('partner.ad_slots.destination_type') }}</label>
        <select x-model="form.destination_type" @change="searchDestinations('')" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
            <option :value="listingDestinationType" x-text="listingDestinationTypeLabel"></option>
            <option value="store">{{ __('partner.ad_slots.destination_store') }}</option>
            <option value="brand">{{ __('partner.ad_slots.destination_brand') }}</option>
            <option value="category">{{ __('partner.ad_slots.destination_category') }}</option>
        </select>
        <template x-if="form.destination_type !== 'store'">
            <div>
                <input type="text" placeholder="{{ __('partner.ad_slots.search_placeholder') }}" @input="searchDestinations($event.target.value)" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
                <div class="mt-2 max-h-48 overflow-y-auto divide-y divide-gray-100 border border-gray-100 rounded-lg">
                    <template x-for="opt in destinationOptions" :key="opt.id">
                        <div class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50" :class="form.destination_reference_id === opt.id ? 'bg-primary-50 text-primary-700' : ''" @click="form.destination_reference_id = opt.id" x-text="opt.label"></div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Step 4: Creative -->
    <div x-show="step === 4" class="space-y-4">
        <p class="text-xs text-gray-500">
            {{ __('partner.ad_slots.creative_required_size') }}:
            desktop <span x-text="slot.creative_spec?.desktop?.w"></span>×<span x-text="slot.creative_spec?.desktop?.h"></span>px,
            mobile <span x-text="slot.creative_spec?.mobile?.w"></span>×<span x-text="slot.creative_spec?.mobile?.h"></span>px
        </p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('partner.ad_slots.desktop_en') }} *</label>
                <input type="file" accept="image/*" @change="onFile($event, 'desktop_en')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('partner.ad_slots.desktop_ar') }}</label>
                <input type="file" accept="image/*" @change="onFile($event, 'desktop_ar')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('partner.ad_slots.mobile_en') }} *</label>
                <input type="file" accept="image/*" @change="onFile($event, 'mobile_en')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('partner.ad_slots.mobile_ar') }}</label>
                <input type="file" accept="image/*" @change="onFile($event, 'mobile_ar')">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <input type="text" placeholder="{{ __('partner.ad_slots.title_en') }}" x-model="form.title_en" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
            <input type="text" placeholder="{{ __('partner.ad_slots.title_ar') }}" x-model="form.title_ar" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm" dir="rtl">
        </div>
    </div>

    <!-- Step 5: Payment -->
    <div x-show="step === 5" class="space-y-4">
        <label class="flex items-center gap-2 text-sm">
            <input type="radio" name="pm" value="payout_deduction" x-model="form.payment_method"> {{ __('partner.ad_slots.pay_payout') }}
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="radio" name="pm" value="wallet" x-model="form.payment_method"> {{ __('partner.ad_slots.pay_wallet') }}
        </label>
        <label class="flex items-center gap-2 text-sm mt-4">
            <input type="checkbox" x-model="form.terms"> {{ __('partner.ad_slots.accept_terms') }}
        </label>
    </div>

    <div class="flex items-center justify-between mt-8 pt-4 border-t border-gray-100">
        <button x-show="step > 1" @click="prev()" class="rounded-lg border border-gray-200 px-4 py-2 text-sm">{{ __('common.previous') }}</button>
        <span></span>
        <button x-show="step < 5" @click="next()" :disabled="loading" class="rounded-lg bg-primary-600 text-white px-5 py-2 text-sm font-semibold disabled:opacity-60">{{ __('common.next') }}</button>
        <button x-show="step === 5" @click="submit()" :disabled="loading || !form.terms" class="rounded-lg bg-emerald-600 text-white px-5 py-2 text-sm font-semibold disabled:opacity-60">{{ __('partner.ad_slots.submit_for_review') }}</button>
    </div>
</div>
@endsection
