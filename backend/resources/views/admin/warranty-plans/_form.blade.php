{{--
    Shared Warranty Plan form partial.
    Include with: @include('admin.warranty-plans._form', ['mode' => 'create'])
                  @include('admin.warranty-plans._form', ['mode' => 'edit', 'plan' => $plan])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
--}}
@php
    $plan = $plan ?? null;
    $isEdit = $plan !== null;

    $val = function (string $field, $default = '') use ($isEdit, $plan) {
        return old($field, $isEdit ? ($plan->{$field} ?? $default) : $default);
    };

    $bool = function (string $field, bool $default = false) use ($isEdit, $plan): bool {
        $raw = old($field, $isEdit ? ($plan->{$field} ?? $default) : $default);
        return (bool) $raw;
    };

    $featuresEn = old('features_en', $isEdit ? ($plan->features_en ?? ['']) : ['']);
    $featuresAr = old('features_ar', $isEdit ? ($plan->features_ar ?? ['']) : ['']);
    $selectedCountryIds = old('country_ids', $isEdit ? ($plan->country_ids ?? []) : []);
@endphp

<div class="space-y-6" x-data="{
        priceAmount: {{ (float) ((int) $val('price', 0)) }},
        currency: '{{ $val('currency', 'AED') }}',
        featuresEn: {{ json_encode(array_values($featuresEn) ?: ['']) }},
        featuresAr: {{ json_encode(array_values($featuresAr) ?: ['']) }},
    }">
    <input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">

    {{-- ─── Page header ──────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $isEdit ? e($plan->name_en) : __('admin.warranty_plans.new_plan') }}
            </h1>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- LEFT: English fields                                            --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warranty_plans.english_label') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div>
                        <label for="name_en" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('common.name') }} (EN) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name_en" name="name_en" value="{{ $val('name_en') }}"
                            class="input w-full @error('name_en') border-red-400 @enderror" dir="ltr" required>
                        @error('name_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.warranty_plans.features_en') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <template x-for="(feature, i) in featuresEn" :key="'en-' + i">
                                <div class="flex items-center gap-2">
                                    <input type="text" :name="'features_en[' + i + ']'" x-model="featuresEn[i]"
                                        class="input w-full" dir="ltr" maxlength="200" required>
                                    <button type="button" class="btn btn-ghost btn-sm text-red-500"
                                        x-show="featuresEn.length > 1" @click="featuresEn.splice(i, 1)">
                                        <x-heroicon name="trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="btn btn-ghost btn-sm" x-show="featuresEn.length < 8"
                                @click="featuresEn.push('')">
                                + {{ __('admin.warranty_plans.add_feature') }}
                            </button>
                        </div>
                        @error('features_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT: Arabic fields                                            --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100" dir="rtl">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warranty_plans.arabic_label') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div>
                        <label for="name_ar" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('common.name') }} (AR) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name_ar" name="name_ar" value="{{ $val('name_ar') }}"
                            class="input w-full @error('name_ar') border-red-400 @enderror" dir="rtl" required>
                        @error('name_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.warranty_plans.features_ar') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="space-y-2">
                            <template x-for="(feature, i) in featuresAr" :key="'ar-' + i">
                                <div class="flex items-center gap-2">
                                    <input type="text" :name="'features_ar[' + i + ']'" x-model="featuresAr[i]"
                                        class="input w-full" dir="rtl" maxlength="200" required>
                                    <button type="button" class="btn btn-ghost btn-sm text-red-500"
                                        x-show="featuresAr.length > 1" @click="featuresAr.splice(i, 1)">
                                        <x-heroicon name="trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                            <button type="button" class="btn btn-ghost btn-sm" x-show="featuresAr.length < 8"
                                @click="featuresAr.push('')">
                                + {{ __('admin.warranty_plans.add_feature') }}
                            </button>
                        </div>
                        @error('features_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- Plan details (shared)                                                --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warranty_plans.plan_details') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div>
                        <label for="category_id" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.warranty_plans.category') }}
                            @unless($isEdit) <span class="text-red-500">*</span> @endunless
                        </label>
                        <select id="category_id" name="category_id" data-select2-init class="input w-full"
                            {{ $isEdit ? '' : 'required' }}>
                            <option value="">{{ __('admin.warranty_plans.category') }}</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (string) $val('category_id') === (string) $cat->id ? 'selected' : '' }}>
                                    {{ str_repeat('— ', $cat->depth ?? 0) . $cat->name_en }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="duration_months" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.warranty_plans.duration_months') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="duration_months" name="duration_months"
                                value="{{ $val('duration_months', 12) }}" min="1" max="120"
                                class="input w-full @error('duration_months') border-red-400 @enderror" required>
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.warranty_plans.duration_hint') }}</p>
                            @error('duration_months') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="sort_order" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.warranty_plans.sort_order') }}
                            </label>
                            <input type="number" id="sort_order" name="sort_order" value="{{ $val('sort_order', 0) }}"
                                min="0" class="input w-full @error('sort_order') border-red-400 @enderror">
                            @error('sort_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="price" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.warranty_plans.price') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="price" name="price" x-model.number="priceAmount"
                                min="1" step="1" class="input w-full @error('price') border-red-400 @enderror" required>
                            <p class="text-xs text-gray-500 mt-1">
                                ≈ <span x-text="(priceAmount).toFixed(2)"></span> <span x-text="currency"></span>
                            </p>
                            @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="currency" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.warranty_plans.currency') }} <span class="text-red-500">*</span>
                            </label>
                            <select id="currency" name="currency" x-model="currency"
                                class="input w-full @error('currency') border-red-400 @enderror" required>
                                @foreach ($currencies as $code)
                                    <option value="{{ $code }}" {{ $val('currency', 'AED') === $code ? 'selected' : '' }}>{{ $code }}</option>
                                @endforeach
                            </select>
                            @error('currency') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="country_ids" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.warranty_plans.country_restrictions') }}
                        </label>
                        <select id="country_ids" name="country_ids[]" data-select2-init multiple class="input w-full">
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" {{ in_array($country->id, $selectedCountryIds ?? []) ? 'selected' : '' }}>
                                    {{ $country->name_en }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">{{ __('admin.warranty_plans.all_countries_placeholder') }}</p>
                        @error('country_ids') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT: sidebar                                                  --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="space-y-4">

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warranty_plans.save_label') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-3">
                    <button type="submit" class="btn btn-primary w-full">
                        <x-heroicon name="check" class="w-4 h-4 mr-1.5" />
                        {{ $isEdit ? __('admin.warranty_plans.update_plan') : __('admin.warranty_plans.create_plan') }}
                    </button>
                    <a href="{{ route('admin.warranty-plans.index') }}" class="btn btn-ghost w-full">
                        {{ __('common.cancel') }}
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.warranty_plans.is_active') }}</h2>
                </div>
                <div class="px-5 py-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">{{ __('admin.warranty_plans.is_active') }}</p>
                        <label class="relative inline-flex items-center cursor-pointer" dir="ltr">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer"
                                {{ $bool('is_active', true) ? 'checked' : '' }}>
                            <div class="relative w-10 h-5 bg-gray-200 peer-checked:bg-primary-600 rounded-full transition-colors duration-200"></div>
                            <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
                        </label>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
