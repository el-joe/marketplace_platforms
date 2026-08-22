@php
    $isAr = session('locale', 'ar') === 'ar';
    $success = session('advertise_request_success');
    $country = $country ?? 'ae';

    $fieldClass = fn (string $field) => 'w-full rounded-xl border px-4 py-3 text-sm text-gray-900 placeholder-gray-400 '
        . 'focus:outline-none focus:ring-2 focus:ring-[#feee00]/30 transition-colors '
        . ($errors->has($field) ? 'border-red-400 focus:border-red-500' : 'border-gray-200 focus:border-[#feee00]');

    $formIcon = portal_image('advertise-request', 'form', 'icon', 'https://advertise.noon.com/images/contactUs.png', 'Contact us', 'اتصل بنا');
@endphp

<section class="bg-white">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-20">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-start">

            {{-- Left: intro --}}
            <div class="text-center {{ $isAr ? 'lg:text-right' : 'lg:text-left' }} lg:sticky lg:top-28">
                <div class="w-16 h-16 rounded-2xl bg-yellow-50 flex items-center justify-center mx-auto {{ $isAr ? 'lg:mr-0 lg:ml-auto' : 'lg:ml-0 lg:mr-auto' }} mb-6">
                    <img src="{{ $formIcon['src'] }}" alt="{{ $formIcon['alt'] }}"
                         loading="lazy" class="w-9 h-9 object-contain">
                </div>
                <p class="text-yellow-500 font-black text-xs sm:text-sm uppercase tracking-wider mb-3">
                    {{ portal_content('advertise-request', 'form', 'eyebrow', 'Contact us', 'اتصل بنا') }}
                </p>
                <h1 class="text-2xl sm:text-3xl lg:text-[34px] font-extrabold text-gray-900 mb-4 text-pretty">
                    {{ portal_content('advertise-request', 'form', 'title', "Let's start a conversation", 'لنبدأ محادثة') }}
                </h1>
                <p class="text-gray-600 font-medium text-base sm:text-lg max-w-[48ch] mx-auto {{ $isAr ? 'lg:mr-0' : 'lg:ml-0' }}">
                    {{ portal_content('advertise-request', 'form', 'subtitle', 'Leverage our ad solutions today to reach your marketing or sales objectives across locations and irrespective of your budget.', 'استفد من حلولنا الإعلانية اليوم للوصول إلى أهدافك التسويقية أو البيعية عبر المواقع بغض النظر عن ميزانيتك.') }}
                </p>
            </div>

            {{-- Right: form card --}}
            <div class="w-full rounded-2xl border border-gray-100 shadow-sm p-6 sm:p-8 lg:p-10">

                @if ($success)
                    <div class="mb-6 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm font-semibold px-4 py-3">
                        {{ portal_content('advertise-request', 'form', 'success_message', 'Your request has been accepted. We will contact you soon.', 'تم قبول طلبك. سيتم التواصل معك قريبًا.') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold px-4 py-3">
                        {{ portal_content('advertise-request', 'form', 'error_message', 'An error occurred. Please try again later.', 'حدث خطأ ما. يرجى المحاولة مرة أخرى لاحقًا.') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('portal.advertise.request.store', $country) }}" class="space-y-5">
                    @csrf

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-sm font-bold text-gray-900 mb-1.5">
                            {{ portal_content('advertise-request', 'form', 'name_label', 'Name', 'الاسم') }}<span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="255"
                               placeholder="{{ portal_content('advertise-request', 'form', 'name_placeholder', 'Please enter your full name!', 'يرجى إدخال اسمك الكامل!') }}"
                               class="{{ $fieldClass('name') }}">
                        @error('name') <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-sm font-bold text-gray-900 mb-1.5">
                            {{ portal_content('advertise-request', 'form', 'email_label', 'Email', 'البريد الإلكتروني') }}<span class="text-red-500">*</span>
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required dir="ltr" maxlength="255"
                               placeholder="{{ portal_content('advertise-request', 'form', 'email_placeholder', 'Please enter a valid email address!', 'يرجى إدخال عنوان بريد إلكتروني صالح!') }}"
                               class="{{ $fieldClass('email') }} text-left">
                        @error('email') <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Company name --}}
                    <div>
                        <label for="company_name" class="block text-sm font-bold text-gray-900 mb-1.5">
                            {{ portal_content('advertise-request', 'form', 'company_label', 'Company Name', 'اسم الشركة') }}<span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}" required maxlength="255"
                               placeholder="{{ portal_content('advertise-request', 'form', 'company_placeholder', 'Please enter your company name!', 'يرجى إدخال اسم شركتك!') }}"
                               class="{{ $fieldClass('company_name') }}">
                        @error('company_name') <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Phone (optional) --}}
                    <div>
                        <label for="phone" class="block text-sm font-bold text-gray-900 mb-1.5">
                            {{ portal_content('advertise-request', 'form', 'phone_label', 'Phone Number (optional)', 'رقم الجوال (اختياري)') }}
                        </label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" dir="ltr" maxlength="16"
                               placeholder="{{ portal_content('advertise-request', 'form', 'phone_placeholder', 'Please enter your phone number with country code. eg: 9715XXXXXXXX', 'أدخل رقم جوالك مع رمز الدولة، مثال: 9715XXXXXXXX') }}"
                               class="{{ $fieldClass('phone') }} text-left">
                        @error('phone') <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label for="description" class="block text-sm font-bold text-gray-900 mb-1.5">
                            {{ portal_content('advertise-request', 'form', 'description_label', 'Description', 'الوصف') }}<span class="text-red-500">*</span>
                        </label>
                        <textarea id="description" name="description" rows="5" minlength="50" maxlength="1000" required
                                  placeholder="{{ portal_content('advertise-request', 'form', 'description_placeholder', 'Please enter your request details in the description!', 'يرجى إدخال تفاصيل الطلب في الوصف!') }}"
                                  class="{{ $fieldClass('description') }} resize-none">{{ old('description') }}</textarea>
                        @error('description') <p class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit"
                            class="w-full inline-flex items-center justify-center bg-[#feee00] hover:bg-[#e5d600] text-black
                                   font-black text-sm sm:text-base px-6 py-3 rounded-full transition-colors">
                        {{ portal_content('advertise-request', 'form', 'submit_button', 'Submit', 'قدّم') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
