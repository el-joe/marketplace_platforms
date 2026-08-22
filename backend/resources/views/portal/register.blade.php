@extends('layouts.portal')

@php $isAr = session('locale', 'ar') === 'ar'; @endphp

@section('title', portal_content('register', 'meta', 'title', 'Register as a Seller — noon', 'سجّل كبائع — نون'))
@section('hide_nav', true)

@push('head')
    @vite(['resources/js/portal/registration.js'])
    <link href="https://unpkg.com/filepond@^4/dist/filepond.css" rel="stylesheet">
    <link href="https://unpkg.com/filepond-plugin-image-preview@^4/dist/filepond-plugin-image-preview.css" rel="stylesheet">
@endpush

@section('content')
<div class="min-h-screen bg-gray-950 py-10 px-4" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
    <div class="max-w-2xl mx-auto">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <a href="{{ route('portal.home') }}" class="inline-flex items-center gap-2">
                <span class="bg-[#feee00] text-gray-950 font-black text-2xl px-3 py-1 rounded">noon</span>
                <span class="text-white text-lg font-semibold">{{ portal_content('register', 'header', 'logo_tagline', 'for Sellers', 'للبائعين') }}</span>
            </a>
            <h1 class="mt-5 text-2xl font-black text-white">{{ portal_content('register', 'header', 'title', 'Join noon as a Seller', 'انضم إلى منصة نون كبائع') }}</h1>
            <p class="mt-2 text-gray-400 text-sm">{{ portal_content('register', 'header', 'subtitle', 'Complete the following steps to create your business account', 'أكمل الخطوات التالية لإنشاء حسابك التجاري') }}</p>
        </div>

        {{-- Wizard card --}}
        <div
            x-data="registrationWizard()"
            x-init="init({{ json_encode(['currentStep' => $currentStep, 'savedData' => $savedData]) }})"
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
                                    'bg-[#feee00] border-[#feee00] text-gray-900': step === n,
                                    'bg-[#feee00]/20 border-[#feee00] text-[#feee00]': step > n,
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
                                :class="step > n ? 'bg-[#feee00]' : 'bg-gray-600'"
                            ></div>
                        </div>
                    </template>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-400">
                        {{ portal_content('register', 'progress', 'step_label', 'Step', 'الخطوة') }} <span x-text="step" class="text-[#feee00] font-semibold"></span>
                        {{ portal_content('register', 'progress', 'of_label', 'of', 'من') }} <span x-text="totalSteps" class="font-semibold text-white"></span>
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

                <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    @include('portal.register.step-1')
                </div>

                <div x-show="step === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    @include('portal.register.step-2')
                </div>

                <div x-show="step === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    @include('portal.register.step-3')
                </div>

                <div x-show="step === 4" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    @include('portal.register.step-4')
                </div>

                <div x-show="step === 5" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    @include('portal.register.step-5')
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
                    {{ portal_content('register', 'footer_nav', 'back_button', 'Back →', '← السابق') }}
                </button>

                <button
                    x-show="step < totalSteps"
                    @click="nextStep()"
                    type="button"
                    :disabled="loading"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[#feee00] hover:bg-[#e5d600] text-gray-900 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span x-show="!loading">{{ portal_content('register', 'footer_nav', 'next_button', '→ Next', 'التالي ←') }}</span>
                    <span x-show="loading" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        {{ portal_content('register', 'footer_nav', 'saving_label', 'Saving…', 'جارٍ الحفظ…') }}
                    </span>
                </button>

                <button
                    x-show="step === totalSteps"
                    @click="submit()"
                    type="button"
                    :disabled="loading || !form.terms_agreed || !form.privacy_agreed"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold bg-[#feee00] hover:bg-[#e5d600] text-gray-900 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span x-show="!loading">{{ portal_content('register', 'footer_nav', 'submit_button', 'Submit Application ✓', 'إرسال الطلب ✓') }}</span>
                    <span x-show="loading" class="flex items-center justify-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        {{ portal_content('register', 'footer_nav', 'submitting_label', 'Submitting…', 'جارٍ الإرسال…') }}
                    </span>
                </button>
            </div>

        </div>{{-- /wizard card --}}

        <p class="text-center text-sm text-gray-500 mt-6">
            {{ portal_content('register', 'login_prompt', 'text', 'Already have a seller account?', 'لديك حساب بائع بالفعل؟') }}
            @php($loginLink = portal_link('register', 'login_prompt', 'link', 'Log in', 'تسجيل الدخول', route('partner.login')))
            <a href="{{ $loginLink['url'] }}" class="text-[#feee00] hover:underline">{{ $loginLink['label'] }}</a>
        </p>

    </div>
</div>

<script>
window._routes = {
    step:         '{{ route('portal.register.step', ['step' => '__STEP__']) }}',
    checkSlug:    '{{ route('portal.register.check-slug') }}',
    cities:       '{{ route('portal.register.cities') }}',
    docRequirements: '{{ route('portal.register.document-requirements') }}',
    upload:       '{{ route('portal.register.upload') }}',
    docRemove:    '{{ route('portal.register.document.remove') }}',
    complete:     '{{ route('portal.register.complete') }}',
};
window._csrf = '{{ csrf_token() }}';
</script>
@endsection
