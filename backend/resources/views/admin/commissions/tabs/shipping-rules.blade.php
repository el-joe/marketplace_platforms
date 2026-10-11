@php
    $canEdit = auth('admin')->user()->can('admin_listings.edit');
    $listing = $editor['listing'];
    $rule = $editor['rule'];
    $listingName = fn ($l) => ($l->productVariant?->product?->name_en ?: '—').' — '.\Illuminate\Support\Str::limit($l->id, 8, '');
    $typeValue = $rule ? ($rule->commission_type instanceof \BackedEnum ? $rule->commission_type->value : $rule->commission_type) : 'fixed';
@endphp

<form method="GET" action="{{ route('admin.commissions.index') }}" class="my-5 flex flex-wrap items-end gap-3">
    <input type="hidden" name="tab" value="shipping-rules">
    <div class="min-w-[16rem] flex-1">
        <label for="sr-listing" class="{{ $label }}">{{ __('commissions.shipping_form.choose_listing') }}</label>
        <select id="sr-listing" name="listing" class="{{ $input }}" onchange="this.form.submit()">
            <option value="">—</option>
            @foreach ($editor['listings'] as $l)
                <option value="{{ $l->id }}" @selected($listing?->id === $l->id)>{{ $listingName($l) }}</option>
            @endforeach
        </select>
    </div>
    <noscript><button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.vendor_form.load') }}</button></noscript>
</form>
<p class="-mt-2 mb-3 text-xs text-gray-500">{{ __('commissions.shipping_form.admin_only') }}</p>

@if ($listing && $canEdit)
    <form method="POST" action="{{ route('admin.admin-listings.save-shipping-rule', $listing->id) }}" data-ajax class="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
        @csrf
        <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ $listingName($listing) }}</h3>

        <fieldset class="rounded-md border border-amber-200 bg-amber-50 p-3">
            <legend class="px-1 text-xs font-semibold text-amber-900">{{ __('commissions.shipping_form.not_applied_legend') }}</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="sr-type" class="{{ $label }}">{{ __('commissions.columns.commission_type') }}</label>
                    <select id="sr-type" name="commission_type" class="{{ $input }}">
                        @foreach (\App\Enums\MarketplaceShippingRuleCommissionType::cases() as $case)
                            <option value="{{ $case->value }}" @selected($typeValue === $case->value)>{{ $case->value }}</option>
                        @endforeach
                    </select>
                    <p data-error-for="commission_type" class="hidden text-xs text-red-700"></p>
                </div>
                <div>
                    <label for="sr-value" class="{{ $label }}">{{ __('commissions.columns.commission_value') }}</label>
                    <input id="sr-value" type="number" name="commission_value" min="0" step="0.01" required value="{{ $rule?->commission_value ?? 0 }}" class="{{ $input }}">
                    <p data-error-for="commission_value" class="hidden text-xs text-red-700"></p>
                </div>
            </div>
            <p class="mt-2 text-xs text-amber-900">{{ __('commissions.notices.shipping_commission_unused') }}</p>
        </fieldset>

        <fieldset class="mt-4 rounded-md border border-green-200 bg-green-50 p-3">
            <legend class="px-1 text-xs font-semibold text-green-900">{{ __('commissions.shipping_form.applied_legend') }}</legend>
            <div class="max-w-xs">
                <label for="sr-fee" class="{{ $label }}">{{ __('commissions.columns.extra_delivery_fee') }}</label>
                <input id="sr-fee" type="number" name="extra_delivery_fee" min="0" step="1" value="{{ $rule?->extra_delivery_fee ?? 0 }}" class="{{ $input }}">
                <p data-error-for="extra_delivery_fee" class="hidden text-xs text-red-700"></p>
            </div>
            <p class="mt-2 text-xs text-green-900">{{ __('commissions.notices.shipping_extra_fee_applied') }}</p>
        </fieldset>

        {{-- The endpoint rewrites the whole row, so the handling fields are carried through unchanged. --}}
        <input type="hidden" name="requires_special_vehicle" value="{{ $rule?->requires_special_vehicle ? 1 : 0 }}">
        <input type="hidden" name="requires_refrigeration" value="{{ $rule?->requires_refrigeration ? 1 : 0 }}">
        <input type="hidden" name="max_weight_kg" value="{{ $rule?->max_weight_kg }}">
        <input type="hidden" name="max_dimensions_cm" value="{{ $rule?->max_dimensions_cm }}">
        <input type="hidden" name="special_handling_notes" value="{{ $rule?->special_handling_notes }}">

        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="{{ $btnPrimary }}">{{ __('commissions.rule_form.save') }}</button>
            <span data-status role="status" class="text-xs"></span>
        </div>
    </form>
@endif

@foreach ($content['tables'] as $table)
    @include('admin.commissions.partials.table', ['table' => $table])
@endforeach
