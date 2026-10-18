@extends('layouts.portal')

@php $isAr = session('locale', 'ar') === 'ar'; @endphp

@section('title', portal_content('travel-agency', 'success', 'page_title', 'Application submitted — Nawy Travel Agencies', 'تم إرسال الطلب — وكالات السفر في ناوي'))

@section('content')
    <div class="min-h-screen bg-gray-950 flex items-center justify-center py-16 px-4" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
        <div class="max-w-lg w-full text-center">

            {{-- Success Icon --}}
            <div class="w-20 h-20 bg-[#0F807E]/10 border-2 border-[#feee00]/40 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-[#0F807E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 class="text-3xl font-black text-white mb-3">{{ portal_content('travel-agency', 'success', 'title', 'Thank you for registering!', 'شكراً لتسجيلك!') }}</h1>
            <p class="text-gray-400 text-base mb-8 leading-relaxed">
                {{ portal_content('travel-agency', 'success', 'intro_prefix', "We've received your travel agency application successfully. Our team will review it within", 'استلمنا طلب تسجيل وكالة السفر بنجاح. سيقوم فريقنا بمراجعته خلال') }}
                <span class="text-[#0F807E] font-semibold">{{ portal_content('travel-agency', 'success', 'intro_days', '3–5 business days', '٣–٥ أيام عمل') }}</span>{{ $isAr ? '،' : ',' }}
                {{ portal_content('travel-agency', 'success', 'intro_suffix', "and we'll reach out via email as soon as a decision is made.", 'وسنتواصل معك عبر البريد الإلكتروني فور اتخاذ القرار.') }}
            </p>

            {{-- Next steps --}}
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 mb-8 {{ $isAr ? 'text-right' : 'text-left' }}">
                <h2 class="text-sm font-semibold text-gray-300 mb-4 text-center">{{ portal_content('travel-agency', 'success', 'next_steps_title', 'What happens next?', 'ماذا سيحدث بعد ذلك؟') }}</h2>
                <ul class="space-y-4">
                    <li class="flex items-start gap-4">
                        <span class="shrink-0 w-8 h-8 bg-[#0F807E]/20 rounded-full flex items-center justify-center text-[#0F807E] font-bold text-sm">{{ $isAr ? '١' : '1' }}</span>
                        <div>
                            <p class="text-sm text-white font-medium">{{ portal_content('travel-agency', 'success', 'step_1_title', 'Application review', 'مراجعة الطلب') }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ portal_content('travel-agency', 'success', 'step_1_text', 'Our team will verify your agency details and license.', 'سيتحقق فريقنا من بيانات وكالتك وترخيصها.') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-4">
                        <span class="shrink-0 w-8 h-8 bg-[#0F807E]/20 rounded-full flex items-center justify-center text-[#0F807E] font-bold text-sm">{{ $isAr ? '٢' : '2' }}</span>
                        <div>
                            <p class="text-sm text-white font-medium">{{ portal_content('travel-agency', 'success', 'step_2_title', 'Decision notification', 'إشعار القرار') }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ portal_content('travel-agency', 'success', 'step_2_text', 'You will receive an email once your agency is approved or if more information is needed.', 'ستتلقى بريداً إلكترونياً عند الموافقة على وكالتك أو إذا احتجنا معلومات إضافية.') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-4">
                        <span class="shrink-0 w-8 h-8 bg-[#0F807E]/20 rounded-full flex items-center justify-center text-[#0F807E] font-bold text-sm">{{ $isAr ? '٣' : '3' }}</span>
                        <div>
                            <p class="text-sm text-white font-medium">{{ portal_content('travel-agency', 'success', 'step_3_title', 'Start publishing trips', 'ابدأ بنشر الرحلات') }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ portal_content('travel-agency', 'success', 'step_3_text', 'Log in to your agency dashboard to add packages and manage bookings.', 'سجّل الدخول إلى لوحة تحكم وكالتك لإضافة الباقات وإدارة الحجوزات.') }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            {{-- CTA --}}
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('travel-agency.login') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#0F807E] hover:bg-[#0c6665] text-white font-bold rounded-xl text-sm transition-colors">
                    {{ portal_content('travel-agency', 'success', 'login_button', 'Go to agency login', 'الذهاب لتسجيل دخول الوكالة') }}
                </a>
                <a href="{{ route('portal.companies.travel-agency') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium rounded-xl text-sm transition-colors">
                    {{ portal_content('travel-agency', 'success', 'back_button', 'Back to overview', 'العودة للصفحة التعريفية') }}
                </a>
            </div>

        </div>
    </div>
@endsection
