@extends('layouts.admin')
@section('title', $marketer->name)
@section('page-title', $marketer->name)

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@php
    $statusStyles = [
        'active' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        'pending' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
        'suspended' => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
        'rejected' => 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200',
    ];
    $statusLabels = [
        'active' => 'نشط',
        'pending' => 'قيد المراجعة',
        'suspended' => 'معلّق',
        'rejected' => 'مرفوض',
    ];
    $statusValue = $marketer->global_status?->value;
@endphp

@section('content')
<div class="w-full space-y-6">

    {{-- Header --}}
    <div class="bg-white rounded-xl border shadow-sm p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white text-lg font-bold">
                    {{ mb_substr($marketer->name, 0, 1) }}
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-gray-900">{{ $marketer->name }}</h2>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusStyles[$statusValue] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $statusLabels[$statusValue] ?? $statusValue }}
                        </span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1">{{ $marketer->email }}</span>
                        @if($marketer->phone)
                            <span class="inline-flex items-center gap-1">{{ $marketer->phone }}</span>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @forelse($marketer->marketerJobs as $job)
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $job->key === 'influencer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $job->key === 'influencer' ? '🎬' : '🔗' }} {{ app()->getLocale() === 'ar' ? $job->name_ar : $job->name_en }}
                            </span>
                        @empty
                            <span class="text-gray-300 text-xs">-</span>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
                @if($statusValue === 'pending')
                    <form method="POST" action="{{ route('admin.marketers.approve', $marketer) }}">
                        @csrf
                        <button class="px-4 py-2 bg-emerald-500 text-white font-semibold rounded-lg text-sm shadow-sm hover:bg-emerald-600 transition-colors">✓ موافقة وتفعيل</button>
                    </form>
                    <form method="POST" action="{{ route('admin.marketers.reject', $marketer) }}" x-data x-on:submit.prevent="
                        const r = prompt('سبب الرفض:');
                        if(r){ $el.querySelector('[name=reason]').value = r; $el.submit(); }">
                        @csrf
                        <input type="hidden" name="reason">
                        <button class="px-4 py-2 bg-red-500 text-white font-semibold rounded-lg text-sm shadow-sm hover:bg-red-600 transition-colors">✕ رفض</button>
                    </form>
                @elseif($statusValue === 'active')
                    <form method="POST" action="{{ route('admin.marketers.suspend', $marketer) }}" x-data x-on:submit.prevent="
                        const r = prompt('سبب التعليق:');
                        $el.querySelector('[name=reason]').value = r || '';
                        $el.submit();">
                        @csrf
                        <input type="hidden" name="reason">
                        <button class="px-4 py-2 bg-red-50 text-red-700 font-semibold rounded-lg text-sm ring-1 ring-inset ring-red-200 hover:bg-red-100 transition-colors">تعليق الحساب</button>
                    </form>
                @elseif($statusValue === 'suspended')
                    <form method="POST" action="{{ route('admin.marketers.activate', $marketer) }}">
                        @csrf
                        <button class="px-4 py-2 bg-emerald-50 text-emerald-700 font-semibold rounded-lg text-sm ring-1 ring-inset ring-emerald-200 hover:bg-emerald-100 transition-colors">إعادة تفعيل</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4 border-t border-gray-100 pt-5 text-sm">
            <div>
                <div class="text-gray-400 text-xs mb-0.5">الدولة</div>
                <div class="font-semibold text-gray-800">{{ $marketer->country?->name_ar ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-400 text-xs mb-0.5">واتساب</div>
                <div class="font-semibold text-gray-800" dir="ltr">{{ $marketer->whatsapp_for_campaigns ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-400 text-xs mb-0.5">تاريخ التسجيل</div>
                <div class="font-semibold text-gray-800">{{ $marketer->created_at->format('Y-m-d') }}</div>
            </div>
            @if($marketer->approved_at)
            <div>
                <div class="text-gray-400 text-xs mb-0.5">تمت الموافقة</div>
                <div class="font-semibold text-gray-800">{{ $marketer->approved_at->format('Y-m-d') }} <span class="text-gray-400 font-normal">— {{ $marketer->approvedBy?->name ?? '-' }}</span></div>
            </div>
            @endif
        </div>

        @if($marketer->rejection_reason)
        <div class="mt-5 flex items-start gap-2 p-3 bg-red-50 text-red-700 rounded-lg text-sm">
            <strong class="shrink-0">سبب الرفض/التعليق:</strong> <span>{{ $marketer->rejection_reason }}</span>
        </div>
        @endif
    </div>

    {{-- Ad price & self-edit permission --}}
    <div class="bg-white rounded-xl border shadow-sm p-6 space-y-4">
        <div class="flex items-center gap-2 border-b border-gray-100 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-yellow-50 text-yellow-600">💰</span>
            <h3 class="font-bold text-gray-800">سعر الإعلان المعروض (Ad Display Price)</h3>
        </div>
        <form method="POST" action="{{ route('admin.marketers.profile.update', $marketer) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">السعر</label>
                    <input type="number" min="0" name="ad_price" value="{{ old('ad_price', $marketer->marketerProfile?->ad_price) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">العملة</label>
                    @php $adCurrencies = \App\Models\Currency::where('is_active', true)->orderBy('code')->get(['code', 'name']); $selAdCur = old('ad_price_currency', $marketer->marketerProfile?->ad_price_currency); @endphp
                    <select name="ad_price_currency" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400">
                        <option value="">-</option>
                        @foreach($adCurrencies as $cur)
                            <option value="{{ $cur->code }}" @selected($selAdCur === $cur->code)>{{ $cur->code }} — {{ $cur->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                <input type="checkbox" name="can_self_edit_ad_price" value="1"
                       @checked(old('can_self_edit_ad_price', $marketer->marketerProfile?->can_self_edit_ad_price))
                       class="rounded border-gray-300">
                السماح للماركتر بتعديل سعره الخاص (استثناء)
            </label>

            @if($marketer->isInfluencer())
            <div class="pt-4 border-t border-gray-100 space-y-4">
                <h4 class="font-bold text-gray-800">مقاسات المؤثر (Sample Sizes)</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">المقاس العام</label>
                        <input type="text" name="clothing_size" value="{{ old('clothing_size', $marketer->marketerProfile?->clothing_size) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">مقاس القميص</label>
                        <input type="text" name="shirt_size" value="{{ old('shirt_size', $marketer->marketerProfile?->shirt_size) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">مقاس البنطلون</label>
                        <input type="text" name="pants_size" value="{{ old('pants_size', $marketer->marketerProfile?->pants_size) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">مقاس الفستان</label>
                        <input type="text" name="dress_size" value="{{ old('dress_size', $marketer->marketerProfile?->dress_size) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">مقاس العباية</label>
                        <input type="text" name="abaya_size" value="{{ old('abaya_size', $marketer->marketerProfile?->abaya_size) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">مقاس الحذاء</label>
                            <input type="text" name="shoe_size" value="{{ old('shoe_size', $marketer->marketerProfile?->shoe_size) }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">النظام</label>
                            <select name="shoe_size_system" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                                <option value="">-</option>
                                @foreach(['EU', 'US', 'UK'] as $sys)
                                <option value="{{ $sys }}" @selected(old('shoe_size_system', $marketer->marketerProfile?->shoe_size_system) === $sys)>{{ $sys }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">محيط الصدر (سم)</label>
                        <input type="number" step="0.1" name="chest_cm" value="{{ old('chest_cm', $marketer->marketerProfile?->chest_cm) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">محيط الخصر (سم)</label>
                        <input type="number" step="0.1" name="waist_cm" value="{{ old('waist_cm', $marketer->marketerProfile?->waist_cm) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">محيط الحوض (سم)</label>
                        <input type="number" step="0.1" name="hip_cm" value="{{ old('hip_cm', $marketer->marketerProfile?->hip_cm) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">الطول (سم)</label>
                        <input type="number" step="0.1" name="height_cm" value="{{ old('height_cm', $marketer->marketerProfile?->height_cm) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">طول الملابس (سم)</label>
                        <input type="number" step="0.1" name="item_length_cm" value="{{ old('item_length_cm', $marketer->marketerProfile?->item_length_cm) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">الكم من الرقبة (سم)</label>
                        <input type="number" step="0.1" name="sleeve_from_neck_cm" value="{{ old('sleeve_from_neck_cm', $marketer->marketerProfile?->sleeve_from_neck_cm) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">الكم من الكتف (سم)</label>
                        <input type="number" step="0.1" name="sleeve_from_shoulder_cm" value="{{ old('sleeve_from_shoulder_cm', $marketer->marketerProfile?->sleeve_from_shoulder_cm) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">عرض الكم (سم)</label>
                        <input type="number" step="0.1" name="sleeve_width_cm" value="{{ old('sleeve_width_cm', $marketer->marketerProfile?->sleeve_width_cm) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ملاحظات</label>
                    <textarea name="measurements_notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('measurements_notes', $marketer->marketerProfile?->measurements_notes) }}</textarea>
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 border-t pt-4 mt-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('common.specialty_ar') }}</label>
                    <input type="text" name="specialty_ar" maxlength="150" value="{{ old('specialty_ar', $marketer->marketerProfile?->specialty_ar) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('common.specialty_en') }}</label>
                    <input type="text" name="specialty_en" dir="ltr" maxlength="150" value="{{ old('specialty_en', $marketer->marketerProfile?->specialty_en) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            @if($marketer->isAffiliate())
            <div class="border-t pt-4 mt-2">
                <h4 class="text-sm font-bold text-gray-700 mb-3">تخصص السمسار (Broker Specialization)</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">القسم المتخصص فيه</label>
                        <select name="broker_category_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">— بدون تحديد —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                                {{ old('broker_category_id', $marketer->marketerProfile?->broker_category_id) === $cat->id ? 'selected' : '' }}>
                                {{ $cat->name_ar }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">المدينة</label>
                        <select name="broker_city_id" id="brokerCitySelect" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                {{ old('broker_serves_all_cities', $marketer->marketerProfile?->broker_serves_all_cities) ? 'disabled' : '' }}>
                            <option value="">— اختر مدينة —</option>
                            @foreach($cities as $city)
                            <option value="{{ $city->id }}"
                                {{ old('broker_city_id', $marketer->marketerProfile?->broker_city_id) === $city->id ? 'selected' : '' }}>
                                {{ $city->name_ar }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="broker_serves_all_cities" value="1" id="brokerAllCitiesCheck"
                                   {{ old('broker_serves_all_cities', $marketer->marketerProfile?->broker_serves_all_cities) ? 'checked' : '' }}
                                   onchange="document.getElementById('brokerCitySelect').disabled = this.checked">
                            يخدم كل المدن
                        </label>
                    </div>
                </div>
            </div>
            @endif

            <button class="px-5 py-2 bg-gray-900 text-white font-semibold rounded-lg text-sm hover:bg-gray-800">حفظ</button>
        </form>
    </div>

    {{-- Job categories --}}
    @if($marketer->marketerJobAssignments->isNotEmpty())
    <div class="bg-white rounded-xl border shadow-sm p-6 space-y-5">
        <div class="flex items-center gap-2 border-b border-gray-100 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">🗂️</span>
            <h3 class="font-bold text-gray-800">أقسام الوظائف (Job Categories)</h3>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($marketer->marketerJobAssignments as $assignment)
            @php
                $job = $assignment->marketerJob;
                $scopableTypes = $job->categories->pluck('category_type')->intersect(['product', 'classified']);
            @endphp
            <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-4">
                <h4 class="text-sm font-bold text-gray-700 mb-2">
                    {{ app()->getLocale() === 'ar' ? $job->name_ar : $job->name_en }}
                </h4>

                @if($scopableTypes->isEmpty())
                    <p class="text-xs text-gray-400">تنطبق هذه الوظيفة على كل الأقسام دون قيود.</p>
                @else
                    @foreach($scopableTypes as $categoryType)
                        @php
                            $sourceOptions = $job->eligibleCategories($categoryType);
                            $selectedIds = $assignment->categoryScopes
                                ->where('category_type', $categoryType)
                                ->pluck('category_id');
                        @endphp
                        <form method="POST" action="{{ route('admin.marketers.job-categories.sync', $marketer) }}" class="mb-3">
                            @csrf
                            <input type="hidden" name="marketer_job_id" value="{{ $job->id }}">
                            <input type="hidden" name="category_type" value="{{ $categoryType }}">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">
                                {{ $categoryType === 'product' ? 'أقسام المنتجات' : 'أقسام المصنفات (opensouq)' }}
                            </label>
                            <select name="category_ids[]" multiple data-select2-init class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                @foreach($sourceOptions as $option)
                                    <option value="{{ $option->id }}" {{ $selectedIds->contains($option->id) ? 'selected' : '' }}>
                                        {{ app()->getLocale() === 'ar' ? $option->name_ar : $option->name_en }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">اترك التحديد فارغًا ليشمل كل الأقسام.</p>
                            <button class="mt-2 px-4 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-lg hover:bg-gray-900">حفظ</button>
                        </form>
                    @endforeach
                @endif
            </div>
        @endforeach
        </div>
    </div>
    @endif

    {{-- Category commission overrides --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">%</span>
            <h3 class="font-bold text-gray-800">نسب العمولة حسب القسم</h3>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-6 py-3 text-start">القسم</th>
                    <th class="px-6 py-3 text-center">نسبة العمولة</th>
                    <th class="px-6 py-3 text-center"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($commissionRules as $cc)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-6 py-3 font-medium">
                        <span class="text-xs text-gray-500">{{ __('admin.marketer_commission_type_'.$cc->scope) }}</span>
                        · {{ $cc->category ? ($cc->category->name_ar ?: $cc->category->name_en) : __('admin.marketer_commission_default_all') }}
                    </td>
                    <td class="px-6 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">{{ collect([
                            (float) $cc->commission_rate > 0 ? number_format($cc->commission_rate, 2).'%' : null,
                            (int) $cc->commission_flat_amount > 0 ? number_format($cc->commission_flat_amount).' '.$commissionCurrency : null,
                        ])->filter()->implode(' + ') ?: '0%' }}</span>
                    </td>
                    <td class="px-6 py-3 text-center">
                        <form method="POST" action="{{ route('admin.marketers.category-commissions.destroy', [$marketer, $cc->id]) }}"
                              onsubmit="return confirm('حذف نسبة العمولة؟');">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-500 hover:text-red-700 text-xs font-semibold">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-6 py-8 text-center text-gray-400">لا توجد نسب عمولة مخصصة بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <form method="POST" action="{{ route('admin.marketers.category-commissions.store', $marketer) }}" class="p-4 border-t border-gray-100 bg-gray-50 flex flex-wrap items-end gap-3"
              x-data='{ scope: "products", cats: @json($commissionCategories->map(fn ($c) => $c->map(fn ($x) => ["id" => $x->id, "name" => $x->name_ar ?: $x->name_en])->values())) }'>
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketer_commission_type') }}</label>
                <select name="scope" x-model="scope" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[160px]">
                    @foreach(['products', 'open_market', 'travel'] as $sc)
                    <option value="{{ $sc }}">{{ __('admin.marketer_commission_type_'.$sc) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">القسم</label>
                <select name="category_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[180px]">
                    <option value="">{{ __('admin.marketer_commission_default_all_type') }}</option>
                    <template x-for="c in cats[scope]" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">نسبة العمولة %</label>
                <input type="number" step="0.01" min="0" max="100" name="commission_rate" value="{{ old('commission_rate') }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('admin.marketer_commission_flat') }} ({{ $commissionCurrency }})</label>
                <input type="number" step="1" min="0" name="commission_flat_amount" value="{{ old('commission_flat_amount') }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
                @error('commission_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500 transition-colors">إضافة / تحديث</button>
        </form>
    </div>

    {{-- Exclusive contracts (open-market) --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-600">📄</span>
            <h3 class="font-bold text-gray-800">{{ __('admin.exclusive_contracts') }}</h3>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-start">النطاق</th>
                    <th class="px-4 py-3 text-center">من</th>
                    <th class="px-4 py-3 text-center">إلى</th>
                    <th class="px-4 py-3 text-center">المدة / المتبقي</th>
                    <th class="px-4 py-3 text-center">الحالة</th>
                    <th class="px-4 py-3 text-center">ملف العقد</th>
                    <th class="px-4 py-3 text-start">ملاحظات</th>
                    <th class="px-4 py-3 text-center">أضافه</th>
                    <th class="px-4 py-3 text-center"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($marketer->exclusiveContracts as $contract)
                @php
                    $statusMap = [
                        'pending' => ['قيد الانتظار', 'bg-yellow-50 text-yellow-700'],
                        'active' => ['نشط', 'bg-emerald-50 text-emerald-700'],
                        'expired' => ['منتهي', 'bg-gray-100 text-gray-600'],
                        'revoked' => ['ملغي', 'bg-red-50 text-red-600'],
                    ];
                    $isLapsed = $contract->status === 'active' && $contract->ends_at && $contract->ends_at->isPast();
                    [$statusLabel, $statusClass] = $isLapsed ? $statusMap['expired'] : ($statusMap[$contract->status] ?? [$contract->status, 'bg-gray-100 text-gray-600']);
                    $totalDays = ($contract->starts_at && $contract->ends_at) ? $contract->starts_at->diffInDays($contract->ends_at) + 1 : null;
                    $daysLeft = ($contract->ends_at && ! $contract->ends_at->isPast()) ? (int) ceil(now()->diffInDays($contract->ends_at, false)) : 0;
                @endphp
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-4 py-3 font-medium">
                        @if($contract->classifiedListing)
                            إعلان: {{ $contract->classifiedListing->listing_number }}
                            @if($contract->classifiedListing->title_ar ?? $contract->classifiedListing->title_en)
                                <div class="text-xs text-gray-400 font-normal">{{ $contract->classifiedListing->title_ar ?: $contract->classifiedListing->title_en }}</div>
                            @endif
                        @elseif($contract->classifiedCategory)
                            قسم: {{ $contract->classifiedCategory->name_ar }}
                        @else
                            كل الأقسام
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $contract->starts_at?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $contract->ends_at?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-center text-gray-500 text-xs">
                        @if($totalDays !== null){{ $totalDays }} يوم @endif
                        @if(in_array($contract->status, ['pending', 'active']) && ! $isLapsed && $contract->ends_at)
                            <div class="text-emerald-600">متبقي {{ $daysLeft }} يوم</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($contract->contract_file_path)
                            <a href="{{ route('admin.marketers.exclusive-contracts.download', [$marketer, $contract]) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-semibold">تحميل</a>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 max-w-[200px]">{{ $contract->notes ?: '—' }}</td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500">
                        {{ $contract->createdBy?->name ?? '—' }}
                        <div class="text-gray-400">{{ $contract->created_at?->format('Y-m-d') }}</div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if(in_array($contract->status, ['pending', 'active']))
                        <form method="POST" action="{{ route('admin.marketers.exclusive-contracts.destroy', [$marketer, $contract]) }}"
                              onsubmit="return confirm('إلغاء العقد الحصري؟');">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-500 hover:text-red-700 text-xs font-semibold">إلغاء</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-6 py-8 text-center text-gray-400">لا توجد عقود حصرية بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <form method="POST" action="{{ route('admin.marketers.exclusive-contracts.store', $marketer) }}"
              enctype="multipart/form-data" class="p-4 border-t border-gray-100 bg-gray-50 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">القسم (اختياري)</label>
                <select name="classified_category_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[160px]">
                    <option value="">كل الأقسام</option>
                    @foreach($classifiedCategories as $category)
                    <option value="{{ $category->id }}">{{ $category->name_ar }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">رقم إعلان محدد (اختياري)</label>
                <input type="text" name="classified_listing_id" placeholder="UUID الإعلان"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">من</label>
                <input type="date" name="starts_at" required class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">إلى</label>
                <input type="date" name="ends_at" required class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">الحالة</label>
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="pending">قيد الانتظار</option>
                    <option value="active">نشط</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">ملف العقد (اختياري)</label>
                <input type="file" name="contract_file" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-semibold text-gray-600 mb-1">ملاحظات</label>
                <input type="text" name="notes" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-full">
            </div>
            <button class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500 transition-colors">{{ __('admin.add_exclusive_contract') }}</button>
        </form>
    </div>

    {{-- Campaign invitations --}}
    @if($marketer->invitations->isNotEmpty())
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-600">📣</span>
            <h3 class="font-bold text-gray-800">الحملات ({{ $marketer->invitations->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-6 py-3 text-start">الحملة</th>
                    <th class="px-6 py-3 text-center">البائع</th>
                    <th class="px-6 py-3 text-center">الحالة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($marketer->invitations->take(10) as $inv)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-6 py-3 font-medium">{{ $inv->campaign->title ?? substr($inv->campaign_id, 0, 8) }}</td>
                    <td class="px-6 py-3 text-center text-gray-500">{{ $inv->campaign->vendor->name ?? '-' }}</td>
                    <td class="px-6 py-3 text-center"><span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full">{{ $inv->status }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

</div>
@endsection
