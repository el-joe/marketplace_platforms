@extends('layouts.portal')

@php $isAr = session('locale', 'ar') === 'ar'; @endphp

@section('title', portal_content('register', 'success', 'page_title', 'Application Submitted — noon for Sellers', 'تم إرسال طلبك — نون للبائعين'))

@section('content')
    <div class="min-h-screen bg-gray-950 flex items-center justify-center py-16 px-4" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
        <div class="max-w-lg w-full text-center">

            {{-- Success Icon --}}
            <div
                class="w-20 h-20 bg-[#feee00]/10 border-2 border-[#feee00]/40 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-[#feee00]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 class="text-3xl font-black text-white mb-3">{{ portal_content('register', 'success', 'title', 'Thank you for registering!', 'شكراً لتسجيلك!') }}</h1>
            <p class="text-gray-400 text-base mb-8 leading-relaxed">
                {{ portal_content('register', 'success', 'intro_prefix', "We've received your application successfully. Our team will review your information and documents within", 'استلمنا طلبك بنجاح. سيقوم فريقنا بمراجعة بياناتك ووثائقك خلال') }}
                <span class="text-[#feee00] font-semibold">{{ portal_content('register', 'success', 'intro_days', '3–5 business days', '٣–٥ أيام عمل') }}</span>{{ $isAr ? '،' : ',' }}
                {{ portal_content('register', 'success', 'intro_suffix', "and we'll reach out via email as soon as a decision is made.", 'وسنتواصل معك عبر البريد الإلكتروني فور اتخاذ القرار.') }}
            </p>

            {{-- Steps --}}
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 mb-8 {{ $isAr ? 'text-right' : 'text-left' }}">
                <h2 class="text-sm font-semibold text-gray-300 mb-4 text-center">{{ portal_content('register', 'success', 'next_steps_title', 'What happens next?', 'ماذا سيحدث بعد ذلك؟') }}</h2>
                <ul class="space-y-4">
                    <li class="flex items-start gap-4">
                        <span
                            class="shrink-0 w-8 h-8 bg-[#feee00]/20 rounded-full flex items-center justify-center text-[#feee00] font-bold text-sm">{{ $isAr ? '١' : '1' }}</span>
                        <div>
                            <p class="text-sm text-white font-medium">{{ portal_content('register', 'success', 'step_1_title', 'Application review', 'مراجعة الطلب') }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ portal_content('register', 'success', 'step_1_text', 'Our team will review your information and documents within 3–5 business days', 'سيراجع فريقنا بياناتك ووثائقك خلال ٣–٥ أيام عمل') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-4">
                        <span
                            class="shrink-0 w-8 h-8 bg-[#feee00]/20 rounded-full flex items-center justify-center text-[#feee00] font-bold text-sm">{{ $isAr ? '٢' : '2' }}</span>
                        <div>
                            <p class="text-sm text-white font-medium">{{ portal_content('register', 'success', 'step_2_title', 'Decision notification', 'إشعار القرار') }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ portal_content('register', 'success', 'step_2_text', 'You will receive an email with the approval decision or a request for additional information', 'ستتلقى بريداً إلكترونياً يتضمن قرار القبول أو طلب معلومات إضافية') }}</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-4">
                        <span
                            class="shrink-0 w-8 h-8 bg-[#feee00]/20 rounded-full flex items-center justify-center text-[#feee00] font-bold text-sm">{{ $isAr ? '٣' : '3' }}</span>
                        <div>
                            <p class="text-sm text-white font-medium">{{ portal_content('register', 'success', 'step_3_title', 'Start selling!', 'ابدأ البيع!') }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ portal_content('register', 'success', 'step_3_text', 'Once approved, you get full access to the seller dashboard to add your products', 'بعد الموافقة تحصل على وصول كامل للوحة تحكم البائع لإضافة منتجاتك') }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            {{-- CTA --}}
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                @php($successHomeCta = portal_link('register', 'success', 'home_button', 'Back to Home', 'العودة للرئيسية', route('portal.home')))
                <a href="{{ $successHomeCta['url'] }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#feee00] hover:bg-[#e5d600] text-gray-900 font-bold rounded-xl text-sm transition-colors">
                    {{ $successHomeCta['label'] }}
                </a>
                @php($successSupportCta = portal_link('register', 'success', 'support_button', 'Contact Support', 'تواصل مع الدعم', 'mailto:vendors@noon.com'))
                <a href="{{ $successSupportCta['url'] }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium rounded-xl text-sm transition-colors">
                    {{ $successSupportCta['label'] }}
                </a>
            </div>

        </div>
    </div>
@endsection