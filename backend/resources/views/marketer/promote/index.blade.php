@extends('layouts.marketer')
@section('title', 'الترويج المدفوع')
@section('page-title', 'الترويج المدفوع')

@section('content')
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex items-center gap-3 flex-wrap">
        <select id="filter-surface" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
            <option value="">كل الأماكن</option>
            <option value="page_block">الصفحة الرئيسية</option>
            <option value="placement">أماكن أخرى</option>
        </select>
        <select id="filter-pricing" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
            <option value="">كل نماذج التسعير</option>
            <option value="fixed_daily">يومي ثابت</option>
            <option value="fixed_weekly">أسبوعي ثابت</option>
            <option value="fixed_monthly">شهري ثابت</option>
            <option value="cpm">CPM</option>
            <option value="cpc">CPC</option>
        </select>
    </div>

    @foreach (['homepage' => 'الصفحة الرئيسية', 'cart' => 'السلة', 'product' => 'صفحات المنتج', 'search' => 'البحث', 'category' => 'الأقسام'] as $key => $label)
        @if ($grouped->has($key))
            <h3 class="text-sm font-semibold text-gray-700 mt-6 mb-3">{{ $label }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($grouped[$key] as $slot)
                    <div class="ad-slot-card bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-md transition-shadow"
                         data-surface="{{ $slot->target_type->value }}" data-pricing="{{ $slot->pricing_model->value }}">
                        <img src="{{ asset('images/ad-slots/generic.png') }}" alt="" class="w-full h-32 object-cover bg-gray-50">
                        <div class="p-4">
                            <div class="text-sm font-semibold text-gray-900">
                                {{ app()->getLocale() === 'ar' && $slot->name_ar ? $slot->name_ar : $slot->name }}
                            </div>
                            @if ($slot->target_type->value === 'page_block')
                                <div class="text-xs text-gray-500 mt-1">{{ $slot->pageBlock?->page?->name }} · {{ $slot->pageBlock?->block_type }} #{{ $slot->item_position }}</div>
                            @else
                                <div class="text-xs text-gray-500 mt-1">{{ $slot->placementDefinition?->name }}</div>
                            @endif
                            <div class="text-sm font-medium text-primary-700 mt-2">
                                @if (in_array($slot->pricing_model->value, ['cpm', 'cpc']))
                                    {{ strtoupper($slot->pricing_model->value) }} {{ number_format($slot->base_rate) }} {{ $slot->country?->currency_code }}
                                    · بحد أدنى للميزانية {{ number_format($slot->min_budget) }} {{ $slot->country?->currency_code }}
                                @else
                                    {{ number_format($slot->base_rate) }} {{ $slot->country?->currency_code }} / {{ str_replace('fixed_', '', $slot->pricing_model->value) }}
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 mt-1">{{ $slot->creative_width_px }}×{{ $slot->creative_height_px }}px</div>
                            <a href="{{ route('marketer.promote.show', $slot->id) }}"
                               class="mt-3 block text-center rounded-lg bg-primary-600 text-white text-sm font-medium py-2 hover:bg-primary-700">
                                احجز
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach

    @if ($grouped->isEmpty())
        <div class="text-center text-gray-400 py-16">لا توجد أماكن إعلانية متاحة لحسابك حالياً.</div>
    @endif

    <script>
        document.getElementById('filter-surface').addEventListener('change', applyAdSlotFilters);
        document.getElementById('filter-pricing').addEventListener('change', applyAdSlotFilters);
        function applyAdSlotFilters() {
            const surface = document.getElementById('filter-surface').value;
            const pricing = document.getElementById('filter-pricing').value;
            document.querySelectorAll('.ad-slot-card').forEach(card => {
                const show = (!surface || card.dataset.surface === surface) && (!pricing || card.dataset.pricing === pricing);
                card.style.display = show ? '' : 'none';
            });
        }
    </script>
@endsection
