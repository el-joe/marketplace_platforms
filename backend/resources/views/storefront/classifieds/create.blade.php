@extends('layouts.storefront-mobile')

@section('title', 'إضافة إعلان جديد')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@section('content')
<div class="flex flex-col h-full overflow-hidden">

    {{-- Header --}}
    <div class="sticky top-0 z-10 bg-white border-b border-gray-100 flex items-center gap-3 px-4 py-3">
        <a href="javascript:history.back()" class="text-gray-500">
            <x-heroicon name="arrow-right" class="w-5 h-5" />
        </a>
        <h1 class="flex-1 text-sm font-bold text-gray-900">إضافة إعلان جديد</h1>
        <span id="step-badge" class="text-xs text-gray-400">الخطوة 1 من 6</span>
    </div>

    {{-- Progress bar --}}
    <div class="h-1 bg-gray-100">
        <div id="progress-bar" class="h-full bg-primary-500 transition-all duration-300" style="width:16.6%"></div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <form id="classified-form" enctype="multipart/form-data" class="p-4 space-y-4">
            @csrf

            {{-- ─── Step 1: Category ─────────────────────────────────────────── --}}
            <div data-step="1" class="space-y-4">
                <h2 class="text-base font-bold text-gray-900">اختر القسم</h2>
                <p class="text-sm text-gray-500">حدد نوع ما تريد نشر إعلانه</p>

                <div class="grid grid-cols-2 gap-3">
                    @foreach($categories as $cat)
                    <label class="category-card cursor-pointer">
                        <input type="radio" name="classified_category_id" value="{{ $cat->id }}"
                               data-requires-map="{{ $cat->requires_location_map ? 1 : 0 }}"
                               data-requires-sketch="{{ $cat->requires_sketch_upload ? 1 : 0 }}"
                               data-attachments="{{ json_encode($cat->required_attachment_types ?? []) }}"
                               class="sr-only category-radio">
                        <div class="border-2 border-gray-200 rounded-xl p-4 text-center transition-all hover:border-primary-300 selected:border-primary-500">
                            <div class="text-3xl mb-2">{{ $cat->icon ?: '📋' }}</div>
                            <p class="text-sm font-semibold text-gray-900">
                                {{ app()->getLocale() === 'ar' ? $cat->name_ar : $cat->name_en }}
                            </p>
                        </div>
                    </label>
                    @endforeach
                </div>

                <div class="pt-2">
                    <label class="text-sm font-medium text-gray-700">الغرض</label>
                    <div class="mt-2 flex gap-3">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="listing_purpose" value="sale" class="sr-only purpose-radio" checked>
                            <div class="purpose-card border-2 border-primary-500 bg-primary-50 rounded-xl p-3 text-center">
                                <p class="text-sm font-semibold text-primary-700">للبيع</p>
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="listing_purpose" value="rent" class="sr-only purpose-radio">
                            <div class="purpose-card border-2 border-gray-200 rounded-xl p-3 text-center">
                                <p class="text-sm font-semibold text-gray-600">للإيجار</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ─── Step 2: Details ──────────────────────────────────────────── --}}
            <div data-step="2" class="hidden space-y-4">
                <h2 class="text-base font-bold text-gray-900">تفاصيل الإعلان</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">العنوان بالعربية *</label>
                    <input type="text" name="title_ar" maxlength="255" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title in English *</label>
                    <input type="text" name="title_en" maxlength="255" required
                           class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الوصف (عربي)</label>
                    <textarea name="description_ar" rows="3"
                              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                    <textarea name="description_en" rows="3"
                              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"></textarea>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">السعر *</label>
                        <input type="number" name="price_display" min="0" step="0.01" required
                               placeholder="0.00"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                        <input type="hidden" name="price" id="price_input">
                    </div>
                    <div class="w-24">
                        <label class="block text-sm font-medium text-gray-700 mb-1">العملة</label>
                        <input type="text" name="currency" value="{{ $countryModel->currency_code ?? '' }}" maxlength="3"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="price_negotiable" id="price_negotiable" value="1" class="rounded">
                    <label for="price_negotiable" class="text-sm text-gray-700">السعر قابل للتفاوض</label>
                </div>

                {{-- Dynamic attributes container --}}
                <div id="dynamic-attributes" class="space-y-3"></div>
            </div>

            {{-- ─── Step 3: Location ─────────────────────────────────────────── --}}
            <div data-step="3" class="hidden space-y-4">
                <h2 class="text-base font-bold text-gray-900">الموقع</h2>

                <div id="location-picker-wrapper">
                    <p class="text-sm text-gray-500 mb-2">اسحب الدبوس لتحديد الموقع الدقيق</p>
                    <div id="location-picker" class="w-full h-64 rounded-xl overflow-hidden border border-gray-200"></div>
                    <input type="hidden" name="latitude" id="latitude_input">
                    <input type="hidden" name="longitude" id="longitude_input">
                    <p id="coords-display" class="text-xs text-gray-400 mt-1 text-center"></p>
                </div>

                <div id="sketch-upload-wrapper" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">المخطط / الكروكي</label>
                    <input type="file" name="sketch_file" accept=".pdf,.jpg,.jpeg,.png"
                           class="w-full text-sm text-gray-600 file:me-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">صور الإعلان</label>
                    <input type="file" name="images[]" accept="image/*" multiple
                           class="w-full text-sm text-gray-600 file:me-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700">
                    <p class="text-xs text-gray-400 mt-1">يمكنك رفع عدة صور. الصورة الأولى ستكون الرئيسية.</p>
                </div>
            </div>

            {{-- ─── Step 4: Attachments ──────────────────────────────────────── --}}
            <div data-step="4" class="hidden space-y-4">
                <h2 class="text-base font-bold text-gray-900">المستندات المطلوبة</h2>
                <p class="text-sm text-gray-500">يجب رفع المستندات التالية لإتمام الإعلان</p>
                <div id="attachments-container" class="space-y-3"></div>
            </div>

            {{-- ─── Step 5: Marketers ────────────────────────────────────────── --}}
            <div data-step="5" class="hidden space-y-4">
                <h2 class="text-base font-bold text-gray-900">التسويق بالعمولة</h2>
                <p class="text-sm text-gray-500">يمكنك اختيار مسوّقين لنشر إعلانك مقابل عمولة</p>

                <div class="flex gap-3">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="marketer_mode" value="skip" class="sr-only" checked>
                        <div class="marketer-mode-card border-2 border-gray-200 rounded-xl p-3 text-center">
                            <p class="text-sm font-semibold text-gray-600">تخطي</p>
                            <p class="text-xs text-gray-400 mt-0.5">بدون مسوّقين</p>
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="marketer_mode" value="all" class="sr-only">
                        <div class="marketer-mode-card border-2 border-gray-200 rounded-xl p-3 text-center">
                            <p class="text-sm font-semibold text-gray-600">جميع المسوّقين</p>
                            <p class="text-xs text-gray-400 mt-0.5">مفتوح للكل</p>
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="marketer_mode" value="specific" class="sr-only">
                        <div class="marketer-mode-card border-2 border-gray-200 rounded-xl p-3 text-center">
                            <p class="text-sm font-semibold text-gray-600">مسوّقين محددين</p>
                            <p class="text-xs text-gray-400 mt-0.5">اختر بالاسم</p>
                        </div>
                    </label>
                </div>

                <div id="commission-fields" class="hidden space-y-3">
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">نوع العمولة</label>
                            <select name="commission_type" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                                <option value="percentage">نسبة مئوية %</option>
                                <option value="fixed">مبلغ ثابت</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">القيمة</label>
                            <input type="number" name="commission_value" value="5" min="0" step="0.01"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                </div>

            </div>

            {{-- ─── Step 6: Contract ─────────────────────────────────────────── --}}
            <div data-step="6" class="hidden space-y-4">
                <h2 class="text-base font-bold text-gray-900">العقد الإلكتروني</h2>
                <p class="text-sm text-gray-500">يرجى قراءة العقد بعناية قبل التوقيع</p>

                <div id="contract-content"
                     class="max-h-64 overflow-y-auto bg-gray-50 rounded-xl p-4 text-sm text-gray-700 leading-relaxed border border-gray-200">
                    <p class="text-gray-400 text-center py-4">جاري تحميل العقد...</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">التوقيع (اكتب اسمك الكامل) *</label>
                    <input type="text" id="signature_name" placeholder="الاسم الكامل"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                </div>

                <div class="flex items-start gap-2">
                    <input type="checkbox" id="contract_agreed" class="mt-0.5 rounded">
                    <label for="contract_agreed" class="text-sm text-gray-700">
                        أوافق على شروط وأحكام هذا العقد وأتعهد بصحة البيانات المُدخلة
                    </label>
                </div>
            </div>

            {{-- Navigation buttons --}}
            <div class="flex gap-3 pt-4">
                <button type="button" id="prev-btn" onclick="prevStep()"
                        class="hidden flex-1 py-3 border border-gray-300 text-gray-700 rounded-xl text-sm font-semibold">
                    السابق
                </button>
                <button type="button" id="next-btn" onclick="nextStep()"
                        class="flex-1 py-3 bg-primary-600 text-white rounded-xl text-sm font-semibold">
                    التالي
                </button>
                <button type="button" id="submit-btn" onclick="submitListing()"
                        class="hidden flex-1 py-3 bg-emerald-600 text-white rounded-xl text-sm font-semibold">
                    نشر الإعلان
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/storefront/classified-map.js') }}"></script>
<script>
const DRAFT_URL   = '{{ route("classifieds.draft", $countryModel->iso_code_2) }}';
const SIGN_URL    = (id) => `/{{ $countryModel->iso_code_2 }}/classifieds/${id}/sign-contract`;
const CSRF        = document.querySelector('meta[name=csrf-token]').content;

let currentStep    = 1;
const totalSteps   = 6;
let savedListingId = null;
let categoryData   = {};

function showStep(n) {
    document.querySelectorAll('[data-step]').forEach(el => {
        el.classList.toggle('hidden', parseInt(el.dataset.step) !== n);
    });
    document.getElementById('step-badge').textContent = `الخطوة ${n} من ${totalSteps}`;
    document.getElementById('progress-bar').style.width = `${(n / totalSteps) * 100}%`;
    document.getElementById('prev-btn').classList.toggle('hidden', n === 1);
    document.getElementById('next-btn').classList.toggle('hidden', n === totalSteps);
    document.getElementById('submit-btn').classList.toggle('hidden', n !== totalSteps);
    currentStep = n;
    if (n === 3) initLocationPickerIfNeeded();
}

function prevStep() { if (currentStep > 1) showStep(currentStep - 1); }
function nextStep() {
    if (!validateStep(currentStep)) return;
    if (currentStep === 4) buildAttachmentsStep();
    if (currentStep < totalSteps) showStep(currentStep + 1);
    if (currentStep === 6) loadContractContent();
}

function validateStep(n) {
    if (n === 1) {
        const cat = document.querySelector('input[name=classified_category_id]:checked');
        if (!cat) { alert('يرجى اختيار القسم'); return false; }
        categoryData = {
            requiresMap:    cat.dataset.requiresMap === '1',
            requiresSketch: cat.dataset.requiresSketch === '1',
            attachments:    JSON.parse(cat.dataset.attachments || '[]'),
        };
        document.getElementById('sketch-upload-wrapper').classList.toggle('hidden', !categoryData.requiresSketch);
        buildDynamicAttributes(cat);
    }
    if (n === 2) {
        const ta = document.querySelector('[name=title_ar]').value.trim();
        const te = document.querySelector('[name=title_en]').value.trim();
        const pd = document.querySelector('[name=price_display]').value;
        if (!ta || !te || !pd) { alert('يرجى ملء الحقول المطلوبة'); return false; }
        document.getElementById('price_input').value = Math.round(parseFloat(pd));
    }
    return true;
}

// Dynamic attributes per category (basic demo, extend as needed)
function buildDynamicAttributes(catInput) {
    const container = document.getElementById('dynamic-attributes');
    container.innerHTML = '';
    // Could load from server; for now just a generic "attributes" field
    const examples = {
        'real-estate': [['bedrooms','غرف النوم'],['area_sqm','المساحة م²'],['floor','الطابق']],
        'cars':        [['make','الماركة'],['model','الموديل'],['year','سنة الصنع'],['mileage','المسافة المقطوعة']],
        'phone-numbers': [['phone_number','رقم الهاتف'],['carrier','شركة الاتصالات']],
    };
    // fallback to empty
}

function buildAttachmentsStep() {
    const container = document.getElementById('attachments-container');
    container.innerHTML = '';
    if (!categoryData.attachments.length) {
        container.innerHTML = '<p class="text-sm text-gray-400">لا توجد مستندات مطلوبة لهذا القسم.</p>';
        return;
    }
    categoryData.attachments.forEach(type => {
        const label = document.createElement('div');
        label.innerHTML = `
            <label class="block text-sm font-medium text-gray-700 mb-1">${type.replace(/_/g,' ')}</label>
            <input type="file" name="attachments[${type}]"
                   class="w-full text-sm text-gray-600 file:me-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700">
        `;
        container.appendChild(label);
    });
}

function loadContractContent() {
    // Contracts are loaded lazily after draft is saved.
    // For now just show a placeholder – the actual template is shown after storeDraft succeeds.
    document.getElementById('contract-content').innerHTML = `
        <h4 class="font-semibold mb-2">شروط وأحكام النشر</h4>
        <p>بالتوقيع على هذا العقد، تؤكد أن جميع المعلومات المُقدَّمة صحيحة ودقيقة، وتوافق على سياسات المنصة المتعلقة بالإعلانات المبوبة.</p>
        <p class="mt-2">تتعهد بعدم نشر أي معلومات مضللة أو مخالفة للأنظمة والقوانين المعمول بها.</p>
    `;
}

async function submitListing() {
    const agreed = document.getElementById('contract_agreed').checked;
    const sigName = document.getElementById('signature_name').value.trim();
    if (!agreed || !sigName) {
        alert('يرجى قراءة العقد والتوقيع عليه');
        return;
    }

    const form = document.getElementById('classified-form');
    const fd   = new FormData(form);
    document.getElementById('submit-btn').disabled = true;
    document.getElementById('submit-btn').textContent = 'جاري الإرسال...';

    try {
        // 1. Save draft
        const draftResp = await fetch(DRAFT_URL, {
            method: 'POST', body: fd,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        const draftData = await draftResp.json();
        if (!draftData.success) throw new Error(JSON.stringify(draftData.errors ?? draftData));

        savedListingId = draftData.listing_id;

        // 2. Sign contract
        const signFd = new FormData();
        signFd.append('signature_name', sigName);
        const signResp = await fetch(SIGN_URL(savedListingId), {
            method: 'POST', body: signFd,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        const signData = await signResp.json();
        if (!signData.success) throw new Error('فشل في حفظ التوقيع');

        alert('تم إرسال إعلانك بنجاح! سيتم مراجعته قريباً.');
        window.location.href = '{{ route("classifieds.index", $countryModel->iso_code_2) }}';
    } catch (err) {
        alert('حدث خطأ: ' + err.message);
        document.getElementById('submit-btn').disabled = false;
        document.getElementById('submit-btn').textContent = 'نشر الإعلان';
    }
}

// Marketer mode switcher
document.querySelectorAll('input[name=marketer_mode]').forEach(radio => {
    radio.addEventListener('change', () => {
        const mode = radio.value;
        document.getElementById('commission-fields').classList.toggle('hidden', mode === 'skip');
        document.querySelectorAll('.marketer-mode-card').forEach(c => {
            c.classList.remove('border-primary-500','bg-primary-50');
            c.classList.add('border-gray-200');
        });
        radio.closest('label').querySelector('.marketer-mode-card').classList.add('border-primary-500','bg-primary-50');
    });
});

// Category radio styles
document.querySelectorAll('.category-radio').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('.category-card div').forEach(d => {
            d.classList.remove('border-primary-500','bg-primary-50');
            d.classList.add('border-gray-200');
        });
        radio.closest('label').querySelector('div').classList.add('border-primary-500','bg-primary-50');
    });
});

// Purpose radio styles
document.querySelectorAll('.purpose-radio').forEach(radio => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('.purpose-card').forEach(d => {
            d.classList.remove('border-primary-500','bg-primary-50');
            d.classList.add('border-gray-200');
        });
        radio.closest('label').querySelector('.purpose-card').classList.add('border-primary-500','bg-primary-50');
    });
});

let locationPickerReady = false;
function initLocationPickerIfNeeded() {
    if (locationPickerReady) return;
    initLocationPicker('location-picker', (lat, lng) => {
        document.getElementById('latitude_input').value  = lat;
        document.getElementById('longitude_input').value = lng;
        document.getElementById('coords-display').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    });
    locationPickerReady = true;
}

showStep(1);
</script>
@endpush
