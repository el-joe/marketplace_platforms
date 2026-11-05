{{--
    "Requires individual contract" switch for a product category or a classified category.
    Yes: sellers sign a separate contract (template per country, or the all-countries one) before listing.
    No: only the global Seller Agreement of the seller's country applies, nothing is signed per category.

    Expects: $scope (product|classified), $subject (model or null), $contractData (ContractAssignmentService::formData),
    optional $contractGaps (countries with sellers that have no usable contract yet).
--}}
@php
    $requires = old('requires_contract', $subject ? ($subject->requiresIndividualContract() ? '1' : '0') : '0');
    $requires = in_array($requires, [true, 1, '1'], true) ? '1' : '0';
    $legacyTemplates = $contractData['templates']->whereNull('country_id');
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3" x-data="{ requires: @js($requires) }">
    <h3 class="text-sm font-semibold text-gray-900">{{ __('contracts.admin.requires_title') }}</h3>
    <p class="text-xs text-gray-500">{{ __('contracts.admin.requires_help') }}</p>

    <div class="flex gap-5 text-sm">
        <label class="inline-flex items-center gap-2">
            <input type="radio" name="requires_contract" value="1" x-model="requires"> {{ __('contracts.admin.yes') }}
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="radio" name="requires_contract" value="0" x-model="requires"> {{ __('contracts.admin.no') }}
        </label>
    </div>
    @error('requires_contract')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    @error('country_contracts')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

    <div x-show="requires === '1'" x-cloak class="space-y-3 rounded-lg border border-gray-100 bg-gray-50 p-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">{{ __('contracts.admin.all_countries_template') }}</label>
            <select name="contract_template_id" class="form-input w-full text-sm">
                <option value="">{{ __('admin.contracts.no_template') }}</option>
                @foreach ($legacyTemplates as $template)
                    <option value="{{ $template->id }}" @selected(old('contract_template_id', $subject?->contract_template_id) === $template->id)>
                        {{ $template->name }} (v{{ $template->version }})
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-400">{{ __('contracts.admin.all_countries_template_hint') }}</p>
        </div>

        @if ($contractData['countries']->isNotEmpty())
            <div class="space-y-2">
                <p class="text-xs font-medium text-gray-700">{{ __('contracts.admin.template_per_country') }}</p>
                @foreach ($contractData['countries'] as $country)
                    @php($countryTemplates = $contractData['templates']->where('country_id', $country->id))
                    <div class="flex items-center gap-2">
                        <span class="w-32 shrink-0 text-xs text-gray-600">{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}</span>
                        <select name="country_contracts[{{ $country->id }}]" class="form-input w-full text-xs" @disabled($countryTemplates->isEmpty())>
                            <option value="">{{ $countryTemplates->isEmpty() ? __('contracts.admin.no_template_for_country') : __('contracts.admin.use_country_default') }}</option>
                            @foreach ($countryTemplates as $template)
                                <option value="{{ $template->id }}" @selected(old('country_contracts.'.$country->id, $contractData['selected'][$country->id] ?? null) === $template->id)>
                                    {{ $template->name }} (v{{ $template->version }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <p class="text-xs text-gray-400">{{ __('contracts.admin.template_per_country_hint') }}</p>
            </div>
        @endif

        @if (! empty($contractGaps) && $contractGaps->isNotEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                {{ __('contracts.admin.gap_warning', ['countries' => $contractGaps->map(fn ($country) => app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en)->implode(', ')]) }}
            </div>
        @endif
    </div>

    <p x-show="requires !== '1'" class="text-xs text-gray-400">{{ __('contracts.admin.requires_off_hint') }}</p>
</div>
