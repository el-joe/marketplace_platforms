{{--
    Shared Coupon form partial.
    Include with: @include('admin.coupons._form', ['mode' => 'create'])
                  @include('admin.coupons._form', ['mode' => 'edit', 'coupon' => $coupon])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
    Requires vendors and categories variables from the controller.
--}}
@php
    $coupon = $coupon ?? null;
    $isEdit = $coupon !== null;

    $val = function (string $field, $default = '') use ($isEdit, $coupon) {
        $current = $isEdit ? ($coupon->{$field} ?? $default) : $default;
        if ($current instanceof \BackedEnum) {
            $current = $current->value;
        }
        return old($field, $current);
    };

    $bool = function (string $field, bool $default = false) use ($isEdit, $coupon): bool {
        $raw = old($field, $isEdit ? ($coupon->{$field} ?? $default) : $default);
        return (bool) $raw;
    };

    $currentType  = old('type',  $isEdit ? $coupon->type->value  : 'percentage');
    $currentScope = old('scope', $isEdit ? $coupon->scope->value : 'platform');
    $currentEligibility = old('customer_eligibility', $isEdit ? $coupon->customer_eligibility->value : 'all');
    $currentFundedBy = old('funded_by', $isEdit ? ($coupon->funded_by ?? 'platform') : 'platform');
    $selectedCustomers = $selectedCustomers ?? collect();
    $countries = $countries ?? collect();
    $selectedCountryIds = old('country_ids', $isEdit ? ($coupon->country_ids ?? []) : []);
@endphp

<div class="space-y-6" x-data="{
    type:  '{{ $currentType }}',
    scope: '{{ $currentScope }}',
    eligibility: '{{ $currentEligibility }}',
    fundedBy: '{{ $currentFundedBy }}'
}">
    <input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">
    <input type="hidden" id="form-times-used" value="{{ $isEdit ? $coupon->times_used : 0 }}">
    <input type="hidden" id="form-original-code" value="{{ $isEdit ? $coupon->code : '' }}">
    <input type="hidden" id="form-original-type" value="{{ $isEdit ? $coupon->type->value : '' }}">
    <input type="hidden" id="form-original-value" value="{{ $isEdit ? $coupon->value : '' }}">

    {{-- ─── Page header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $isEdit ? __('admin.coupons_section.edit_coupon') . ': ' . e($coupon->code) : __('admin.coupons_section.new_coupon') }}
            </h1>
            @if($isEdit)
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('admin.coupons_section.used_times_so_far', ['count' => $usageCount]) }}
                </p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary" id="save-btn">
                {{ $isEdit ? __('admin.coupons_section.save_changes') : __('admin.coupons_section.create_coupon') }}
            </button>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- LEFT: main fields                                               --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 space-y-4">

            {{-- Basic Info ─────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.coupon_details') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    {{-- Code --}}
                    <div>
                        <label for="code" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.code_required') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                id="code"
                                name="code"
                                value="{{ strtoupper($val('code')) }}"
                                class="input flex-1 font-mono uppercase @error('code') border-red-400 @enderror"
                                placeholder="{{ __('admin.coupons_section.code_placeholder') }}"
                                maxlength="50"
                                oninput="this.value = this.value.toUpperCase()"
                                required
                            />
                            <button type="button"
                                    id="btn-generate-code"
                                    data-url="{{ route('admin.coupons.generate-code') }}"
                                    class="btn btn-secondary btn-sm shrink-0 whitespace-nowrap">
                                {{ __('admin.coupons_section.generate') }}
                            </button>
                        </div>
                        @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.internal_name') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ $val('name') }}"
                            class="input w-full @error('name') border-red-400 @enderror"
                            placeholder="{{ __('admin.coupons_section.internal_name_placeholder') }}"
                            maxlength="150"
                            required
                        />
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label for="description" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.coupons_section.description') }}</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="3"
                            class="input w-full @error('description') border-red-400 @enderror"
                            placeholder="{{ __('admin.coupons_section.description_placeholder') }}"
                        >{{ $val('description') }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Bank Offer ─────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.bank_offer') }}</h2>
                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.coupons_section.bank_offer_hint') }}</p>
                </div>
                <div class="px-5 py-5 space-y-4">

                    {{-- Bank name --}}
                    <div>
                        <label for="bank_name" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.coupons_section.bank_name') }}</label>
                        <input
                            type="text"
                            id="bank_name"
                            name="bank_name"
                            value="{{ $val('bank_name') }}"
                            class="input w-full @error('bank_name') border-red-400 @enderror"
                            placeholder="{{ __('admin.coupons_section.bank_name_placeholder') }}"
                            maxlength="100"
                        />
                        @error('bank_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Promo titles --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="title_en" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.coupons_section.title_en') }}</label>
                            <input
                                type="text"
                                id="title_en"
                                name="title_en"
                                value="{{ $val('title_en') }}"
                                class="input w-full @error('title_en') border-red-400 @enderror"
                                placeholder="{{ __('admin.coupons_section.title_en_placeholder') }}"
                                maxlength="255"
                            />
                            @error('title_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="title_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.coupons_section.title_ar') }}</label>
                            <input
                                type="text"
                                id="title_ar"
                                name="title_ar"
                                dir="rtl"
                                value="{{ $val('title_ar') }}"
                                class="input w-full @error('title_ar') border-red-400 @enderror"
                                maxlength="255"
                            />
                            @error('title_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Terms --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="terms_en" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.coupons_section.terms_en') }}
                                <span class="text-gray-400 font-normal">{{ __('admin.coupons_section.terms_hint') }}</span>
                            </label>
                            <textarea
                                id="terms_en"
                                name="terms_en"
                                rows="4"
                                class="input w-full @error('terms_en') border-red-400 @enderror"
                            >{{ $isEdit ? implode("\n", old('terms_en', $coupon->terms_en ?? [])) : old('terms_en', '') }}</textarea>
                            @error('terms_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            @error('terms_en.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="terms_ar" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.coupons_section.terms_ar') }}
                                <span class="text-gray-400 font-normal">{{ __('admin.coupons_section.terms_hint') }}</span>
                            </label>
                            <textarea
                                id="terms_ar"
                                name="terms_ar"
                                dir="rtl"
                                rows="4"
                                class="input w-full @error('terms_ar') border-red-400 @enderror"
                            >{{ $isEdit ? implode("\n", old('terms_ar', $coupon->terms_ar ?? [])) : old('terms_ar', '') }}</textarea>
                            @error('terms_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            @error('terms_ar.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Max orders per customer per month --}}
                    <div>
                        <label for="max_orders_per_customer_per_month" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.max_orders_per_customer_per_month') }}
                            <span class="text-gray-400 font-normal">{{ __('admin.coupons_section.unlimited_hint') }}</span>
                        </label>
                        <input
                            type="number"
                            id="max_orders_per_customer_per_month"
                            name="max_orders_per_customer_per_month"
                            value="{{ $val('max_orders_per_customer_per_month') }}"
                            class="input w-40 @error('max_orders_per_customer_per_month') border-red-400 @enderror"
                            min="1"
                        />
                        @error('max_orders_per_customer_per_month') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Discount Type & Value ──────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.discount') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    {{-- Type --}}
                    <div>
                        <label for="type" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.type') }} <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="type"
                            name="type"
                            x-model="type"
                            class="input w-full @error('type') border-red-400 @enderror"
                            required
                        >
                            <option value="percentage"   {{ $currentType === 'percentage'   ? 'selected' : '' }}>{{ __('admin.coupons_section.percentage_pct') }}</option>
                            <option value="fixed_amount" {{ $currentType === 'fixed_amount' ? 'selected' : '' }}>{{ __('admin.coupons_section.fixed_amount') }}</option>
                            <option value="free_shipping"{{ $currentType === 'free_shipping'? 'selected' : '' }}>{{ __('admin.coupons_section.free_shipping') }}</option>
                            <option value="bogo"         {{ $currentType === 'bogo'         ? 'selected' : '' }}>{{ __('admin.coupons_section.bogo_full') }}</option>
                        </select>
                        @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Value (hidden for free_shipping) --}}
                    <div x-show="type !== 'free_shipping'">
                        <label for="value" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.value') }} <span class="text-red-500">*</span>
                            <span x-show="type === 'percentage'" class="text-gray-400 font-normal">{{ __('admin.coupons_section.value_range_hint') }}</span>
                        </label>
                        <input
                            type="number"
                            id="value"
                            name="value"
                            value="{{ $val('value', 0) }}"
                            class="input w-full @error('value') border-red-400 @enderror"
                            step="0.01"
                            min="0"
                        />
                        @error('value') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Currency (required for fixed_amount/bogo) --}}
                    <div x-show="type === 'fixed_amount' || type === 'bogo'">
                        <label for="currency" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.coupons_section.currency') }}</label>
                        <input
                            type="text"
                            id="currency"
                            name="currency"
                            value="{{ $val('currency') }}"
                            class="input w-32 font-mono uppercase @error('currency') border-red-400 @enderror"
                            placeholder="EGP"
                            maxlength="3"
                            oninput="this.value = this.value.toUpperCase()"
                            :required="type === 'fixed_amount' || type === 'bogo'"
                        />
                        @error('currency') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Max Discount (only for percentage) --}}
                    <div x-show="type === 'percentage'">
                        <label for="max_discount" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.max_discount_cap') }}
                            <span class="text-gray-400 font-normal">{{ __('admin.coupons_section.max_discount_cap_hint') }}</span>
                        </label>
                        <input
                            type="number"
                            id="max_discount"
                            name="max_discount"
                            value="{{ $val('max_discount') }}"
                            class="input w-full @error('max_discount') border-red-400 @enderror"
                            min="0"
                            placeholder="{{ __('admin.coupons_section.max_discount_placeholder') }}"
                        />
                        @error('max_discount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Scope ─────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.scope') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div>
                        <label for="scope" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.applies_to_required') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="scope" value="platform" x-model="scope" class="text-blue-600" {{ $currentScope === 'platform' ? 'checked' : '' }} required />
                                <span class="text-sm text-gray-700">{{ __('admin.coupons_section.platform_all_products') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="scope" value="category" x-model="scope" class="text-blue-600" {{ $currentScope === 'category' ? 'checked' : '' }} required />
                                <span class="text-sm text-gray-700">{{ __('admin.coupons_section.specific_category') }}</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">{{ __('admin.coupons_section.admin_scope_hint') }}</p>
                        @error('scope') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Category picker --}}
                    <div x-show="scope === 'category'">
                        <label for="category_id" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.coupons_section.category') }}</label>
                        <select
                            id="category_id"
                            name="category_id"
                            class="input w-full @error('category_id') border-red-400 @enderror"
                            :required="scope === 'category'"
                        >
                            <option value="">{{ __('admin.coupons_section.select_category') }}</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ $val('category_id') === $cat->id ? 'selected' : '' }}>
                                    {{ e($cat->name_en) }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Usage Limits ───────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.usage_limits') }}</h2>
                </div>
                <div class="px-5 py-5 grid grid-cols-2 gap-4">

                    <div>
                        <label for="usage_limit_total" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.total_uses') }}
                            <span class="text-gray-400 font-normal">{{ __('admin.coupons_section.unlimited_hint') }}</span>
                        </label>
                        <input
                            type="number"
                            id="usage_limit_total"
                            name="usage_limit_total"
                            value="{{ $val('usage_limit_total') }}"
                            class="input w-full @error('usage_limit_total') border-red-400 @enderror"
                            min="1"
                            placeholder="∞"
                        />
                        @error('usage_limit_total') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="usage_limit_per_customer" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.per_customer') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="usage_limit_per_customer"
                            name="usage_limit_per_customer"
                            value="{{ $val('usage_limit_per_customer', 1) }}"
                            class="input w-full @error('usage_limit_per_customer') border-red-400 @enderror"
                            min="1"
                            required
                        />
                        @error('usage_limit_per_customer') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="min_order_amount" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.min_order_amount') }}
                            <span class="text-gray-400 font-normal">{{ __('admin.coupons_section.smallest_unit_hint') }}</span>
                        </label>
                        <input
                            type="number"
                            id="min_order_amount"
                            name="min_order_amount"
                            value="{{ $val('min_order_amount') }}"
                            class="input w-full @error('min_order_amount') border-red-400 @enderror"
                            min="0"
                            placeholder="{{ __('admin.coupons_section.min_order_placeholder') }}"
                        />
                        @error('min_order_amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="customer_eligibility" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.customer_eligibility') }} <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="customer_eligibility"
                            name="customer_eligibility"
                            x-model="eligibility"
                            class="input w-full @error('customer_eligibility') border-red-400 @enderror"
                            required
                        >
                            <option value="all"              {{ $currentEligibility === 'all'              ? 'selected' : '' }}>{{ __('admin.coupons_section.eligibility_all') }}</option>
                            <option value="new_customers"    {{ $currentEligibility === 'new_customers'    ? 'selected' : '' }}>{{ __('admin.coupons_section.eligibility_new') }}</option>
                            <option value="specific_segment" {{ $currentEligibility === 'specific_segment' ? 'selected' : '' }}>{{ __('admin.coupons_section.eligibility_segment') }}</option>
                            <option value="specific_users"   {{ $currentEligibility === 'specific_users'   ? 'selected' : '' }}>{{ __('admin.coupons_section.eligibility_users') }}</option>
                        </select>
                        @error('customer_eligibility') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Eligible customers (specific_users only) --}}
                    <div x-show="eligibility === 'specific_users'" class="col-span-2">
                        <label for="eligible_customer_ids" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.coupons_section.eligible_customers') }}</label>
                        <select
                            id="eligible_customer_ids"
                            name="eligible_customer_ids[]"
                            multiple
                            class="input w-full @error('eligible_customer_ids') border-red-400 @enderror"
                            data-async-select
                            data-config="{{ json_encode(['url' => route('admin.coupons.search-customers'), 'param' => 'q', 'minLength' => 2], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                            placeholder="{{ __('admin.coupons_section.select_customers') }}"
                        >
                            @foreach($selectedCustomers as $customer)
                                <option value="{{ $customer->id }}" selected>{{ e($customer->name) }} ({{ e($customer->email) }})</option>
                            @endforeach
                        </select>
                        @error('eligible_customer_ids') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        @error('eligible_customer_ids.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Restrictions ────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.countries') }}</h2>
                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.coupons_section.countries_hint') }}</p>
                </div>
                <div class="px-5 py-5">
                    <select
                        id="country_ids"
                        name="country_ids[]"
                        multiple
                        class="input w-full @error('country_ids') border-red-400 @enderror"
                        data-select2-init
                    >
                        <option value=""></option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ in_array($country->id, $selectedCountryIds, true) ? 'selected' : '' }}>
                                {{ e($country->name_en) }}
                            </option>
                        @endforeach
                    </select>
                    @error('country_ids') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    @error('country_ids.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Funding ───────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.funding') }}</h2>
                </div>
                <div class="px-5 py-5 grid grid-cols-2 gap-4">
                    <div>
                        <label for="funded_by" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.funded_by') }} <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="funded_by"
                            name="funded_by"
                            x-model="fundedBy"
                            class="input w-full @error('funded_by') border-red-400 @enderror"
                            required
                        >
                            <option value="platform" {{ $currentFundedBy === 'platform' ? 'selected' : '' }}>{{ __('admin.coupons_section.funded_by_platform') }}</option>
                            <option value="shared"   {{ $currentFundedBy === 'shared'   ? 'selected' : '' }}>{{ __('admin.coupons_section.funded_by_shared') }}</option>
                        </select>
                        @error('funded_by') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="fundedBy === 'shared'">
                        <label for="vendor_share_pct" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.vendor_share_pct') }}
                            <span class="text-gray-400 font-normal">{{ __('admin.coupons_section.vendor_share_pct_hint') }}</span>
                        </label>
                        <input
                            type="number"
                            id="vendor_share_pct"
                            name="vendor_share_pct"
                            value="{{ $val('vendor_share_pct') }}"
                            class="input w-full @error('vendor_share_pct') border-red-400 @enderror"
                            min="0"
                            max="100"
                            :required="fundedBy === 'shared'"
                        />
                        @error('vendor_share_pct') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Validity Period ─────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.validity_period') }}</h2>
                </div>
                <div class="px-5 py-5 grid grid-cols-2 gap-4">

                    <div>
                        <label for="valid_from" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.valid_from') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="valid_from"
                            name="valid_from"
                            value="{{ $val('valid_from') }}"
                            class="input w-full flatpickr-input @error('valid_from') border-red-400 @enderror"
                            data-flatpickr
                            data-enable-time="true"
                            placeholder="YYYY-MM-DD HH:MM"
                            required
                        />
                        @error('valid_from') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="valid_until" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.coupons_section.valid_until') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="valid_until"
                            name="valid_until"
                            value="{{ $val('valid_until') }}"
                            class="input w-full flatpickr-input @error('valid_until') border-red-400 @enderror"
                            data-flatpickr
                            data-enable-time="true"
                            placeholder="YYYY-MM-DD HH:MM"
                            required
                        />
                        @error('valid_until') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT: sidebar options                                         --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-72 shrink-0 space-y-4">

            {{-- Status card --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.status') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="hidden"
                            name="is_active"
                            value="0"
                        />
                        <input
                            type="checkbox"
                            id="is_active"
                            name="is_active"
                            value="1"
                            {{ $bool('is_active', true) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                        />
                        <span class="text-sm text-gray-700">{{ __('admin.coupons_section.active') }}</span>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="hidden"
                            name="is_stackable"
                            value="0"
                        />
                        <input
                            type="checkbox"
                            id="is_stackable"
                            name="is_stackable"
                            value="1"
                            {{ $bool('is_stackable', false) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                        />
                        <span class="text-sm text-gray-700">{{ __('admin.coupons_section.stackable') }}
                            <span class="text-xs text-gray-400">{{ __('admin.coupons_section.stackable_hint') }}</span>
                        </span>
                    </label>

                </div>
            </div>

            {{-- Usage stats (edit mode only) --}}
            @if($isEdit)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.coupons_section.usage_stats') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-2 text-sm text-gray-700">
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('admin.coupons_section.used_count') }}</span>
                        <span class="font-medium">{{ number_format($coupon->times_used) }}</span>
                    </div>
                    @if($coupon->usage_limit_total)
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ __('admin.coupons_section.limit') }}</span>
                        <span class="font-medium">{{ number_format($coupon->usage_limit_total) }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 mt-1">
                        <div
                            class="bg-blue-500 h-1.5 rounded-full"
                            style="width: {{ min(100, round(($coupon->times_used / $coupon->usage_limit_total) * 100)) }}%"
                        ></div>
                    </div>
                    @endif
                    <div class="flex justify-between pt-1">
                        <span class="text-gray-500">{{ __('admin.coupons_section.db_usages') }}</span>
                        <span class="font-medium">{{ number_format($usageCount) }}</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Summary card --}}
            <div class="bg-blue-50 rounded-xl border border-blue-200 px-5 py-4 text-xs text-blue-700 space-y-1">
                <p class="font-semibold">{{ __('admin.coupons_section.how_values_work') }}</p>
                <p>• {{ __('admin.coupons_section.percentage_explain') }}</p>
                <p>• {{ __('admin.coupons_section.fixed_amount_explain') }}</p>
                <p>• {{ __('admin.coupons_section.free_shipping_explain') }}</p>
                <p>• {{ __('admin.coupons_section.bogo_explain') }}</p>
                <p class="pt-1">{{ __('admin.coupons_section.amounts_stored_hint') }}</p>
            </div>

        </div>

    </div>
</div>
