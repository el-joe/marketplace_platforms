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
        <h2 class="text-lg font-bold text-gray-900">حجز مكان إعلاني</h2>
        <span class="text-xs text-gray-500">خطوة <span x-text="step"></span> / 5</span>
    </div>

    <div x-show="error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="error"></div>

    <!-- Step 1: Dates -->
    <div x-show="step === 1" class="space-y-4">
        <label class="block text-sm font-medium text-gray-700">التواريخ</label>
        <div class="grid grid-cols-2 gap-4">
            <input type="date" x-model="form.booked_from" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
            <input type="date" x-model="form.booked_until" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
        </div>
        <template x-if="isMetered">
            <div>
                <label class="block text-sm font-medium text-gray-700 mt-3">الميزانية (<span x-text="slot.currency"></span>)</label>
                <input type="number" min="1" x-model.number="form.budget" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm w-full">
            </div>
        </template>
        <p class="text-xs text-gray-400" x-text="dateHint"></p>
    </div>

    <!-- Step 2: Quote -->
    <div x-show="step === 2" class="space-y-3">
        <div class="rounded-xl border border-gray-100 bg-gray-50 divide-y divide-gray-100 text-sm" x-show="quote">
            <div class="flex justify-between px-4 py-2"><span>الوحدات</span><span x-text="quote?.units + ' × ' + quote?.unit_rate + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2"><span>الإجمالي قبل الضريبة</span><span x-text="quote?.subtotal + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2"><span>الضريبة</span><span x-text="quote?.tax_amount + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between px-4 py-2 font-semibold"><span>الإجمالي</span><span x-text="quote?.total + ' ' + quote?.currency"></span></div>
        </div>
    </div>

    <!-- Step 3: Destination -->
    <div x-show="step === 3" class="space-y-3">
        <label class="block text-sm font-medium text-gray-700">وجهة الإعلان</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button type="button" @click="chooseDestinationType('marketer_profile')"
                class="text-start rounded-xl border p-4 text-sm"
                :class="form.destination_type === 'marketer_profile' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'">
                <div class="font-semibold text-gray-900">صفحتي الشخصية</div>
                <div class="text-xs text-gray-500 mt-1">وجّه الزوار مباشرة إلى بروفايلك العام.</div>
            </button>
            <button type="button" @click="chooseDestinationType('campaign')"
                class="text-start rounded-xl border p-4 text-sm"
                :class="form.destination_type === 'campaign' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'">
                <div class="font-semibold text-gray-900">إحدى حملاتي</div>
                <div class="text-xs text-gray-500 mt-1">استخدم رابط الإحالة الخاص بحملة قبلت الدعوة إليها.</div>
            </button>
        </div>

        <template x-if="form.destination_type === 'marketer_profile'">
            <div class="mt-2">
                <template x-if="!profileReady">
                    <div class="text-sm text-red-600">لم يتم إعداد صفحتك الشخصية بعد. <a href="{{ route('marketer.profile') }}" class="underline">أكمل الملف الشخصي</a>.</div>
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
                <div x-show="!campaignOptions.length" class="px-3 py-4 text-sm text-gray-400">لا توجد حملات مقبولة حالياً.</div>
            </div>
        </template>
    </div>

    <!-- Step 4: Creative -->
    <div x-show="step === 4" class="space-y-4">
        <p class="text-xs text-gray-500">
            المقاسات المطلوبة:
            الكمبيوتر <span x-text="slot.creative_spec?.desktop?.w"></span>×<span x-text="slot.creative_spec?.desktop?.h"></span>px،
            الجوال <span x-text="slot.creative_spec?.mobile?.w"></span>×<span x-text="slot.creative_spec?.mobile?.h"></span>px
        </p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">كمبيوتر (EN) *</label>
                <input type="file" accept="image/*" @change="onFile($event, 'desktop_en')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">كمبيوتر (AR)</label>
                <input type="file" accept="image/*" @change="onFile($event, 'desktop_ar')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">جوال (EN) *</label>
                <input type="file" accept="image/*" @change="onFile($event, 'mobile_en')">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">جوال (AR)</label>
                <input type="file" accept="image/*" @change="onFile($event, 'mobile_ar')">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <input type="text" placeholder="العنوان (EN)" x-model="form.title_en" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
            <input type="text" placeholder="العنوان (AR)" x-model="form.title_ar" class="rounded-lg border border-gray-200 px-3 py-2.5 text-sm" dir="rtl">
        </div>
    </div>

    <!-- Step 5: Payment -->
    <div x-show="step === 5" class="space-y-4">
        <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm space-y-1">
            <div class="flex justify-between"><span>رصيد المحفظة</span><span x-text="wallet.balance + ' ' + wallet.currency"></span></div>
            <div class="flex justify-between font-semibold"><span>الإجمالي المطلوب</span><span x-text="quote?.total + ' ' + quote?.currency"></span></div>
            <div class="flex justify-between"><span>الرصيد بعد الدفع</span><span x-text="(wallet.balance - (quote?.total || 0)) + ' ' + wallet.currency"></span></div>
        </div>
        <p class="text-xs text-gray-400">الدفع من المحفظة فقط. اكسب المزيد من العمولات من خلال حملاتك لتغطية تكلفة الإعلان.</p>
        <label class="flex items-center gap-2 text-sm mt-2">
            <input type="checkbox" x-model="form.terms"> أوافق على شروط الإعلان
        </label>
    </div>

    <div class="flex items-center justify-between mt-8 pt-4 border-t border-gray-100">
        <button x-show="step > 1" @click="prev()" class="rounded-lg border border-gray-200 px-4 py-2 text-sm">السابق</button>
        <span></span>
        <button x-show="step < 5" @click="next()" :disabled="loading" class="rounded-lg bg-primary-600 text-white px-5 py-2 text-sm font-semibold disabled:opacity-60">التالي</button>
        <button x-show="step === 5" @click="submit()" :disabled="loading || !form.terms" class="rounded-lg bg-emerald-600 text-white px-5 py-2 text-sm font-semibold disabled:opacity-60">إرسال للمراجعة</button>
    </div>
</div>
@endsection
