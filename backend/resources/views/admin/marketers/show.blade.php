@extends('layouts.admin')
@section('title', $marketer->name)
@section('page-title', $marketer->name)

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Info card --}}
    <div class="bg-white rounded-xl border p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $marketer->name }}</h2>
                <div class="text-gray-500 text-sm">{{ $marketer->email }}</div>
                @if($marketer->phone)<div class="text-gray-400 text-sm">{{ $marketer->phone }}</div>@endif
            </div>
            <div class="flex flex-col gap-2 items-end">
                <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $marketer->marketer_type === 'influencer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                    {{ $marketer->marketer_type === 'influencer' ? '🎬 مؤثر' : '🔗 أفيليت' }}
                </span>
                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">{{ $marketer->global_status?->value }}</span>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 text-sm">
            <div><span class="text-gray-400">الدولة: </span><strong>{{ $marketer->country?->name_ar ?? '-' }}</strong></div>
            <div><span class="text-gray-400">واتساب: </span><strong>{{ $marketer->whatsapp_for_campaigns ?? '-' }}</strong></div>
            <div><span class="text-gray-400">تاريخ التسجيل: </span><strong>{{ $marketer->created_at->format('Y-m-d') }}</strong></div>
            @if($marketer->approved_at)
            <div><span class="text-gray-400">تم الموافقة: </span><strong>{{ $marketer->approved_at->format('Y-m-d') }}</strong></div>
            <div class="col-span-2"><span class="text-gray-400">بواسطة: </span><strong>{{ $marketer->approvedBy?->name ?? '-' }}</strong></div>
            @endif
        </div>

        @if($marketer->rejection_reason)
        <div class="p-3 bg-red-50 text-red-700 rounded-lg text-sm">
            <strong>سبب الرفض/التعليق:</strong> {{ $marketer->rejection_reason }}
        </div>
        @endif

        {{-- Actions --}}
        <div class="flex gap-3 pt-2 border-t border-gray-100">
            @if($marketer->global_status?->value === 'pending')
                <form method="POST" action="{{ route('admin.marketers.approve', $marketer) }}">
                    @csrf
                    <button class="px-5 py-2 bg-green-500 text-white font-semibold rounded-lg text-sm hover:bg-green-600">✓ موافقة وتفعيل</button>
                </form>
                <form method="POST" action="{{ route('admin.marketers.reject', $marketer) }}" x-data x-on:submit.prevent="
                    const r = prompt('سبب الرفض:');
                    if(r){ $el.querySelector('[name=reason]').value = r; $el.submit(); }">
                    @csrf
                    <input type="hidden" name="reason">
                    <button class="px-5 py-2 bg-red-500 text-white font-semibold rounded-lg text-sm hover:bg-red-600">✕ رفض</button>
                </form>
            @elseif($marketer->global_status?->value === 'active')
                <form method="POST" action="{{ route('admin.marketers.suspend', $marketer) }}" x-data x-on:submit.prevent="
                    const r = prompt('سبب التعليق:');
                    $el.querySelector('[name=reason]').value = r || '';
                    $el.submit();">
                    @csrf
                    <input type="hidden" name="reason">
                    <button class="px-5 py-2 bg-red-100 text-red-700 font-semibold rounded-lg text-sm hover:bg-red-200">تعليق الحساب</button>
                </form>
            @elseif($marketer->global_status?->value === 'suspended')
                <form method="POST" action="{{ route('admin.marketers.activate', $marketer) }}">
                    @csrf
                    <button class="px-5 py-2 bg-green-100 text-green-700 font-semibold rounded-lg text-sm hover:bg-green-200">إعادة تفعيل</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Ad price & self-edit permission --}}
    <div class="bg-white rounded-xl border p-6 space-y-4">
        <h3 class="font-bold text-gray-800">سعر الإعلان المعروض (Ad Display Price)</h3>
        <form method="POST" action="{{ route('admin.marketers.profile.update', $marketer) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">السعر</label>
                    <input type="number" min="0" name="ad_price" value="{{ old('ad_price', $marketer->marketerProfile?->ad_price) }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">العملة</label>
                    <input type="text" maxlength="3" name="ad_price_currency" value="{{ old('ad_price_currency', $marketer->marketerProfile?->ad_price_currency) }}"
                           placeholder="SAR" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400">
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
                <div class="grid grid-cols-3 gap-4">
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
                <div class="grid grid-cols-3 gap-4">
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
                <div class="grid grid-cols-3 gap-4">
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

    {{-- Category commission overrides --}}
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="font-bold text-gray-800">نسب العمولة حسب القسم</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-start">القسم</th>
                    <th class="px-4 py-3 text-center">نسبة العمولة</th>
                    <th class="px-4 py-3 text-center"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($marketer->categoryCommissions as $cc)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $cc->category?->name_ar ?? 'افتراضي (كل الأقسام)' }}</td>
                    <td class="px-4 py-3 text-center">{{ number_format($cc->commission_rate, 2) }}%</td>
                    <td class="px-4 py-3 text-center">
                        <form method="POST" action="{{ route('admin.marketers.category-commissions.destroy', [$marketer, $cc]) }}"
                              onsubmit="return confirm('حذف نسبة العمولة؟');">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-500 hover:text-red-700 text-xs font-semibold">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">لا توجد نسب عمولة مخصصة بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
        <form method="POST" action="{{ route('admin.marketers.category-commissions.store', $marketer) }}" class="p-4 border-t bg-gray-50 flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">القسم</label>
                <select name="category_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[180px]">
                    <option value="">افتراضي (كل الأقسام)</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name_ar }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">نسبة العمولة %</label>
                <input type="number" step="0.01" min="0" max="100" name="commission_rate" required
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32">
            </div>
            <button class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500">إضافة / تحديث</button>
        </form>
    </div>

    {{-- Campaign invitations --}}
    @if($marketer->invitations->isNotEmpty())
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b"><h3 class="font-bold text-gray-800">الحملات ({{ $marketer->invitations->count() }})</h3></div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-start">الحملة</th>
                    <th class="px-4 py-3 text-center">البائع</th>
                    <th class="px-4 py-3 text-center">الحالة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($marketer->invitations->take(10) as $inv)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $inv->campaign->title ?? substr($inv->campaign_id, 0, 8) }}</td>
                    <td class="px-4 py-3 text-center text-gray-500">{{ $inv->campaign->vendor->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">{{ $inv->status }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>
@endsection
