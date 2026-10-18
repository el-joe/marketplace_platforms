@extends('layouts.portal')

@php $isAr = session('locale', 'ar') === 'ar'; @endphp

@section('title', portal_content('travel-agency', 'register', 'meta_title', 'Register your travel agency — Nawy', 'سجّل وكالة السفر الخاصة بك — ناوي'))
@section('hide_nav', true)

@push('head')
    @vite(['resources/js/portal/travel-agency-registration.js'])
@endpush

@section('content')
<div class="min-h-screen bg-gray-950 py-10 px-4" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
    <div class="max-w-2xl mx-auto">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <a href="{{ route('portal.companies.travel-agency') }}" class="inline-flex items-center gap-2">
                <span class="bg-[#0F807E] text-white font-black text-2xl px-3 py-1 rounded">{{ portal_content('travel-agency', 'register', 'logo_text', 'Nawy', 'ناوي') }}</span>
                <span class="text-white text-lg font-semibold">{{ portal_content('travel-agency', 'register', 'logo_tagline', 'for Travel Agencies', 'لوكالات السفر') }}</span>
            </a>
            <h1 class="mt-5 text-2xl font-black text-white">{{ portal_content('travel-agency', 'register', 'title', 'Register your travel agency', 'سجّل وكالة السفر الخاصة بك') }}</h1>
            <p class="mt-2 text-gray-400 text-sm">{{ portal_content('travel-agency', 'register', 'subtitle', 'Complete the following steps to create your agency account. Our team reviews it before activation.', 'أكمل الخطوات التالية لإنشاء حساب وكالتك. يراجعه فريقنا قبل التفعيل.') }}</p>
        </div>

        {{-- Wizard card --}}
        <div
            x-data="travelAgencyWizard()"
            x-init="init({{ Js::from(['currentStep' => $currentStep, 'savedData' => $savedData]) }})"
            class="bg-gray-900 rounded-2xl shadow-2xl overflow-hidden"
        >
            {{-- Progress bar --}}
            <div class="bg-gray-800 px-6 pt-6 pb-4">
                <div class="flex items-center mb-4">
                    <template x-for="n in totalSteps" :key="n">
                        <div class="flex items-center" :class="n < totalSteps ? 'flex-1' : ''">
                            <div
                                class="relative flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold border-2 transition-all duration-300 flex-shrink-0"
                                :class="{
                                    'bg-[#0F807E] border-[#feee00] text-white': step === n,
                                    'bg-[#0F807E]/20 border-[#feee00] text-[#0F807E]': step > n,
                                    'bg-gray-700 border-gray-600 text-gray-400': step < n
                                }"
                            >
                                <template x-if="step > n">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </template>
                                <template x-if="step <= n">
                                    <span x-text="n"></span>
                                </template>
                            </div>
                            <div
                                x-show="n < totalSteps"
                                class="flex-1 h-0.5 mx-1 transition-all duration-300"
                                :class="step > n ? 'bg-[#0F807E]' : 'bg-gray-600'"
                            ></div>
                        </div>
                    </template>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-400">
                        {{ portal_content('travel-agency', 'register', 'step_label', 'Step', 'الخطوة') }} <span x-text="step" class="text-[#feee00] font-semibold"></span>
                        {{ portal_content('travel-agency', 'register', 'of_label', 'of', 'من') }} <span x-text="totalSteps" class="font-semibold text-white"></span>
                    </p>
                    <p class="text-white font-semibold mt-1 text-sm" x-text="stepTitle()"></p>
                </div>
            </div>

            {{-- Global error banner --}}
            <div
                x-show="globalError"
                x-transition
                x-cloak
                class="mx-6 mt-4 bg-red-900/40 border border-red-700 rounded-lg px-4 py-3 text-sm text-red-300"
                x-text="globalError"
            ></div>

            {{-- Step bodies --}}
            <div class="p-6">

                {{-- Step 1: Account --}}
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-4">
                    <h2 class="text-lg font-bold text-white mb-4">{{ portal_content('travel-agency', 'register', 'step_1_heading', 'Account information', 'معلومات الحساب') }}</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('travel-agency', 'register', 'name_label', 'Full name', 'الاسم الكامل') }} <span class="text-red-400">*</span></label>
                        <input type="text" x-model="form.name" autocomplete="name" placeholder="{{ portal_content('travel-agency', 'register', 'name_placeholder', 'Amina Al Farsi', 'أمينة الفارسي') }}"
                            class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
                            :class="errors.name ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-[#feee00]'">
                        <p x-show="errors.name" x-text="errors.name?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('travel-agency', 'register', 'email_label', 'Email address', 'البريد الإلكتروني') }} <span class="text-red-400">*</span></label>
                        <input type="email" x-model="form.email" autocomplete="email" placeholder="agency@example.com" dir="ltr"
                            class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors text-left"
                            :class="errors.email ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-[#feee00]'">
                        <p x-show="errors.email" x-text="errors.email?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('travel-agency', 'register', 'phone_label', 'Phone number', 'رقم الهاتف') }} <span class="text-red-400">*</span></label>
                        <input type="tel" x-model="form.phone" autocomplete="tel" placeholder="+971 50 000 0000" dir="ltr"
                            class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors text-left"
                            :class="errors.phone ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-[#feee00]'">
                        <p x-show="errors.phone" x-text="errors.phone?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('travel-agency', 'register', 'country_label', 'Country', 'الدولة') }} <span class="text-red-400">*</span></label>
                        <select x-model="form.country_id"
                            class="w-full bg-gray-800 border text-white rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
                            :class="errors.country_id ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-[#feee00]'">
                            <option value="">{{ portal_content('travel-agency', 'register', 'country_placeholder', '— Select country —', '— اختر الدولة —') }}</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->flag_emoji }} {{ $isAr ? $country->name_ar : ($country->name_en ?? $country->name_ar) }}</option>
                            @endforeach
                        </select>
                        <p x-show="errors.country_id" x-text="errors.country_id?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('travel-agency', 'register', 'password_label', 'Password', 'كلمة المرور') }} <span class="text-red-400">*</span></label>
                            <input type="password" x-model="form.password" autocomplete="new-password" placeholder="{{ portal_content('travel-agency', 'register', 'password_placeholder', 'At least 8 characters', '٨ أحرف على الأقل') }}" dir="ltr"
                                class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
                                :class="errors.password ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-[#feee00]'">
                            <p x-show="errors.password" x-text="errors.password?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('travel-agency', 'register', 'password_confirmation_label', 'Confirm password', 'تأكيد كلمة المرور') }} <span class="text-red-400">*</span></label>
                            <input type="password" x-model="form.password_confirmation" autocomplete="new-password" placeholder="{{ portal_content('travel-agency', 'register', 'password_confirmation_placeholder', 'Re-enter your password', 'أعد إدخال كلمة المرور') }}" dir="ltr"
                                class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
                                :class="errors.password_confirmation ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-[#feee00]'">
                            <p x-show="errors.password_confirmation" x-text="errors.password_confirmation?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Agency --}}
                <div x-show="step === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-4">
                    <h2 class="text-lg font-bold text-white mb-4">{{ portal_content('travel-agency', 'register', 'step_2_heading', 'Agency information', 'معلومات الوكالة') }}</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('travel-agency', 'register', 'agency_name_label', 'Agency name', 'اسم الوكالة') }} <span class="text-red-400">*</span></label>
                        <input type="text" x-model="form.agency_name" placeholder="{{ portal_content('travel-agency', 'register', 'agency_name_placeholder', 'Gulf Horizons Travel', 'آفاق الخليج للسفر') }}"
                            class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
                            :class="errors.agency_name ? 'border-red-500 focus:border-red-400' : 'border-gray-700 focus:border-[#feee00]'">
                        <p x-show="errors.agency_name" x-text="errors.agency_name?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">
                            {{ portal_content('travel-agency', 'register', 'license_label', 'Tourism / trade license number', 'رقم ترخيص السياحة / السجل التجاري') }}
                            <span class="text-gray-500 font-normal text-xs">{{ portal_content('travel-agency', 'register', 'optional', '(optional)', '(اختياري)') }}</span>
                        </label>
                        <input type="text" x-model="form.license_number" placeholder="DTCM-2024-0011" dir="ltr"
                            class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
                        <p x-show="errors.license_number" x-text="errors.license_number?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">
                            {{ portal_content('travel-agency', 'register', 'logo_label', 'Agency logo', 'شعار الوكالة') }}
                            <span class="text-gray-500 font-normal text-xs">{{ portal_content('travel-agency', 'register', 'optional', '(optional)', '(اختياري)') }}</span>
                        </label>

                        <div class="flex items-center gap-3">
                            <template x-if="logoPreview">
                                <img :src="logoPreview" alt="" class="h-14 w-14 rounded-xl object-cover border border-gray-700">
                            </template>
                            <label class="cursor-pointer inline-flex items-center gap-2 rounded-xl border border-gray-700 bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-200 hover:border-[#feee00] transition-colors">
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onLogoChange($event)">
                                <span x-text="logoFilename || window.t('portal.travel_agency.choose_logo')"></span>
                            </label>
                            <button type="button" x-show="logoFilename" x-cloak @click="removeLogo()"
                                class="text-xs text-red-400 hover:text-red-300">{{ portal_content('travel-agency', 'register', 'logo_remove', 'Remove', 'إزالة') }}</button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ portal_content('travel-agency', 'register', 'logo_hint', 'PNG, JPG or WEBP, up to 2MB.', 'PNG أو JPG أو WEBP، حتى 2 ميجابايت.') }}</p>
                        <p x-show="errors.logo" x-text="errors.logo?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
                    </div>
                </div>

                {{-- Step 3: Review --}}
                <div x-show="step === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-4">
                    <h2 class="text-lg font-bold text-white mb-4">{{ portal_content('travel-agency', 'register', 'step_3_heading', 'Review & confirm', 'مراجعة وتأكيد') }}</h2>

                    <dl class="divide-y divide-gray-800 rounded-xl border border-gray-800 bg-gray-950/60">
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-gray-400">{{ portal_content('travel-agency', 'register', 'review_name', 'Full name', 'الاسم الكامل') }}</dt>
                            <dd class="text-sm font-semibold text-white text-end" x-text="form.name || '—'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-gray-400">{{ portal_content('travel-agency', 'register', 'review_email', 'Email', 'البريد الإلكتروني') }}</dt>
                            <dd class="text-sm font-semibold text-white text-end break-all" dir="ltr" x-text="form.email || '—'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-gray-400">{{ portal_content('travel-agency', 'register', 'review_phone', 'Phone', 'الهاتف') }}</dt>
                            <dd class="text-sm font-semibold text-white text-end" dir="ltr" x-text="form.phone || '—'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-gray-400">{{ portal_content('travel-agency', 'register', 'review_agency', 'Agency name', 'اسم الوكالة') }}</dt>
                            <dd class="text-sm font-semibold text-white text-end" x-text="form.agency_name || '—'"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <dt class="text-sm text-gray-400">{{ portal_content('travel-agency', 'register', 'review_license', 'License number', 'رقم الترخيص') }}</dt>
                            <dd class="text-sm font-semibold text-white text-end" x-text="form.license_number || '—'"></dd>
                        </div>
                    </dl>

                    <label class="flex items-start gap-3 text-sm text-gray-300">
                        <input type="checkbox" x-model="form.terms_agreed" class="mt-0.5 h-4 w-4 rounded border-gray-600 bg-gray-800 text-[#0F807E] focus:ring-[#0F807E]">
                        <span>{{ portal_content('travel-agency', 'register', 'terms_label', 'I agree to the Terms & Conditions.', 'أوافق على الشروط والأحكام.') }}</span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-gray-300">
                        <input type="checkbox" x-model="form.privacy_agreed" class="mt-0.5 h-4 w-4 rounded border-gray-600 bg-gray-800 text-[#0F807E] focus:ring-[#0F807E]">
                        <span>{{ portal_content('travel-agency', 'register', 'privacy_label', 'I agree to the Privacy Policy.', 'أوافق على سياسة الخصوصية.') }}</span>
                    </label>
                    <p x-show="errors.terms_agreed || errors.privacy_agreed" x-text="(errors.terms_agreed?.[0] || errors.privacy_agreed?.[0])" class="text-xs text-red-400" x-cloak></p>
                </div>

            </div>

            {{-- Navigation footer --}}
            <div class="px-6 pb-6 flex items-center gap-3 border-t border-gray-800 pt-4">
                <button
                    x-show="step > 1"
                    @click="prevStep()"
                    type="button"
                    class="flex-none px-5 py-2.5 rounded-xl text-sm font-medium text-gray-300 bg-gray-700 hover:bg-gray-600 transition-colors"
                >
                    {{ portal_content('travel-agency', 'register', 'back_button', '← Back', 'السابق →') }}
                </button>

                <button
                    x-show="step < totalSteps"
                    @click="nextStep()"
                    type="button"
                    :disabled="loading"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[#0F807E] hover:bg-[#0c6665] text-white transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span x-show="!loading">{{ portal_content('travel-agency', 'register', 'next_button', 'Next →', 'التالي ←') }}</span>
                    <span x-show="loading" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        {{ portal_content('travel-agency', 'register', 'saving_label', 'Saving…', 'جارٍ الحفظ…') }}
                    </span>
                </button>

                <button
                    x-show="step === totalSteps"
                    @click="submit()"
                    type="button"
                    :disabled="loading || !form.terms_agreed || !form.privacy_agreed"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[#0F807E] hover:bg-[#0c6665] text-white transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span x-show="!loading">{{ portal_content('travel-agency', 'register', 'submit_button', 'Submit application ✓', 'إرسال الطلب ✓') }}</span>
                    <span x-show="loading" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        {{ portal_content('travel-agency', 'register', 'submitting_label', 'Submitting…', 'جارٍ الإرسال…') }}
                    </span>
                </button>
            </div>

        </div>{{-- /wizard card --}}

        <p class="text-center text-sm text-gray-500 mt-6">
            {{ portal_content('travel-agency', 'register', 'login_prompt', 'Already have an agency account?', 'لديك حساب وكالة بالفعل؟') }}
            <a href="{{ route('travel-agency.login') }}" class="text-[#0F807E] hover:underline">{{ portal_content('travel-agency', 'register', 'login_link', 'Log in', 'تسجيل الدخول') }}</a>
        </p>

    </div>
</div>

<script>
window._routes = {
    step:      '{{ route('portal.companies.travel-agency.register.step', ['step' => '__STEP__']) }}',
    logoRemove: '{{ route('portal.companies.travel-agency.register.logo.remove') }}',
    complete:  '{{ route('portal.companies.travel-agency.register.complete') }}',
};
window._csrf = '{{ csrf_token() }}';
</script>
@endsection
