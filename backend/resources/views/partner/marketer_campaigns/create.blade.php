@extends('layouts.partner')

@section('title', 'إنشاء حملة ماركتر')
@section('page-title', 'إنشاء حملة ماركتر')

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">إنشاء حملة ماركتر</h1>
        <p class="mt-1 text-sm text-gray-500">دعوة ماركترز للترويج لهذا المنتج مقابل عمولة.</p>
    </div>

    @if(session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Listing summary --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center gap-3">
            @if($vendorListing->productVariant->product->image_url ?? false)
                <img src="{{ $vendorListing->productVariant->product->image_url }}" class="w-14 h-14 rounded-lg object-cover">
            @endif
            <div>
                <div class="font-semibold text-gray-900">
                    {{ $vendorListing->productVariant->product->name_en ?? '—' }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ $vendorListing->currency }} {{ number_format($vendorListing->price) }}
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('partner.marketer-campaigns.store') }}" x-data="campaignCreateForm()">
        @csrf
        <input type="hidden" name="vendor_listing_id" value="{{ $vendorListing->id }}">

        <div class="bg-white rounded-2xl border border-purple-200 p-6 space-y-4">
            @if($marketerVendors->isEmpty())
                <p class="text-xs text-amber-600 bg-amber-50 rounded-lg p-3">
                    لا يوجد ماركترز متاحين في بلدك حالياً. يمكن للأدمن تفعيل ماركترز من لوحة التحكم.
                </p>
            @else
                <x-form.select
                    name="marketer_ids"
                    label="اختر الماركترز"
                    :multiple="true"
                    :select2="true"
                    placeholder="ابحث واختر الماركترز..."
                    x-on:change="updateSelectedMarketers($event)"
                >
                    @foreach($marketerVendors as $m)
                        <option value="{{ $m->id }}" data-type="{{ $m->marketerJobs->first()?->key }}" data-name="{{ $m->name }}">
                            {{ $m->name }} — {{ $m->isInfluencer() ? 'مؤثر' : 'أفلييت' }}
                        </option>
                    @endforeach
                </x-form.select>

                <div x-show="selectedMarketers.length > 0" x-cloak class="mt-4">
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-receipt text-orange-500 mr-1"></i>
                        تفاصيل الرسوم لكل ماركتر
                    </h5>
                    <div class="rounded-lg border border-gray-200 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-right px-4 py-2 text-gray-600 font-medium">الماركتر</th>
                                    <th class="text-center px-4 py-2 text-gray-600 font-medium">النوع</th>
                                    <th class="text-center px-4 py-2 text-gray-600 font-medium">الرسوم</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="marketer in selectedMarketers" :key="marketer.id">
                                    <tr class="border-t border-gray-100">
                                        <td class="px-4 py-2 text-gray-800" x-text="marketer.name"></td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="px-2 py-0.5 rounded-full text-xs"
                                                  :class="marketer.type === 'influencer' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                                                  x-text="marketer.type === 'influencer' ? 'إنفلوينسر' : 'أفيلييت'">
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-center font-medium"
                                            :class="marketer.type === 'influencer' && feePerInfluencer > 0 ? 'text-orange-700' : 'text-green-600'">
                                            <span x-show="marketer.type === 'influencer' && feePerInfluencer > 0"
                                                  x-text="feePerInfluencer + ' ' + currency"></span>
                                            <span x-show="!(marketer.type === 'influencer' && feePerInfluencer > 0)" class="text-green-600">مجاني</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 font-semibold text-gray-700 text-right">إجمالي رسوم المنصة</td>
                                    <td class="px-4 py-2 text-center font-bold"
                                        :class="totalInfluencerFee > 0 ? 'text-orange-700' : 'text-green-600'">
                                        <span x-show="totalInfluencerFee > 0" x-text="totalInfluencerFee + ' ' + currency"></span>
                                        <span x-show="totalInfluencerFee === 0" class="text-green-600">مجاني</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        رسوم المنصة تُحسب لكل إنفلوينسر مختار — الأفيلييت مجاني دائماً
                    </p>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع الكوميشن</label>
                <select name="commission_type" x-model="commissionType"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40">
                    <option value="fixed">{{ __('partner.listings.commission_type_fixed') }}</option>
                    <option value="tiered">{{ __('partner.listings.commission_type_tiered') }}</option>
                    <option value="last_click">{{ __('partner.listings.commission_type_last_click') }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    أقصى ميزانية كوميشن
                    <span class="text-xs text-gray-400">({{ auth()->guard('vendor')->user()->vendor->country->currency_code ?? '' }})</span>
                </label>
                <input type="number" name="max_commission_budget" min="0"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400/40"
                       placeholder="0">
            </div>

            <div x-show="commissionType === 'tiered'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-2">قواعد الكوميشن المتدرج</label>
                <div class="space-y-2">
                    <template x-for="(rule, i) in tieredRules" :key="i">
                        <div class="flex gap-2 items-center">
                            <input type="number" :name="`tiered_rules[${i}][from_sale_number]`"
                                   x-model="rule.from_sale_number"
                                   placeholder="رقم البيعة (مثال: 10)"
                                   class="w-1/2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
                            <input type="number" :name="`tiered_rules[${i}][commission_amount]`"
                                   x-model="rule.commission_amount"
                                   placeholder="مبلغ الكوميشن"
                                   class="w-1/2 border border-gray-200 rounded-xl px-3 py-2 text-sm">
                            <button type="button" @click="tieredRules.splice(i, 1)" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="tieredRules.push({from_sale_number: '', commission_amount: ''})"
                        class="mt-2 text-sm text-purple-600 hover:underline">
                    + إضافة مستوى
                </button>
            </div>

            <div class="p-3 bg-purple-50 rounded-lg text-sm text-purple-800">
                <i class="fas fa-box-open mr-1"></i>
                إجمالي العينات المتوقع: <strong x-text="selectedMarketers.length"></strong> ماركتر مختار
                <span class="block text-xs text-gray-500 mt-1">
                    سيتم تحديد كمية العينات النهائية تلقائياً حسب فئة المنتج بعد إنشاء الحملة.
                </span>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-yellow-400 hover:bg-yellow-300 text-gray-900 font-semibold px-6 py-3 rounded-xl transition-colors text-sm">
                إنشاء الحملة
            </button>
            <a href="{{ route('partner.marketer-campaigns.index') }}"
               class="border border-gray-200 text-gray-700 font-semibold px-6 py-3 rounded-xl text-sm hover:bg-gray-50">
                إلغاء
            </a>
        </div>
    </form>

</div>

@push('scripts')
    <script>
        function campaignCreateForm() {
            return {
                commissionType: 'fixed',
                tieredRules: [],
                selectedMarketers: [],
                feePerInfluencer: 0,
                currency: '',
                get totalInfluencerFee() {
                    const count = this.selectedMarketers.filter(m => m.type === 'influencer').length;
                    return count * this.feePerInfluencer;
                },
                updateSelectedMarketers(event) {
                    const select = event.target;
                    this.selectedMarketers = Array.from(select.selectedOptions || []).map(opt => ({
                        id: opt.value,
                        name: opt.dataset.name || opt.text,
                        type: opt.dataset.type || 'affiliate',
                    }));
                },
                async fetchInfluencerFee() {
                    try {
                        const res = await fetch('{{ route("partner.listings.marketer-fee") }}', {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        this.feePerInfluencer = data.fee_per_influencer ?? 0;
                        this.currency = data.currency ?? '';
                    } catch (e) {
                        console.error('Failed to fetch influencer fee', e);
                    }
                },
                init() {
                    this.fetchInfluencerFee();
                },
            };
        }
    </script>
@endpush

@endsection
