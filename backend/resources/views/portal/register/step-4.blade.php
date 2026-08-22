{{-- Step 4: Document Uploads (dynamic per country) --}}
@php $isAr = $isAr ?? (session('locale', 'ar') === 'ar'); @endphp
<div class="space-y-5">
    <div>
        <h2 class="text-lg font-bold text-white">{{ portal_content('register', 'step_4', 'heading', 'Upload Official Documents', 'رفع الوثائق الرسمية') }}</h2>
        <p class="text-sm text-gray-400 mt-1">{{ portal_content('register', 'step_4', 'subtitle', 'Accepted formats and maximum size vary by document type.', 'الصيغ المقبولة والحجم الأقصى يختلفان حسب نوع الوثيقة.') }}</p>
    </div>

    {{-- Loading state --}}
    <div x-show="docTypesLoading" class="flex items-center gap-3 text-sm text-gray-400 py-6 justify-center" x-cloak>
        <svg class="animate-spin w-5 h-5 text-[#feee00]" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
        </svg>
        {{ portal_content('register', 'step_4', 'loading_label', 'Loading document requirements…', 'جارٍ تحميل متطلبات الوثائق…') }}
    </div>

    {{-- No doc types available (e.g. country config missing) --}}
    <div x-show="!docTypesLoading && docTypes.length === 0" class="text-sm text-gray-400 text-center py-4" x-cloak>
        {{ portal_content('register', 'step_4', 'no_docs_notice', 'No documents are required for this country — you may continue.', 'لا توجد وثائق مطلوبة لهذه الدولة، يمكنك المتابعة.') }}
    </div>

    {{-- Dynamic document list --}}
    <template x-for="doc in docTypes" :key="doc.code">
        <div class="bg-gray-800/50 rounded-xl p-4 border border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-sm font-semibold text-white">
                        <span x-text="doc.name_ar"></span>
                        <span x-show="doc.requirement_level === 'mandatory'" class="text-red-400"> *</span>
                        <span x-show="doc.requirement_level === 'optional'" class="text-gray-500 text-xs font-normal"> {{ portal_content('register', 'step_4', 'optional_label', '(optional)', '(اختياري)') }}</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="doc.description_ar || ''"></p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ portal_content('register', 'step_4', 'accepted_types_label', 'Accepted types:', 'الأنواع المقبولة:') }} <span x-text="doc.accepted_file_types.join('، ')"></span>
                        — {{ portal_content('register', 'step_4', 'max_size_label', 'Max size:', 'الحجم الأقصى:') }} <span x-text="Math.round(doc.max_file_size_kb / 1024) + 'MB'"></span>
                    </p>
                </div>
                <span x-show="documents[doc.code]" class="text-green-400 text-xs flex items-center gap-1 shrink-0 mr-2" x-cloak>
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ portal_content('register', 'step_4', 'uploaded_label', 'Uploaded', 'تم الرفع') }}
                </span>
            </div>

            {{-- File input (hidden, triggered by label) --}}
            <input
                type="file"
                :id="'doc_' + doc.code"
                :accept="'.' + doc.accepted_file_types.join(',.')"
                class="hidden"
                @change="uploadDocument($event, doc.code, doc)"
            >
            <label
                :for="'doc_' + doc.code"
                class="flex flex-col items-center justify-center border-2 border-dashed rounded-xl cursor-pointer transition-colors"
                :class="{
                    'py-5 px-4': doc.requirement_level === 'mandatory',
                    'py-3 px-4': doc.requirement_level === 'optional',
                    'border-green-600 bg-green-900/20': documents[doc.code],
                    'border-gray-600 hover:border-[#feee00]/60 bg-gray-800': !documents[doc.code]
                }"
            >
                <template x-if="!documents[doc.code]">
                    <div class="text-center">
                        <template x-if="doc.requirement_level === 'mandatory'">
                            <svg class="w-8 h-8 mx-auto text-gray-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                        </template>
                        <span class="text-sm text-gray-400" x-text="doc.requirement_level === 'optional' ? {{ Js::from(portal_content('register', 'step_4', 'choose_file_optional_label', 'Click to choose a file (optional)', 'انقر لاختيار الملف (اختياري)')) }} : {{ Js::from(portal_content('register', 'step_4', 'choose_file_label', 'Click to choose a file', 'انقر لاختيار الملف')) }}"></span>
                    </div>
                </template>
                <template x-if="documents[doc.code]">
                    <div class="text-center">
                        <svg class="w-8 h-8 mx-auto text-green-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm text-green-400 font-medium" x-text="docFilenames[doc.code] || {{ Js::from(portal_content('register', 'step_4', 'uploaded_label', 'Uploaded', 'تم الرفع')) }}"></span>
                        <button type="button" @click.prevent.stop="removeDocument(doc.code)"
                            class="block mx-auto mt-1 text-xs text-red-400 hover:underline">{{ portal_content('register', 'step_4', 'remove_label', 'Remove', 'حذف') }}</button>
                    </div>
                </template>
            </label>

            {{-- Expiry date (shown only when type requires it) --}}
            <div x-show="doc.requires_expiry_date && documents[doc.code]" class="mt-3" x-cloak>
                <label class="block text-xs text-gray-400 mb-1">
                    {{ portal_content('register', 'step_4', 'expiry_date_label', 'Expiry Date', 'تاريخ انتهاء الصلاحية') }} <span class="text-red-400">*</span>
                </label>
                <input
                    type="date"
                    :name="'doc_expiry_' + doc.code"
                    x-model="docExpiries[doc.code]"
                    class="w-full bg-gray-800 border border-gray-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-[#feee00]"
                >
            </div>

            {{-- Optional skip note --}}
            <p x-show="doc.requirement_level === 'optional' && !documents[doc.code]"
               class="mt-1.5 text-xs text-gray-500">
                {{ portal_content('register', 'step_4', 'optional_skip_note', '(Optional — can be added later from the dashboard)', '(اختياري — يمكن إضافتها لاحقاً من لوحة التحكم)') }}
            </p>

            {{-- Error --}}
            <p x-show="docErrors[doc.code]" x-text="docErrors[doc.code]" class="mt-1 text-xs text-red-400" x-cloak></p>

            {{-- Upload progress --}}
            <div x-show="docUploading[doc.code]" class="mt-2 flex items-center gap-2 text-xs text-[#feee00]" x-cloak>
                <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                {{ portal_content('register', 'step_4', 'uploading_label', 'Uploading…', 'جارٍ الرفع…') }}
            </div>
        </div>
    </template>

    {{-- Required docs error banner --}}
    <p x-show="errors.documents" x-text="errors.documents?.[0]" class="text-sm text-red-400 font-medium" x-cloak></p>
</div>
