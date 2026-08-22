{{--
    Shared Cart Card Offer form partial.
    Include with: @include('admin.cart-card-offers._form', ['mode' => 'create'])
                  @include('admin.cart-card-offers._form', ['mode' => 'edit', 'offer' => $offer])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
    Requires countries variable from the controller.
--}}
@php
    $offer = $offer ?? null;
    $isEdit = $offer !== null;

    $val = function (string $field, $default = '') use ($isEdit, $offer) {
        $current = $isEdit ? ($offer->{$field} ?? $default) : $default;
        if ($current instanceof \BackedEnum) {
            $current = $current->value;
        }
        return old($field, $current);
    };

    $bool = function (string $field, bool $default = false) use ($isEdit, $offer): bool {
        $raw = old($field, $isEdit ? ($offer->{$field} ?? $default) : $default);
        return (bool) $raw;
    };

    $currentType = old('cashback_type', $isEdit ? $offer->cashback_type : 'percentage');
@endphp

<div class="space-y-6" x-data="{ cashbackType: '{{ $currentType }}' }">
    <input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">

    {{-- ─── Page header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $isEdit ? __('admin.cart_card_offers_section.edit_offer') . ': ' . e($offer->card_name_en) : __('admin.cart_card_offers_section.new_offer') }}
            </h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.cart-card-offers.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary" id="save-btn">
                {{ $isEdit ? __('admin.cart_card_offers_section.save_changes') : __('admin.cart_card_offers_section.create_offer') }}
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
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.cart_card_offers_section.offer_details') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    {{-- Country --}}
                    <div>
                        <label for="country_id" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.cart_card_offers_section.country') }} <span class="text-red-500">*</span>
                        </label>
                        <select id="country_id" name="country_id" data-select2-init required
                            class="input w-full @error('country_id') border-red-400 @enderror">
                            <option value="">{{ __('admin.cart_card_offers_section.select_country') }}</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}" {{ $val('country_id') === $country->id ? 'selected' : '' }}>
                                    {{ e($country->name_en) }}
                                </option>
                            @endforeach
                        </select>
                        @error('country_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Card Name --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="card_name_en" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.cart_card_offers_section.card_name_en') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="card_name_en" name="card_name_en" value="{{ $val('card_name_en') }}"
                                class="input w-full @error('card_name_en') border-red-400 @enderror" maxlength="150" required />
                            @error('card_name_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="card_name_ar" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.cart_card_offers_section.card_name_ar') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="card_name_ar" name="card_name_ar" dir="rtl" value="{{ $val('card_name_ar') }}"
                                class="input w-full @error('card_name_ar') border-red-400 @enderror" maxlength="150" required />
                            @error('card_name_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Bank Name --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="bank_name_en" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.bank_name_en') }}</label>
                            <input type="text" id="bank_name_en" name="bank_name_en" value="{{ $val('bank_name_en') }}"
                                class="input w-full @error('bank_name_en') border-red-400 @enderror" maxlength="100" />
                            @error('bank_name_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="bank_name_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.bank_name_ar') }}</label>
                            <input type="text" id="bank_name_ar" name="bank_name_ar" dir="rtl" value="{{ $val('bank_name_ar') }}"
                                class="input w-full @error('bank_name_ar') border-red-400 @enderror" maxlength="100" />
                            @error('bank_name_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Card Image --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.card_image') }}</label>
                        <input
                            type="file"
                            name="card_image"
                            id="card-image-input"
                            data-filepond
                            accept="image/*"
                            @if($isEdit && $offer->card_image_path)
                                data-existing='{{ json_encode([['id' => 'existing', 'url' => \Illuminate\Support\Facades\Storage::url($offer->card_image_path), 'name' => basename($offer->card_image_path)]]) }}'
                            @endif
                        />
                        @error('card_image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Cashback ───────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.cart_card_offers_section.cashback') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div>
                        <label for="cashback_type" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.cart_card_offers_section.cashback_type') }} <span class="text-red-500">*</span>
                        </label>
                        <select id="cashback_type" name="cashback_type" x-model="cashbackType"
                            class="input w-full @error('cashback_type') border-red-400 @enderror" required>
                            <option value="percentage" {{ $currentType === 'percentage' ? 'selected' : '' }}>{{ __('admin.cart_card_offers_section.percentage') }}</option>
                            <option value="fixed" {{ $currentType === 'fixed' ? 'selected' : '' }}>{{ __('admin.cart_card_offers_section.fixed') }}</option>
                        </select>
                        @error('cashback_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="cashbackType === 'percentage'">
                        <label for="cashback_pct" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.cashback_pct') }}</label>
                        <input type="number" id="cashback_pct" name="cashback_pct" value="{{ $val('cashback_pct', 0) }}"
                            class="input w-full @error('cashback_pct') border-red-400 @enderror" step="0.01" min="0" max="100" />
                        @error('cashback_pct') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="cashbackType === 'fixed'">
                        <label for="cashback_fixed_amount" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.cart_card_offers_section.cashback_fixed_amount') }}
                            <span class="text-gray-400 font-normal">{{ __('admin.cart_card_offers_section.base_currency_hint') }}</span>
                        </label>
                        <input type="number" id="cashback_fixed_amount" name="cashback_fixed_amount" value="{{ $val('cashback_fixed_amount', 0) }}"
                            class="input w-full @error('cashback_fixed_amount') border-red-400 @enderror" min="0" />
                        @error('cashback_fixed_amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="min_order_amount" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.cart_card_offers_section.min_order_amount') }}
                                <span class="text-gray-400 font-normal">{{ __('admin.cart_card_offers_section.min_order_hint') }}</span>
                            </label>
                            <input type="number" id="min_order_amount" name="min_order_amount" value="{{ $val('min_order_amount', 0) }}"
                                class="input w-full @error('min_order_amount') border-red-400 @enderror" min="0" />
                            @error('min_order_amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="max_cashback_amount" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.cart_card_offers_section.max_cashback_amount') }}
                                <span class="text-gray-400 font-normal">{{ __('admin.cart_card_offers_section.max_cashback_hint') }}</span>
                            </label>
                            <input type="number" id="max_cashback_amount" name="max_cashback_amount" value="{{ $val('max_cashback_amount') }}"
                                class="input w-full @error('max_cashback_amount') border-red-400 @enderror" min="0" />
                            @error('max_cashback_amount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Label & Apply ──────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.cart_card_offers_section.label_and_apply') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div>
                        <label for="label_template_en" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.cart_card_offers_section.label_template_en') }} <span class="text-red-500">*</span>
                            <span class="text-gray-400 font-normal">{{ __('admin.cart_card_offers_section.label_template_hint') }}</span>
                        </label>
                        <input type="text" id="label_template_en" name="label_template_en" value="{{ $val('label_template_en') }}"
                            class="input w-full @error('label_template_en') border-red-400 @enderror" maxlength="300" required />
                        @error('label_template_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="label_template_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.label_template_ar') }}</label>
                        <input type="text" id="label_template_ar" name="label_template_ar" dir="rtl" value="{{ $val('label_template_ar') }}"
                            class="input w-full @error('label_template_ar') border-red-400 @enderror" maxlength="300" />
                        @error('label_template_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="apply_url" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.apply_url') }}</label>
                        <input type="url" id="apply_url" name="apply_url" value="{{ $val('apply_url') }}"
                            class="input w-full @error('apply_url') border-red-400 @enderror" maxlength="500" placeholder="https://" />
                        @error('apply_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="apply_label_en" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.apply_label_en') }}</label>
                            <input type="text" id="apply_label_en" name="apply_label_en" value="{{ $val('apply_label_en', __('admin.cart_card_offers_section.apply_label_en_default')) }}"
                                class="input w-full @error('apply_label_en') border-red-400 @enderror" maxlength="100" />
                            @error('apply_label_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="apply_label_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.apply_label_ar') }}</label>
                            <input type="text" id="apply_label_ar" name="apply_label_ar" dir="rtl" value="{{ $val('apply_label_ar', __('admin.cart_card_offers_section.apply_label_ar_default')) }}"
                                class="input w-full @error('apply_label_ar') border-red-400 @enderror" maxlength="100" />
                            @error('apply_label_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Validity Period ───────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.cart_card_offers_section.validity_period') }}</h2>
                </div>
                <div class="px-5 py-5 grid grid-cols-2 gap-4">

                    <div>
                        <label for="valid_from" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.valid_from') }}</label>
                        <input type="text" id="valid_from" name="valid_from" value="{{ $val('valid_from') }}"
                            class="input w-full flatpickr-input @error('valid_from') border-red-400 @enderror"
                            data-flatpickr data-enable-time="true" placeholder="YYYY-MM-DD HH:MM" />
                        @error('valid_from') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="valid_until" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.valid_until') }}</label>
                        <input type="text" id="valid_until" name="valid_until" value="{{ $val('valid_until') }}"
                            class="input w-full flatpickr-input @error('valid_until') border-red-400 @enderror"
                            data-flatpickr data-enable-time="true" placeholder="YYYY-MM-DD HH:MM" />
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
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.cart_card_offers_section.status') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                            {{ $bool('is_active', true) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" />
                        <span class="text-sm text-gray-700">{{ __('admin.cart_card_offers_section.active') }}</span>
                    </label>

                    <div>
                        <label for="sort_order" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.cart_card_offers_section.sort_order') }}</label>
                        <input type="number" id="sort_order" name="sort_order" value="{{ $val('sort_order', 0) }}"
                            class="input w-full @error('sort_order') border-red-400 @enderror" min="0" />
                        @error('sort_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Summary card --}}
            <div class="bg-blue-50 rounded-xl border border-blue-200 px-5 py-4 text-xs text-blue-700 space-y-1">
                <p class="font-semibold">{{ __('admin.cart_card_offers_section.how_values_work') }}</p>
                <p>• {{ __('admin.cart_card_offers_section.placeholders_explain') }}</p>
                <p class="pt-1">{{ __('admin.cart_card_offers_section.amounts_stored_hint') }}</p>
            </div>

        </div>

    </div>
</div>
