{{--
    Shared Voucher form partial.
    Include with: @include('admin.vouchers._form', ['mode' => 'create'])
                  @include('admin.vouchers._form', ['mode' => 'edit', 'voucher' => $voucher])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
    Requires $countries from the controller.
--}}
@php
    $voucher = $voucher ?? null;
    $isEdit = $voucher !== null;

    $val = function (string $field, $default = '') use ($isEdit, $voucher) {
        $current = $isEdit ? ($voucher->{$field} ?? $default) : $default;
        if ($current instanceof \Illuminate\Support\Carbon) {
            $current = $current->format('Y-m-d H:i');
        }
        return old($field, $current);
    };

    $bool = function (string $field, bool $default = false) use ($isEdit, $voucher): bool {
        $raw = old($field, $isEdit ? ($voucher->{$field} ?? $default) : $default);
        return (bool) $raw;
    };

    $currentEligibility = old('customer_eligibility', $isEdit ? $voucher->customer_eligibility : 'all');
    $selectedCustomers = $selectedCustomers ?? collect();
    $countries = $countries ?? collect();
@endphp

<div class="space-y-6" x-data="{ eligibility: '{{ $currentEligibility }}' }">

    {{-- ─── Page header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">
            {{ $isEdit ? __('admin.vouchers_section.edit_voucher') . ': ' . e($voucher->code) : __('admin.vouchers_section.new') }}
        </h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.vouchers.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary" id="save-btn">
                {{ $isEdit ? __('admin.vouchers_section.update_voucher') : __('admin.vouchers_section.create_voucher') }}
            </button>
        </div>
    </div>

    {{-- SECTION 1 — Code & Labels ─────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.vouchers_section.code_labels') }}</h2>
        </div>
        <div class="px-5 py-5 space-y-4">

            <div>
                <label for="code" class="block text-xs font-medium text-gray-700 mb-1">
                    {{ __('admin.vouchers_section.code') }} <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    value="{{ strtoupper($val('code')) }}"
                    class="input w-full font-mono uppercase @error('code') border-red-400 @enderror"
                    placeholder="{{ __('admin.vouchers_section.code_hint') }}"
                    maxlength="50"
                    oninput="this.value = this.value.toUpperCase()"
                    required
                />
                @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 mb-1">
                    {{ __('admin.vouchers_section.name') }} <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ $val('name') }}"
                    class="input w-full @error('name') border-red-400 @enderror"
                    maxlength="150"
                    required
                />
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.description') }}</label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="input w-full @error('description') border-red-400 @enderror"
                >{{ $val('description') }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="title_en" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.title_en') }}</label>
                    <input
                        type="text"
                        id="title_en"
                        name="title_en"
                        value="{{ $val('title_en') }}"
                        class="input w-full @error('title_en') border-red-400 @enderror"
                        maxlength="255"
                    />
                    @error('title_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="title_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.title_ar') }}</label>
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

        </div>
    </div>

    {{-- SECTION 2 — Value ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.vouchers_section.value_section') }}</h2>
        </div>
        <div class="px-5 py-5 grid grid-cols-2 gap-4">

            <div>
                <label for="amount" class="block text-xs font-medium text-gray-700 mb-1">
                    {{ __('admin.vouchers_section.amount') }} <span class="text-red-500">*</span>
                    <span class="text-gray-400 font-normal block">{{ __('admin.vouchers_section.amount_hint') }}</span>
                </label>
                <input
                    type="number"
                    id="amount"
                    name="amount"
                    value="{{ $val('amount') }}"
                    class="input w-full @error('amount') border-red-400 @enderror"
                    min="1"
                    step="1"
                    required
                />
                @error('amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="currency_code" class="block text-xs font-medium text-gray-700 mb-1">
                    {{ __('admin.vouchers_section.currency') }} <span class="text-red-500">*</span>
                </label>
                <select
                    id="currency_code"
                    name="currency_code"
                    class="input w-full @error('currency_code') border-red-400 @enderror"
                    required
                >
                    @foreach(['SAR', 'AED', 'EGP', 'KWD', 'OMR', 'QAR', 'BHD', 'JOD'] as $code)
                        <option value="{{ $code }}" {{ $val('currency_code', 'SAR') === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
                @error('currency_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

        </div>
    </div>

    {{-- SECTION 3 — Restrictions ──────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.vouchers_section.restrictions') }}</h2>
        </div>
        <div class="px-5 py-5 space-y-4">

            <div>
                <label for="country_id" class="block text-xs font-medium text-gray-700 mb-1">
                    {{ __('admin.vouchers_section.country') }}
                    <span class="text-gray-400 font-normal">{{ __('admin.vouchers_section.all_countries') }}</span>
                </label>
                <select
                    id="country_id"
                    name="country_id"
                    class="input w-full @error('country_id') border-red-400 @enderror"
                    data-select2-init
                >
                    <option value="">{{ __('admin.vouchers_section.all_countries') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" {{ $val('country_id') === $country->id ? 'selected' : '' }}>
                            {{ e($country->name_en) }}
                        </option>
                    @endforeach
                </select>
                @error('country_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="customer_eligibility" class="block text-xs font-medium text-gray-700 mb-1">
                    {{ __('admin.vouchers_section.customer_eligibility') }} <span class="text-red-500">*</span>
                </label>
                <select
                    id="customer_eligibility"
                    name="customer_eligibility"
                    x-model="eligibility"
                    class="input w-full @error('customer_eligibility') border-red-400 @enderror"
                    required
                >
                    <option value="all" {{ $currentEligibility === 'all' ? 'selected' : '' }}>{{ __('admin.vouchers_section.eligibility_all') }}</option>
                    <option value="new_customers" {{ $currentEligibility === 'new_customers' ? 'selected' : '' }}>{{ __('admin.vouchers_section.eligibility_new') }}</option>
                    <option value="specific_users" {{ $currentEligibility === 'specific_users' ? 'selected' : '' }}>{{ __('admin.vouchers_section.eligibility_users') }}</option>
                </select>
                @error('customer_eligibility') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div x-show="eligibility === 'specific_users'">
                <label for="eligible_customer_ids" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.vouchers_section.eligible_customers') }}</label>
                <select
                    id="eligible_customer_ids"
                    name="eligible_customer_ids[]"
                    multiple
                    class="input w-full @error('eligible_customer_ids') border-red-400 @enderror"
                    data-async-select
                    data-config="{{ json_encode(['url' => route('admin.coupons.search-customers'), 'param' => 'q', 'minLength' => 2], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                    placeholder="{{ __('admin.vouchers_section.select_customers') }}"
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

    {{-- SECTION 4 — Limits ────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.vouchers_section.limits') }}</h2>
        </div>
        <div class="px-5 py-5 grid grid-cols-2 gap-4">

            <div>
                <label for="usage_limit_total" class="block text-xs font-medium text-gray-700 mb-1">
                    {{ __('admin.vouchers_section.total_usage_limit') }}
                    <span class="text-gray-400 font-normal">{{ __('admin.vouchers_section.unlimited_hint') }}</span>
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
                    {{ __('admin.vouchers_section.per_customer_limit') }} <span class="text-red-500">*</span>
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

        </div>
    </div>

    {{-- SECTION 5 — Validity ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.vouchers_section.validity') }}</h2>
        </div>
        <div class="px-5 py-5 space-y-4">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="valid_from" class="block text-xs font-medium text-gray-700 mb-1">
                        {{ __('admin.vouchers_section.valid_from') }} <span class="text-red-500">*</span>
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
                        {{ __('admin.vouchers_section.valid_until') }} <span class="text-red-500">*</span>
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

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="hidden" name="is_active" value="0" />
                <input
                    type="checkbox"
                    id="is_active"
                    name="is_active"
                    value="1"
                    {{ $bool('is_active', true) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                />
                <span class="text-sm text-gray-700">{{ __('admin.vouchers_section.is_active') }}</span>
            </label>

        </div>
    </div>

</div>
