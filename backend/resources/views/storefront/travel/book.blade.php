@extends('layouts.storefront')

@section('title', 'حجز: ' . ($package->title_ar ?: $package->title_en))

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 space-y-6">

    <h1 class="text-2xl font-black text-gray-900">إتمام الحجز</h1>

    {{-- Package summary --}}
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 flex gap-4">
        @php $cover = $package->coverImage(); @endphp
        @if($cover)
        <img src="{{ $cover->url() }}" class="w-20 h-20 rounded-xl object-cover flex-shrink-0">
        @endif
        <div>
            <p class="font-bold text-gray-900">{{ $package->title_ar ?: $package->title_en }}</p>
            <p class="text-sm text-blue-600">{{ $package->destination_country }} · {{ $package->departure_date->format('d M Y') }}</p>
            <p class="text-sm font-medium text-gray-700 mt-1">{{ $package->priceFormatted() }} <span class="font-normal text-gray-500">للفرد</span></p>
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 space-y-1">
        @foreach($errors->all() as $error)<p>• {{ $error }}</p>@endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('travel.book.submit', $package) }}"
          enctype="multipart/form-data" class="space-y-6" id="booking-form">
        @csrf

        {{-- Travelers count --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-bold text-gray-900 mb-4">عدد المسافرين</h3>
            <div class="flex items-center gap-4">
                <button type="button" id="dec-btn"
                        class="w-10 h-10 rounded-full border-2 border-gray-300 text-gray-600 text-xl font-bold hover:border-blue-400 hover:text-blue-600 flex items-center justify-center">
                    −
                </button>
                <input type="number" name="travelers_count" id="travelers-count" value="1" min="1" max="{{ $package->seatsRemaining() ?? 20 }}"
                       class="w-16 text-center text-2xl font-black text-gray-900 border-0 focus:outline-none">
                <button type="button" id="inc-btn"
                        class="w-10 h-10 rounded-full border-2 border-gray-300 text-gray-600 text-xl font-bold hover:border-blue-400 hover:text-blue-600 flex items-center justify-center">
                    +
                </button>
                <div class="mr-4">
                    <p class="text-sm text-gray-500">الإجمالي</p>
                    <p id="total-price" class="text-xl font-black text-blue-600">{{ $package->priceFormatted() }}</p>
                </div>
            </div>
        </div>

        {{-- Passport upload --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-bold text-gray-900">صورة جواز السفر *</h3>
                <p class="text-sm text-gray-500 mt-1">ارفع صورة واضحة من صفحة البيانات الشخصية في جواز السفر</p>
            </div>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition-colors cursor-pointer" onclick="document.getElementById('passport-input').click()">
                <div id="passport-preview" class="hidden mb-3">
                    <img id="passport-img" class="mx-auto max-h-32 rounded-lg">
                </div>
                <div id="passport-placeholder">
                    <p class="text-4xl mb-2">📄</p>
                    <p class="text-sm text-gray-500">انقر لرفع صورة جواز السفر</p>
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, PDF — الحد الأقصى 5MB</p>
                </div>
                <input type="file" id="passport-input" name="passport_file" accept=".jpg,.jpeg,.png,.pdf" required class="hidden"
                       onchange="previewPassport(this)">
            </div>
            @error('passport_file') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
        </div>

        {{-- Contract / E-signature --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <h3 class="font-bold text-gray-900">توقيع العقد إلكترونياً *</h3>

            <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-700 max-h-40 overflow-y-auto leading-relaxed">
                <p class="font-semibold mb-2">شروط وأحكام الباقة السياحية</p>
                <p>يوافق العميل على الشروط والأحكام الخاصة بهذه الباقة السياحية، بما يشمل سياسة الإلغاء والاسترداد والتغييرات على البرنامج. تسري هذه الاتفاقية بين العميل ووكالة {{ $package->agency->name }} المعتمدة.</p>
                <p class="mt-2">سياسة الإلغاء: الإلغاء قبل 30 يوماً من السفر يسترد 80٪ من المبلغ. الإلغاء قبل 15 يوماً يسترد 50٪. لا يسترد أي مبلغ عند الإلغاء خلال أقل من 15 يوماً من موعد السفر.</p>
            </div>

            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">وقّع هنا بكتابة اسمك الكامل كما في جواز السفر</p>
                <canvas id="signature-canvas" width="600" height="120"
                        class="w-full border-2 border-gray-300 rounded-xl cursor-crosshair bg-white"></canvas>
                <div class="flex gap-2 mt-2">
                    <button type="button" onclick="clearSignature()"
                            class="text-xs text-gray-500 hover:text-red-500 border border-gray-300 rounded-lg px-3 py-1.5">
                        مسح التوقيع
                    </button>
                </div>
                <input type="hidden" name="contract_signature_data" id="signature-data">
                @error('contract_signature_data') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Submit --}}
        <button type="submit" onclick="captureSignature()"
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-2xl text-lg transition-colors">
            تأكيد الحجز ودفع {{ $package->priceFormatted() }} × <span id="submit-count">1</span>
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
const priceCents = {{ $package->price }};
const currency   = '{{ $package->currency }}';
let count = 1;

document.getElementById('dec-btn').addEventListener('click', () => {
    if (count > 1) { count--; updateCount(); }
});
document.getElementById('inc-btn').addEventListener('click', () => {
    count++;
    updateCount();
});
document.getElementById('travelers-count').addEventListener('input', e => {
    count = parseInt(e.target.value) || 1;
    updateCount();
});

function updateCount() {
    document.getElementById('travelers-count').value = count;
    document.getElementById('submit-count').textContent = count;
    const total = (priceCents * count / 100).toFixed(2);
    document.getElementById('total-price').textContent = currency + ' ' + Number(total).toLocaleString('ar-SA');
}

function previewPassport(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('passport-img').src = e.target.result;
                document.getElementById('passport-preview').classList.remove('hidden');
                document.getElementById('passport-placeholder').classList.add('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('passport-placeholder').querySelector('p').textContent = '📄 ' + file.name;
        }
    }
}

// Canvas signature
const canvas = document.getElementById('signature-canvas');
const ctx    = canvas.getContext('2d');
let drawing  = false;

canvas.addEventListener('mousedown', e => { drawing = true; ctx.beginPath(); ctx.moveTo(pos(e).x, pos(e).y); });
canvas.addEventListener('mousemove', e => { if (!drawing) return; ctx.lineTo(pos(e).x, pos(e).y); ctx.stroke(); });
canvas.addEventListener('mouseup', () => drawing = false);
canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; const t = e.touches[0]; ctx.beginPath(); ctx.moveTo(pos(t).x, pos(t).y); }, {passive:false});
canvas.addEventListener('touchmove', e => { e.preventDefault(); if (!drawing) return; const t = e.touches[0]; ctx.lineTo(pos(t).x, pos(t).y); ctx.stroke(); }, {passive:false});
canvas.addEventListener('touchend', () => drawing = false);

ctx.strokeStyle = '#1e40af';
ctx.lineWidth   = 2;
ctx.lineCap     = 'round';

function pos(e) {
    const r = canvas.getBoundingClientRect();
    return { x: (e.clientX - r.left) * (canvas.width / r.width), y: (e.clientY - r.top) * (canvas.height / r.height) };
}

function clearSignature() { ctx.clearRect(0, 0, canvas.width, canvas.height); }
function captureSignature() { document.getElementById('signature-data').value = canvas.toDataURL(); }
</script>
@endpush
