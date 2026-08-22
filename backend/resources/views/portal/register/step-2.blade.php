{{-- Step 2: Store Information --}}
@php $isAr = $isAr ?? (session('locale', 'ar') === 'ar'); @endphp
<div class="space-y-4">
    <h2 class="text-lg font-bold text-white mb-4">{{ portal_content('register', 'step_2', 'heading', 'Store Information', 'معلومات المتجر') }}</h2>

    {{-- Store Name --}}
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_2', 'store_name_label', 'Store Name', 'اسم المتجر') }} <span
                class="text-red-400">*</span></label>
        <input type="text" x-model="form.store_name" @input="generateSlug($event.target.value)"
            placeholder="{{ portal_content('register', 'step_2', 'store_name_placeholder', 'Elegance Store', 'متجر الأناقة') }}"
            class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
            :class="errors.store_name ? 'border-red-500' : 'border-gray-700 focus:border-[#feee00]'">
        <p x-show="errors.store_name" x-text="errors.store_name?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
    </div>

    {{-- Store Slug --}}
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">
            {{ portal_content('register', 'step_2', 'store_slug_label', 'Store URL Slug', 'الرابط المختصر للمتجر') }} <span class="text-red-400">*</span>
            <span class="text-gray-500 font-normal text-xs mr-1">{{ portal_content('register', 'step_2', 'store_slug_hint', '(used in your store URL)', '(يُستخدم في الرابط)') }}</span>
        </label>
        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <input type="text" x-model="form.store_slug" @input.debounce.600ms="checkSlugAvailability()"
                    placeholder="my-store" dir="ltr"
                    class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
                    :class="{
                        'border-red-500': errors.store_slug || slugStatus === 'taken',
                        'border-green-500': slugStatus === 'available',
                        'border-gray-700 focus:border-[#feee00]': !errors.store_slug && !slugStatus
                    }">
                <span x-show="slugChecking" class="absolute inset-y-0 left-3 flex items-center" x-cloak>
                    <svg class="animate-spin w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                </span>
            </div>
        </div>
        <p x-show="slugStatus === 'available'" class="mt-1 text-xs text-green-400" x-cloak>{{ portal_content('register', 'step_2', 'store_slug_available', '✓ This URL is available', '✓ الرابط متاح') }}</p>
        <p x-show="slugStatus === 'taken'" class="mt-1 text-xs text-red-400" x-cloak>{{ portal_content('register', 'step_2', 'store_slug_taken', '✗ This URL is already taken, try another', '✗ هذا الرابط مستخدم، جرب آخر') }}</p>
        <p x-show="errors.store_slug" x-text="errors.store_slug?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
    </div>

    {{-- Store Description --}}
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_2', 'store_description_label', 'Store Description', 'وصف المتجر') }} <span
                class="text-gray-500 font-normal">{{ portal_content('register', 'step_2', 'optional_label', '(optional)', '(اختياري)') }}</span></label>
        <textarea x-model="form.store_description" rows="3" placeholder="{{ portal_content('register', 'step_2', 'store_description_placeholder', 'Briefly describe your store and products…', 'صف متجرك ومنتجاتك باختصار…') }}"
            class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors resize-none"></textarea>
    </div>

    {{-- Business Type --}}
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_2', 'business_type_label', 'Business Type', 'نوع النشاط التجاري') }} <span
                class="text-red-400">*</span></label>
        <select x-model="form.business_type"
            class="w-full bg-gray-800 border text-white rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
            :class="errors.business_type ? 'border-red-500' : 'border-gray-700 focus:border-[#feee00]'">
            <option value="individual">{{ portal_content('register', 'step_2', 'business_type_individual', 'Individual', 'فرد / شخصي') }}</option>
            <option value="sole_prop">{{ portal_content('register', 'step_2', 'business_type_sole_prop', 'Sole Proprietorship', 'مؤسسة فردية') }}</option>
            <option value="llc">{{ portal_content('register', 'step_2', 'business_type_llc', 'Limited Liability Company (LLC)', 'شركة ذات مسؤولية محدودة') }}</option>
            <option value="corp">{{ portal_content('register', 'step_2', 'business_type_corp', 'Corporation', 'شركة مساهمة') }}</option>
        </select>
        <p x-show="errors.business_type" x-text="errors.business_type?.[0]" class="mt-1 text-xs text-red-400" x-cloak>
        </p>
    </div>

    {{-- Business Name --}}
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_2', 'business_name_label', 'Official Business Name', 'الاسم التجاري الرسمي') }} <span
                class="text-red-400">*</span></label>
        <input type="text" x-model="form.business_name" placeholder="{{ portal_content('register', 'step_2', 'business_name_placeholder', 'Company name as officially registered', 'اسم الشركة كما هو مسجل رسمياً') }}"
            class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
            :class="errors.business_name ? 'border-red-500' : 'border-gray-700 focus:border-[#feee00]'">
        <p x-show="errors.business_name" x-text="errors.business_name?.[0]" class="mt-1 text-xs text-red-400" x-cloak>
        </p>
    </div>

    {{-- Row: Registration No + Tax ID --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_2', 'registration_number_label', 'Trade Registration Number', 'رقم السجل التجاري') }}</label>
            <input type="text" x-model="form.business_registration_number" placeholder="CR-XXXXXXXX" dir="ltr"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_2', 'tax_id_label', 'Tax ID', 'الرقم الضريبي') }}</label>
            <input type="text" x-model="form.tax_id" placeholder="XXXXXXXXXXXXXXXXX" dir="ltr"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
        </div>
    </div>
</div>