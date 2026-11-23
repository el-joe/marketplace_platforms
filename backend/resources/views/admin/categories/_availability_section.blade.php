{{--
    Country availability for a category. Reads/writes the same country_categories
    rows as Countries → edit → "Category exceptions" (CategoryCountryAvailabilityService).
    Expects: $countries, $availability (['mode', 'country_ids']), $inheritedUnavailable (country ids), $category|null
--}}
@php
    $inheritedNames = $countries->whereIn('id', $inheritedUnavailable)->map(fn ($c) => localized($c))->values();
@endphp
<div class="space-y-5 p-6"
     x-data="{
        mode: @js(old('availability_mode', $availability['mode'])),
        selected: @js(array_values(old('availability_countries', $availability['country_ids']))),
        all: @js($countries->pluck('id')->values()),
     }">

    <fieldset>
        <legend class="mb-2 text-sm font-medium text-gray-700">{{ __('category_form.availability.title') }}</legend>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @foreach([
                \App\Services\CategoryCountryAvailabilityService::MODE_ALL => __('category_form.availability.mode_all'),
                \App\Services\CategoryCountryAvailabilityService::MODE_ONLY => __('category_form.availability.mode_only'),
                \App\Services\CategoryCountryAvailabilityService::MODE_EXCEPT => __('category_form.availability.mode_except'),
            ] as $modeValue => $modeLabel)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition-colors"
                       :class="mode === @js($modeValue) ? 'border-primary-500 bg-primary-50 text-primary-800' : 'border-gray-200 text-gray-700 hover:bg-gray-50'">
                    <input type="radio" name="availability_mode" value="{{ $modeValue }}" x-model="mode" class="text-primary-600">
                    {{ $modeLabel }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <div x-show="mode !== 'all'" x-cloak class="space-y-2">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700">{{ __('category_form.availability.pick_countries') }}</span>
            <div class="flex gap-3 text-xs">
                <button type="button" class="text-primary-600 hover:underline" @click="selected = [...all]">{{ __('category_form.availability.select_all') }}</button>
                <button type="button" class="text-gray-500 hover:underline" @click="selected = []">{{ __('category_form.availability.clear') }}</button>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($countries as $country)
                @php $status = $country->visibilityStatus(); @endphp
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                       :class="selected.includes(@js($country->id)) ? (mode === 'except' ? 'border-danger-300 bg-danger-50' : 'border-primary-300 bg-primary-50') : 'border-gray-200 hover:bg-gray-50'">
                    <input type="checkbox" name="availability_countries[]" value="{{ $country->id }}" x-model="selected" :disabled="mode === 'all'"
                           class="rounded border-gray-300 text-primary-600">
                    <span class="text-base leading-none">{{ $country->flag_emoji }}</span>
                    <span class="flex-1 truncate">{{ localized($country) }}</span>
                    @if($status === \App\Models\Country::STATUS_DRAFT)
                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500">{{ __('category_form.availability.status_draft') }}</span>
                    @elseif($status === \App\Models\Country::STATUS_HIDDEN)
                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">{{ __('category_form.availability.status_hidden') }}</span>
                    @endif
                </label>
            @endforeach
        </div>
    </div>

    @if($inheritedNames->isNotEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
            <span class="font-semibold">{{ __('category_form.availability.inherited_title') }}</span>
            {{ $inheritedNames->implode(app()->getLocale() === 'ar' ? '، ' : ', ') }}
        </div>
    @endif

    <div class="space-y-1 rounded-lg bg-gray-50 px-4 py-3 text-xs leading-relaxed text-gray-500">
        <p>{{ __('category_form.availability.hint') }}</p>
        <p>{{ __('category_form.availability.inherit_hint') }}</p>
    </div>
</div>
