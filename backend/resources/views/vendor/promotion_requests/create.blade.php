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
            <a href="{{ route('partner.promotion-requests.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('partner.static_text.vendor_promotion_requests_create.back_to_promotion_requests') }}</a>
        </div>

        <form method="POST" action="{{ route('partner.promotion-requests.store') }}" class="space-y-6">
            @csrf

            {{-- STEP 1 — Select Listing --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">1. اختر القائمة (Listing)</h3>
                <select name="vendor_listing_id" x-model="selectedListingId" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">{{ __('partner.static_text.vendor_promotion_requests_create.select_an_active_listing_enabled_for') }}</option>
                    @foreach($listings as $listing)
                        <option value="{{ $listing->id }}">
                            {{ $listing->vendor_sku ?? $listing->id }} — {{ $listing->currency }} {{ number_format($listing->price) }}
                        </option>
                    @endforeach
                </select>

                <div x-show="requiresWarehouseReceipt" x-cloak
                     class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
                    {{ __('partner.static_text.vendor_promotion_requests_create.your_listing_uses_the_fbp_fbm') }}
                </div>
            </div>

            {{-- STEP 2 — Select Celebrities --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('partner.static_text.vendor_promotion_requests_create.2_choose_influencers_celebrities') }}</h3>
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
                <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('partner.static_text.vendor_promotion_requests_create.cost_summary') }}</h3>
                <div class="space-y-2 text-sm text-gray-700">
                    <div class="flex justify-between">
                        <span>{{ __('partner.static_text.vendor_promotion_requests_create.number_of_selected_influencers') }}</span>
                        <span x-text="selectedCelebrities.length"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('partner.static_text.vendor_promotion_requests_create.promotion_fee') }}</span>
                        <span><span x-text="selectedCelebrities.length"></span> × {{ $costSettings['fee_per_celebrity'] }} = <span x-text="promotionFees"></span> {{ __('partner.static_text.vendor_promotion_requests_create.sar') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('partner.static_text.vendor_promotion_requests_create.fixed_admin_commission') }}</span>
                        <span>{{ $costSettings['fixed_commission'] }} ريال</span>
                    </div>
                    <hr class="border-gray-300">
                    <div class="flex justify-between font-semibold text-gray-900">
                        <span>{{ __('partner.static_text.vendor_promotion_requests_create.total_upfront_cost') }}</span>
                        <span><span x-text="totalCost"></span> {{ __('partner.static_text.vendor_promotion_requests_create.sar') }}</span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>{{ __('partner.static_text.vendor_promotion_requests_create.number_of_samples_required') }}</span>
                        <span><span x-text="samplesNeeded"></span> (عدد المؤثرين + عينة الإدارة)</span>
                    </div>
                </div>
            </div>

            {{-- STEP 3 — Notes --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">3. ملاحظات (اختياري)</h3>
                <textarea name="vendor_note" maxlength="500" rows="4"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                          placeholder="{{ __('partner.static_text.vendor_promotion_requests_create.add_any_notes_for_this_request') }}"></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" :disabled="!selectedListingId || selectedCelebrities.length === 0"
                        class="bg-yellow-400 hover:bg-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed text-gray-900 font-semibold text-sm rounded-lg px-6 py-3">
                    {{ __('partner.static_text.vendor_promotion_requests_create.submit_promotion_request') }}
                </button>
            </div>
        </form>
    </div>
@endsection
