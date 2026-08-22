@extends('layouts.storefront')

@section('title', 'جرّب المنتج افتراضياً')

@section('content')
<div class="max-w-lg mx-auto py-10 px-4 space-y-6">

    <div class="text-center space-y-1">
        <h1 class="text-2xl font-bold text-gray-900">جرّب المنتج افتراضياً</h1>
        <p class="text-sm text-gray-500">ارفع صورتك وسنُريك كيف يبدو المنتج عليك</p>
    </div>

    {{-- Product preview --}}
    @if($productImage)
    <div class="flex justify-center">
        <img src="{{ asset('storage/' . $productImage->path) }}"
             class="h-48 object-contain rounded-2xl border shadow-sm"
             alt="{{ $listing->productVariant?->product?->name }}">
    </div>
    @endif

    {{-- Upload form --}}
    <div id="upload-section" class="space-y-4">
        <div class="border-2 border-dashed border-gray-300 rounded-2xl p-6 text-center space-y-3" id="drop-zone">
            <div class="text-4xl">🤳</div>
            <p class="text-sm text-gray-600 font-medium">اسحب صورتك هنا أو اضغط للاختيار</p>
            <p class="text-xs text-gray-400">JPG, PNG, WEBP — حتى 10 ميجابايت</p>
            <input type="file" id="photo-input" accept="image/*" class="hidden">
            <button onclick="document.getElementById('photo-input').click()"
                class="bg-blue-600 text-white rounded-xl px-5 py-2 text-sm font-medium hover:bg-blue-700 transition">
                اختر صورة
            </button>
        </div>

        <div id="preview-section" class="hidden space-y-3">
            <img id="photo-preview" src="" class="w-full max-h-64 object-contain rounded-2xl border">
            <button onclick="submitTryOn()"
                class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-2xl py-3 font-semibold text-sm hover:opacity-90 transition">
                ✨ جرّب المنتج الآن
            </button>
        </div>
    </div>

    {{-- Processing state --}}
    <div id="processing-section" class="hidden text-center space-y-4 py-8">
        <div class="flex justify-center">
            <svg class="animate-spin h-10 w-10 text-purple-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
        </div>
        <p class="text-sm text-gray-600 font-medium">الذكاء الاصطناعي يعالج صورتك…</p>
        <p class="text-xs text-gray-400">قد يستغرق هذا 30–60 ثانية</p>
    </div>

    {{-- Result state --}}
    <div id="result-section" class="hidden space-y-4">
        <h2 class="text-center font-bold text-green-700">✓ النتيجة جاهزة!</h2>

        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1 text-center">
                <p class="text-xs text-gray-500 font-medium">صورتك</p>
                <img id="original-thumb" src="" class="w-full h-48 object-cover rounded-xl border">
            </div>
            <div class="space-y-1 text-center">
                <p class="text-xs text-purple-600 font-medium">مع المنتج</p>
                <img id="result-img" src="" class="w-full h-48 object-cover rounded-xl border-2 border-purple-400 shadow-md">
            </div>
        </div>

        <div class="flex gap-2">
            <a id="download-link" href="#" download
               class="flex-1 text-center bg-green-600 text-white rounded-xl py-2 text-sm font-medium hover:bg-green-700">
                حفظ الصورة
            </a>
            <button onclick="resetTryOn()"
                class="flex-1 bg-gray-100 text-gray-700 rounded-xl py-2 text-sm font-medium hover:bg-gray-200">
                جرّب صورة أخرى
            </button>
        </div>
    </div>

    {{-- Error state --}}
    <div id="error-section" class="hidden text-center py-6 space-y-3">
        <div class="text-3xl">⚠️</div>
        <p id="error-msg" class="text-sm text-red-600"></p>
        <button onclick="resetTryOn()" class="text-sm text-blue-600 underline">حاول مجدداً</button>
    </div>

</div>

@push('scripts')
<script>
const listingId = '{{ $listing->id }}';
let sessionId = null;
let pollTimer = null;

const photoInput = document.getElementById('photo-input');

photoInput.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('photo-preview').src = e.target.result;
        document.getElementById('original-thumb').src = e.target.result;
        document.getElementById('preview-section').classList.remove('hidden');
    };
    reader.readAsDataURL(file);
});

function submitTryOn() {
    const file = photoInput.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('photo', file);
    formData.append('_token', document.querySelector('meta[name=csrf-token]').content);

    document.getElementById('upload-section').classList.add('hidden');
    document.getElementById('processing-section').classList.remove('hidden');

    fetch(`/try-on/${listingId}`, {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        sessionId = data.session_id;
        pollTimer = setInterval(pollStatus, 3000);
    })
    .catch(() => showError('فشل رفع الصورة. يرجى المحاولة مجدداً.'));
}

function pollStatus() {
    fetch(`/try-on/status/${sessionId}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'completed') {
                clearInterval(pollTimer);
                document.getElementById('result-img').src = data.result_url;
                document.getElementById('download-link').href = data.result_url;
                document.getElementById('processing-section').classList.add('hidden');
                document.getElementById('result-section').classList.remove('hidden');
            } else if (data.status === 'failed') {
                clearInterval(pollTimer);
                showError(data.error || 'حدث خطأ أثناء المعالجة.');
            }
        });
}

function showError(msg) {
    document.getElementById('processing-section').classList.add('hidden');
    document.getElementById('error-msg').textContent = msg;
    document.getElementById('error-section').classList.remove('hidden');
}

function resetTryOn() {
    clearInterval(pollTimer);
    sessionId = null;
    photoInput.value = '';
    ['processing-section', 'result-section', 'error-section', 'preview-section'].forEach(id => {
        document.getElementById(id).classList.add('hidden');
    });
    document.getElementById('upload-section').classList.remove('hidden');
}
</script>
@endpush
@endsection
