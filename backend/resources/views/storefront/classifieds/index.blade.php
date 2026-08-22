@extends('layouts.storefront-mobile')

@section('title', __('common.storefront.classifieds_title'))

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@section('content')
<div class="flex h-full overflow-hidden">

    {{-- ─── Sidebar: categories ───────────────────────────────────────────── --}}
    <aside class="hidden md:flex flex-col w-56 shrink-0 border-e border-gray-100 bg-white overflow-y-auto">
        <div class="px-4 pt-5 pb-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">الأقسام</p>
        </div>
        <nav class="flex-1 px-2 pb-4 space-y-0.5">
            <a href="{{ route('classifieds.index', $countryModel->iso_code_2) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors
                      {{ !request('category') ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                <x-heroicon name="squares-2x2" class="w-5 h-5 shrink-0" />
                الكل
            </a>
            @foreach($categories as $cat)
            <a href="{{ route('classifieds.index', $countryModel->iso_code_2) }}?category={{ $cat->slug }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors
                      {{ request('category') === $cat->slug ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-600 hover:bg-gray-50' }}">
                @if($cat->icon)
                    <span class="text-lg">{{ $cat->icon }}</span>
                @else
                    <x-heroicon name="tag" class="w-5 h-5 shrink-0" />
                @endif
                {{ app()->getLocale() === 'ar' ? $cat->name_ar : $cat->name_en }}
            </a>
            @endforeach
        </nav>
    </aside>

    {{-- ─── Main ──────────────────────────────────────────────────────────── --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- Header + search --}}
        <div class="sticky top-0 z-10 bg-white border-b border-gray-100 px-4 py-3 space-y-2">
            <div class="flex items-center justify-between">
                <h1 class="text-base font-bold text-gray-900">الإعلانات المبوبة</h1>
                @auth('customer')
                <a href="{{ route('classifieds.create', $countryModel->iso_code_2) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 text-white rounded-lg text-sm font-medium">
                    <x-heroicon name="plus" class="w-4 h-4" />
                    أضف إعلانك
                </a>
                @endauth
            </div>
            <form method="GET" class="flex gap-2">
                <input name="q" value="{{ request('q') }}" placeholder="ابحث في الإعلانات..."
                       class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                <button type="submit" class="px-3 py-2 bg-primary-600 text-white rounded-lg">
                    <x-heroicon name="magnifying-glass" class="w-4 h-4" />
                </button>
            </form>
        </div>

        {{-- Filter pills --}}
        <div class="flex gap-2 px-4 py-2.5 overflow-x-auto bg-white border-b border-gray-100">
            @foreach([null => 'الكل', 'sale' => 'للبيع', 'rent' => 'للإيجار'] as $val => $label)
            <a href="{{ request()->fullUrlWithQuery(['purpose' => $val]) }}"
               class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap
                      {{ request('purpose') == $val ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

        {{-- Map toggle --}}
        <div class="px-4 py-2 flex justify-end bg-gray-50 border-b border-gray-100">
            <button onclick="toggleMap()" id="map-toggle-btn"
                    class="inline-flex items-center gap-1.5 text-xs text-primary-600 font-medium">
                <x-heroicon name="map" class="w-4 h-4" />
                عرض الخريطة
            </button>
        </div>

        {{-- Map container (hidden by default) --}}
        <div id="map-container" class="hidden h-64 w-full" data-map-url="{{ route('classifieds.map-data', $countryModel->iso_code_2) }}">
            <div id="browse-map" class="w-full h-full"></div>
        </div>

        {{-- Grid --}}
        <div class="flex-1 overflow-y-auto">
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse($listings as $listing)
                <a href="{{ route('classifieds.show', [$countryModel->iso_code_2, $listing->listing_number]) }}"
                   class="bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                    {{-- Image --}}
                    <div class="relative aspect-video bg-gray-100">
                        @if($listing->primary_image_url)
                            <img src="{{ $listing->primary_image_url }}" alt="{{ $listing->title }}"
                                 class="w-full h-full object-cover" loading="lazy">
                        @else
                            <div class="flex items-center justify-center h-full text-gray-300">
                                <x-heroicon name="photo" class="w-12 h-12" />
                            </div>
                        @endif
                        <span class="absolute top-2 start-2 px-2 py-0.5 rounded-full text-xs font-semibold
                                     {{ $listing->listing_purpose === 'sale' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $listing->listing_purpose === 'sale' ? 'للبيع' : 'للإيجار' }}
                        </span>
                    </div>
                    {{-- Info --}}
                    <div class="p-3">
                        <p class="font-semibold text-gray-900 text-sm line-clamp-1">{{ $listing->title }}</p>
                        <p class="text-primary-600 font-bold text-base mt-1">{{ $listing->price_formatted }}</p>
                        <div class="flex items-center gap-1 mt-1.5 text-xs text-gray-500">
                            @if($listing->city)
                                <x-heroicon name="map-pin" class="w-3.5 h-3.5" />
                                <span>{{ app()->getLocale() === 'ar' ? $listing->city->name_ar : $listing->city->name_en }}</span>
                            @endif
                            <span class="ms-auto">{{ $listing->classifiedCategory?->name }}</span>
                        </div>
                    </div>
                </a>
                @empty
                <div class="col-span-2 py-16 text-center text-gray-400">
                    <x-heroicon name="inbox" class="w-12 h-12 mx-auto mb-2" />
                    <p class="text-sm">لا توجد إعلانات حالياً</p>
                </div>
                @endforelse
            </div>

            <div class="px-4 pb-6">
                {{ $listings->links() }}
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/storefront/classified-map.js') }}"></script>
<script>
    let mapVisible = false;
    let browseMapInit = false;

    function toggleMap() {
        const container = document.getElementById('map-container');
        const btn = document.getElementById('map-toggle-btn');
        mapVisible = !mapVisible;
        container.classList.toggle('hidden', !mapVisible);
        btn.textContent = mapVisible ? '← إخفاء الخريطة' : '🗺 عرض الخريطة';

        if (mapVisible && !browseMapInit) {
            const mapUrl = container.dataset.mapUrl;
            initBrowseMap('browse-map', mapUrl);
            browseMapInit = true;
        }
    }
</script>
@endpush
