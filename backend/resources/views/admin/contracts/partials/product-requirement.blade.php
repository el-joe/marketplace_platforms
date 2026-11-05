{{--
    "Requires individual contract" for one product: Inherit (follow the category), Yes (always needs its own
    contract) or No/exempt (covered by the global Seller Agreement even when its category requires a contract).

    Expects: $subject (App\Models\Product or null).
--}}
@php
    $service = app(\App\Services\Vendor\ContractAssignmentService::class);
    $contractData = $service->formData('product', $subject);
    $contractGaps = $subject ? $service->gaps($subject, 'product') : collect();
    $current = old('contract_requirement', $subject?->contractRequirementValue() ?? 'inherit');
    $legacyTemplates = $contractData['templates']->whereNull('country_id');
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3" x-data="{ mode: @js($current) }">
    <h3 class="text-sm font-semibold text-gray-800">{{ __('contracts.admin.product_requires_title') }}</h3>
    <p class="text-xs text-gray-500">{{ __('contracts.admin.product_requires_help') }}</p>

    <div class="space-y-1.5 text-sm">
        @foreach (['inherit' => 'mode_inherit', 'required' => 'mode_required', 'exempt' => 'mode_exempt'] as $value => $label)
            <label class="flex items-start gap-2">
                <input type="radio" name="contract_requirement" value="{{ $value }}" x-model="mode" class="mt-1">
                <span>{{ __('contracts.admin.'.$label) }}</span>
            </label>
        @endforeach
    </div>
    @error('contract_requirement')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

    <div x-show="mode !== 'exempt'" x-cloak class="space-y-3 rounded-lg border border-gray-100 bg-gray-50 p-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-700">{{ __('contracts.admin.product_template') }}</label>
            <select name="contract_template_id" class="form-input w-full text-sm">
                <option value="">{{ __('contracts.admin.use_category_template') }}</option>
                @foreach ($legacyTemplates as $template)
                    <option value="{{ $template->id }}" @selected(old('contract_template_id', $subject?->contract_template_id) === $template->id)>
                        {{ $template->name }} (v{{ $template->version }})
                    </option>
                @endforeach
            </select>
        </div>

        @if ($contractData['countries']->isNotEmpty())
            <div class="space-y-2">
                <p class="text-xs font-medium text-gray-700">{{ __('contracts.admin.template_per_country') }}</p>
                @foreach ($contractData['countries'] as $country)
                    @php($countryTemplates = $contractData['templates']->where('country_id', $country->id))
                    <div class="flex items-center gap-2">
                        <span class="w-28 shrink-0 text-xs text-gray-600">{{ app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en }}</span>
                        <select name="country_contracts[{{ $country->id }}]" class="form-input w-full text-xs" @disabled($countryTemplates->isEmpty())>
                            <option value="">{{ $countryTemplates->isEmpty() ? __('contracts.admin.no_template_for_country') : __('contracts.admin.use_category_template') }}</option>
                            @foreach ($countryTemplates as $template)
                                <option value="{{ $template->id }}" @selected(old('country_contracts.'.$country->id, $contractData['selected'][$country->id] ?? null) === $template->id)>
                                    {{ $template->name }} (v{{ $template->version }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($contractGaps->isNotEmpty() && $current === 'required')
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                {{ __('contracts.admin.gap_warning', ['countries' => $contractGaps->map(fn ($country) => app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en)->implode(', ')]) }}
            </div>
        @endif
    </div>
</div>
