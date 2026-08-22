@extends('layouts.storefront-mobile')

@section('title', $listing->title)

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@section('content')
<div class="flex-1 overflow-y-auto">

    {{-- Back nav --}}
    <div class="sticky top-0 z-10 bg-white border-b border-gray-100 flex items-center gap-3 px-4 py-3">
        <a href="javascript:history.back()" class="text-gray-500">
            <x-heroicon name="arrow-right" class="w-5 h-5" />
        </a>
        <h1 class="flex-1 text-sm font-bold text-gray-900 truncate">{{ $listing->title }}</h1>
    </div>

    {{-- Image carousel --}}
    @if($listing->images->isNotEmpty())
    <div class="relative aspect-video bg-gray-100 overflow-hidden">
        <div id="img-track" class="flex h-full transition-transform duration-300">
            @foreach($listing->images as $img)
            <div class="shrink-0 w-full h-full">
                <img src="{{ \Illuminate\Support\Facades\Storage::url($img->file_path) }}"
                     alt="" class="w-full h-full object-cover">
            </div>
            @endforeach
        </div>
        @if($listing->images->count() > 1)
        <button onclick="slideImg(-1)" class="absolute start-2 top-1/2 -translate-y-1/2 bg-black/40 text-white rounded-full p-1">
            <x-heroicon name="chevron-right" class="w-5 h-5" />
        </button>
        <button onclick="slideImg(1)" class="absolute end-2 top-1/2 -translate-y-1/2 bg-black/40 text-white rounded-full p-1">
            <x-heroicon name="chevron-left" class="w-5 h-5" />
        </button>
        @endif
    </div>
    @endif

    <div class="p-4 space-y-4">

        {{-- Title + purpose badge --}}
        <div class="flex items-start gap-2">
            <div class="flex-1">
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold mb-1
                             {{ $listing->listing_purpose === 'sale' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                    {{ $listing->listing_purpose === 'sale' ? 'للبيع' : 'للإيجار' }}
                </span>
                <h2 class="text-lg font-bold text-gray-900">{{ $listing->title }}</h2>
                <p class="text-2xl font-bold text-primary-600 mt-1">{{ $listing->price_formatted }}</p>
                @if($listing->price_negotiable)
                    <p class="text-xs text-gray-500 mt-0.5">السعر قابل للتفاوض</p>
                @endif
            </div>
        </div>

        {{-- Meta --}}
        <div class="flex flex-wrap gap-3 text-sm text-gray-600">
            <span class="flex items-center gap-1">
                <x-heroicon name="tag" class="w-4 h-4" />
                {{ $listing->classifiedCategory?->name }}
            </span>
            @if($listing->city)
            <span class="flex items-center gap-1">
                <x-heroicon name="map-pin" class="w-4 h-4" />
                {{ app()->getLocale() === 'ar' ? $listing->city->name_ar : $listing->city->name_en }}
            </span>
            @endif
            <span class="flex items-center gap-1">
                <x-heroicon name="eye" class="w-4 h-4" />
                {{ number_format($listing->views_count) }} مشاهدة
            </span>
            <span class="flex items-center gap-1">
                <x-heroicon name="calendar" class="w-4 h-4" />
                {{ $listing->created_at->diffForHumans() }}
            </span>
        </div>

        {{-- Category attributes --}}
        @if(!empty($listing->attributes))
        <div class="bg-gray-50 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">التفاصيل</h3>
            <dl class="grid grid-cols-2 gap-2">
                @foreach($listing->attributes as $key => $value)
                <div>
                    <dt class="text-xs text-gray-500">{{ str_replace('_', ' ', $key) }}</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $value }}</dd>
                </div>
                @endforeach
            </dl>
        </div>
        @endif

        {{-- Description --}}
        @if($listing->description_ar || $listing->description_en)
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">الوصف</h3>
            <p class="text-sm text-gray-600 leading-relaxed">
                {{ app()->getLocale() === 'ar' ? $listing->description_ar : $listing->description_en }}
            </p>
        </div>
        @endif

        {{-- Map --}}
        @if($listing->latitude && $listing->longitude)
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">الموقع</h3>
            <div id="detail-map" class="w-full h-48 rounded-xl overflow-hidden border border-gray-200"
                 data-lat="{{ $listing->latitude }}" data-lng="{{ $listing->longitude }}"
                 data-title="{{ $listing->title }}">
            </div>
        </div>
        @endif

        {{-- Sketch --}}
        @if($listing->sketch_file_path)
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-2">المخطط / الكروكي</h3>
            <a href="{{ \Illuminate\Support\Facades\Storage::url($listing->sketch_file_path) }}"
               target="_blank"
               class="flex items-center gap-2 p-3 border border-gray-200 rounded-xl text-sm text-primary-600">
                <x-heroicon name="document" class="w-5 h-5" />
                عرض المخطط
            </a>
        </div>
        @endif

        {{-- Barcode --}}
        @if($listing->barcode_path)
        <div class="flex justify-center">
            <img src="{{ \Illuminate\Support\Facades\Storage::url($listing->barcode_path) }}"
                 alt="{{ __('common.storefront.qr_code_alt') }}" class="w-32 h-32">
        </div>
        @endif

        {{-- Inquiry form --}}
        <div class="bg-primary-50 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">تواصل مع البائع</h3>
            <form id="inquiry-form" class="space-y-3">
                @csrf
                <textarea name="message" rows="3" placeholder="اكتب رسالتك هنا..."
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                <input name="contact_phone" type="tel" placeholder="رقم هاتفك (اختياري)"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <button type="submit"
                        class="w-full py-2.5 bg-primary-600 text-white rounded-lg text-sm font-semibold">
                    إرسال الاستفسار
                </button>
            </form>
            <p id="inquiry-success" class="hidden text-center text-sm text-emerald-700 mt-2 font-medium">
                تم إرسال استفسارك بنجاح ✓
            </p>
        </div>

        {{-- Related listings --}}
        @if($related->isNotEmpty())
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">إعلانات مشابهة</h3>
            <div class="grid grid-cols-2 gap-3">
                @foreach($related as $rel)
                <a href="{{ route('classifieds.show', [$country, $rel->listing_number]) }}"
                   class="bg-white border border-gray-100 rounded-xl overflow-hidden">
                    <div class="aspect-video bg-gray-100">
                        @if($rel->primary_image_url)
                            <img src="{{ $rel->primary_image_url }}" class="w-full h-full object-cover" loading="lazy">
                        @endif
                    </div>
                    <div class="p-2">
                        <p class="text-xs font-semibold text-gray-900 line-clamp-1">{{ $rel->title }}</p>
                        <p class="text-xs text-primary-600 font-bold mt-0.5">{{ $rel->price_formatted }}</p>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/storefront/classified-map.js') }}"></script>
<script>
    // Detail map
    const mapEl = document.getElementById('detail-map');
    if (mapEl) {
        initDetailMap('detail-map', {
            lat: parseFloat(mapEl.dataset.lat),
            lng: parseFloat(mapEl.dataset.lng),
            title: mapEl.dataset.title,
        });
    }

    // Image slider
    let current = 0;
    const track = document.getElementById('img-track');
    const total = track ? track.children.length : 0;
    function slideImg(dir) {
        current = (current + dir + total) % total;
        track.style.transform = `translateX(${current * -100}%)`;
    }

    // Inquiry
    const form = document.getElementById('inquiry-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const resp = await fetch('{{ route("classifieds.inquire", [$country, $listing->listing_number]) }}', {
                method: 'POST',
                body: fd,
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            if (resp.ok) {
                form.classList.add('hidden');
                document.getElementById('inquiry-success').classList.remove('hidden');
            }
        });
    }
</script>
@endpush
