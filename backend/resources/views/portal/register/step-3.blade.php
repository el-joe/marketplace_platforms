{{-- Step 3: Contact & Address --}}
@php $isAr = $isAr ?? (session('locale', 'ar') === 'ar'); @endphp
<div class="space-y-4">
    <h2 class="text-lg font-bold text-white mb-4">{{ portal_content('register', 'step_3', 'heading', 'Contact & Address Information', 'بيانات التواصل والعنوان') }}</h2>

    {{-- Contact Email --}}
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'contact_email_label', 'Contact Email', 'البريد الإلكتروني للتواصل') }} <span
                class="text-red-400">*</span></label>
        <input type="email" x-model="form.contact_email" placeholder="orders@mystore.com" dir="ltr"
            class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors text-left"
            :class="errors.contact_email ? 'border-red-500' : 'border-gray-700 focus:border-[#feee00]'">
        <p x-show="errors.contact_email" x-text="errors.contact_email?.[0]" class="mt-1 text-xs text-red-400" x-cloak>
        </p>
    </div>

    {{-- Row: Contact Phone + WhatsApp --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'contact_phone_label', 'Contact Phone', 'هاتف التواصل') }} <span
                    class="text-red-400">*</span></label>
            <input type="tel" x-model="form.contact_phone" placeholder="+971 50 000 0000" dir="ltr"
                class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
                :class="errors.contact_phone ? 'border-red-500' : 'border-gray-700 focus:border-[#feee00]'">
            <p x-show="errors.contact_phone" x-text="errors.contact_phone?.[0]" class="mt-1 text-xs text-red-400"
                x-cloak></p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'whatsapp_label', 'WhatsApp', 'واتساب') }} <span
                    class="text-gray-500 text-xs font-normal">{{ portal_content('register', 'step_3', 'optional_label', '(optional)', '(اختياري)') }}</span></label>
            <input type="tel" x-model="form.whatsapp_number" placeholder="+971 50 000 0000" dir="ltr"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
        </div>
    </div>

    <div class="border-t border-gray-700 pt-4">
        <h3 class="text-sm font-semibold text-gray-300 mb-3">{{ portal_content('register', 'step_3', 'address_heading', 'Business Address', 'عنوان النشاط التجاري') }}</h3>
    </div>

    {{-- City (AJAX loaded based on country from step 1) --}}
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'city_label', 'City', 'المدينة') }} <span
                class="text-red-400">*</span></label>
        <select x-model="form.city_id"
            class="w-full bg-gray-800 border text-white rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
            :class="errors.city_id ? 'border-red-500' : 'border-gray-700 focus:border-[#feee00]'">
            <option value="">{{ portal_content('register', 'step_3', 'city_placeholder', '— Select City —', '— اختر المدينة —') }}</option>
            <template x-for="city in cities" :key="city.id">
                <option :value="city.id" x-text="city.name_ar"></option>
            </template>
        </select>
        <p x-show="errors.city_id" x-text="errors.city_id?.[0]" class="mt-1 text-xs text-red-400" x-cloak></p>
        <p x-show="cities.length === 0 && form.country_id" class="mt-1 text-xs text-[#feee00]" x-cloak>{{ portal_content('register', 'step_3', 'no_cities_notice', 'No cities are currently registered for this country.', 'لا توجد مدن مسجلة لهذه الدولة حالياً.') }}</p>
    </div>

    {{-- Row: Area + Street Address --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'area_label', 'District / Area', 'الحي / المنطقة') }}</label>
            <input type="text" x-model="form.area" placeholder="{{ portal_content('register', 'step_3', 'area_placeholder', 'Al Rawda District', 'حي الروضة') }}"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'street_label', 'Street Name', 'اسم الشارع') }} <span
                    class="text-red-400">*</span></label>
            <input type="text" x-model="form.street_address" placeholder="{{ portal_content('register', 'step_3', 'street_placeholder', 'King Fahd Street', 'شارع الملك فهد') }}"
                class="w-full bg-gray-800 border text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none transition-colors"
                :class="errors.street_address ? 'border-red-500' : 'border-gray-700 focus:border-[#feee00]'">
            <p x-show="errors.street_address" x-text="errors.street_address?.[0]" class="mt-1 text-xs text-red-400"
                x-cloak></p>
        </div>
    </div>

    {{-- Row: Building + Floor + Apartment + Postal Code --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'building_label', 'Building', 'المبنى / البناية') }}</label>
            <input type="text" x-model="form.building" placeholder="{{ portal_content('register', 'step_3', 'building_placeholder', 'Business Tower', 'برج الأعمال') }}"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'floor_label', 'Floor', 'الطابق') }}</label>
            <input type="text" x-model="form.floor" placeholder="3" dir="ltr"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'apartment_label', 'Office / Apartment Number', 'رقم المكتب / الشقة') }}</label>
            <input type="text" x-model="form.apartment" placeholder="305" dir="ltr"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ portal_content('register', 'step_3', 'postal_code_label', 'Postal Code', 'الرمز البريدي') }}</label>
            <input type="text" x-model="form.postal_code" placeholder="12345" dir="ltr"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-[#feee00] transition-colors">
        </div>
    </div>
</div>