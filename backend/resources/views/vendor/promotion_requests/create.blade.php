@extends('layouts.partner')

@section('title', 'طلب ترويج مؤثرين جديد')
@section('page-title', 'طلب ترويج مؤثرين جديد')

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-5xl"
         x-data="{
            selectedListingId: null,
            selectedCelebrities: [],
            feePerCelebrity: {{ $costSettings['fee_per_celebrity'] }},
            fixedCommission: {{ $costSettings['fixed_commission'] }},
            listings: {{ Js::from($listings->map(fn ($l) => ['id' => $l->id, 'fulfillment_model' => $l->fulfillment_model])) }},
            get selectedListing() { return this.listings.find(l => l.id === this.selectedListingId) },
            get requiresWarehouseReceipt() {
                return this.selectedListing && ['fbm', 'cross_dock'].includes(this.selectedListing.fulfillment_model);
            },
            toggleCelebrity(id) {
                const idx = this.selectedCelebrities.indexOf(id);
                if (idx === -1) { this.selectedCelebrities.push(id); } else { this.selectedCelebrities.splice(idx, 1); }
            },
            get promotionFees() { return this.selectedCelebrities.length * this.feePerCelebrity },
            get totalCost() { return this.promotionFees + this.fixedCommission },
            get samplesNeeded() { return this.selectedCelebrities.length + 1 },
         }">

        <div class="mb-6">
            <a href="{{ route('partner.promotion-requests.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; الرجوع إلى طلبات الترويج</a>
        </div>

        <form method="POST" action="{{ route('partner.promotion-requests.store') }}" class="space-y-6">
            @csrf

            {{-- STEP 1 — Select Listing --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">1. اختر القائمة (Listing)</h3>
                <select name="vendor_listing_id" x-model="selectedListingId" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- اختر قائمة نشطة ومفعّلة للمسوّقين --</option>
                    @foreach($listings as $listing)
                        <option value="{{ $listing->id }}">
                            {{ $listing->vendor_sku ?? $listing->id }} — {{ $listing->currency }} {{ number_format($listing->price) }}
                        </option>
                    @endforeach
                </select>

                <div x-show="requiresWarehouseReceipt" x-cloak
                     class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                    ⚠️ قائمتك تستخدم نموذج FBP/FBM. يجب عليك شحن عينات المنتج إلى مستودعنا قبل تفعيل طلب الترويج هذا.
                </div>
            </div>

            {{-- STEP 2 — Select Celebrities --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">2. اختر المؤثرين / المشاهير</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($celebrities as $celebrity)
                        <label class="flex items-center gap-3 border border-gray-200 rounded-xl p-4 cursor-pointer hover:border-yellow-400">
                            <input type="checkbox" name="celebrity_marketer_ids[]" value="{{ $celebrity->id }}"
                                   @change="toggleCelebrity('{{ $celebrity->id }}')"
                                   class="rounded border-gray-300">
                            <img src="{{ $celebrity->profile_photo_path ? asset('storage/'.$celebrity->profile_photo_path) : asset('images/avatar-placeholder.png') }}"
                                 alt="" class="w-12 h-12 rounded-full object-cover">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $celebrity->display_name ?? $celebrity->name }}</div>
                                <div class="text-xs text-gray-500">{{ number_format($celebrity->followers_count ?? 0) }} متابع</div>
                                <div class="text-xs text-gray-400">{{ $celebrity->niche }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- COST PREVIEW --}}
            <div class="bg-gray-50 rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">ملخص التكلفة</h3>
                <div class="space-y-2 text-sm text-gray-700">
                    <div class="flex justify-between">
                        <span>عدد المؤثرين المختارين</span>
                        <span x-text="selectedCelebrities.length"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>رسوم الترويج</span>
                        <span><span x-text="selectedCelebrities.length"></span> × {{ $costSettings['fee_per_celebrity'] }} = <span x-text="promotionFees"></span> ريال</span>
                    </div>
                    <div class="flex justify-between">
                        <span>عمولة الإدارة الثابتة</span>
                        <span>{{ $costSettings['fixed_commission'] }} ريال</span>
                    </div>
                    <hr class="border-gray-300">
                    <div class="flex justify-between font-semibold text-gray-900">
                        <span>إجمالي التكلفة المقدمة</span>
                        <span><span x-text="totalCost"></span> ريال</span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>عدد العينات المطلوبة</span>
                        <span><span x-text="samplesNeeded"></span> (عدد المؤثرين + عينة الإدارة)</span>
                    </div>
                </div>
            </div>

            {{-- STEP 3 — Notes --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">3. ملاحظات (اختياري)</h3>
                <textarea name="vendor_note" maxlength="500" rows="4"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                          placeholder="أضف أي ملاحظات لهذا الطلب..."></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" :disabled="!selectedListingId || selectedCelebrities.length === 0"
                        class="bg-yellow-400 hover:bg-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed text-gray-900 font-semibold text-sm rounded-lg px-6 py-3">
                    إرسال طلب الترويج
                </button>
            </div>
        </form>
    </div>
@endsection
