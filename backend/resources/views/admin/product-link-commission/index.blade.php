@extends('layouts.admin')

@section('title', __('product_link.nav'))

@section('content')

<div class="mb-6">
    <h1 class="text-xl font-semibold text-gray-900">{{ __('product_link.admin.title') }}</h1>
</div>

<div class="mb-6 rounded-xl border border-primary-100 bg-primary-50/50 p-4 text-sm text-gray-700">
    <ul class="space-y-2 list-disc ps-5">
        <li>{{ __('product_link.admin.help.what') }}</li>
        <li>{{ __('product_link.admin.help.qualifying') }}</li>
        <li>{{ __('product_link.admin.help.precedence') }}</li>
        <li>{{ __('product_link.admin.help.scope') }}</li>
    </ul>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    @foreach($countries as $country)
        @php
            $rule = $rules->get($country->id);
            $errorBag = old('country_id') === $country->id ? $errors : new \Illuminate\Support\ViewErrorBag;
            $value = fn (string $field, $default) => old('country_id') === $country->id ? old($field, $default) : $default;
            $selectedModels = $value('fulfillment_models', $rule?->fulfillment_models ?? $models);
        @endphp
        <x-card :title="localized($country).' ('.$country->currency_code.')'">
            <p class="mb-3 text-xs text-gray-500" data-rule-summary="{{ $country->id }}">
                @if($rule && $rule->is_active)
                    {{ $rule->describe($country->currency_code) }} · {{ __('product_link.admin.fields.window') }}: {{ $rule->attribution_window_days }}
                @else
                    {{ __('product_link.admin.not_configured') }}
                @endif
            </p>
            <form method="POST" action="{{ route('admin.product-link-commission.update', $country) }}" class="space-y-3 text-sm">
                @csrf
                @method('PUT')
                <input type="hidden" name="country_id" value="{{ $country->id }}">
                <fieldset @disabled(! $canManage) class="space-y-3">
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked($value('is_active', $rule?->is_active ?? false))>
                        <span class="font-medium text-gray-700">{{ __('product_link.admin.fields.active') }}</span>
                    </label>
                    <div>
                        <span class="block font-medium text-gray-700 mb-1">{{ __('product_link.admin.fields.sale_models') }}</span>
                        <div class="flex flex-wrap gap-3">
                            @foreach($models as $model)
                                <label class="flex items-center gap-1">
                                    <input type="checkbox" name="fulfillment_models[]" value="{{ $model }}" @checked(in_array($model, (array) $selectedModels, true))>
                                    <span>{{ __('product_link.admin.models.'.$model) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if($errorBag->has('fulfillment_models'))<p class="text-xs text-danger-600 mt-1">{{ $errorBag->first('fulfillment_models') }}</p>@endif
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">{{ __('product_link.admin.fields.discount_type') }}</label>
                        <select name="discount_type" class="form-input w-full">
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected($value('discount_type', $rule?->discount_type ?? 'percent') === $type)>{{ __('product_link.admin.types.'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">{{ __('product_link.admin.fields.discount_percent') }}</label>
                            <input type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ $value('discount_percent', $rule ? (float) $rule->discount_percent : '') }}" class="form-input w-full">
                            @if($errorBag->has('discount_percent'))<p class="text-xs text-danger-600 mt-1">{{ $errorBag->first('discount_percent') }}</p>@endif
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">{{ __('product_link.admin.fields.discount_fixed') }} ({{ $country->currency_code }})</label>
                            <input type="number" step="0.01" min="0" name="discount_fixed" value="{{ $value('discount_fixed', $rule ? (float) $rule->discount_fixed : '') }}" class="form-input w-full">
                            @if($errorBag->has('discount_fixed'))<p class="text-xs text-danger-600 mt-1">{{ $errorBag->first('discount_fixed') }}</p>@endif
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">{{ __('product_link.admin.fields.window') }}</label>
                            <input type="number" min="1" max="{{ \App\Models\ProductLinkCommissionRule::MAX_WINDOW_DAYS }}" name="attribution_window_days" value="{{ $value('attribution_window_days', $rule?->attribution_window_days ?? \App\Models\ProductLinkCommissionRule::DEFAULT_WINDOW_DAYS) }}" class="form-input w-full" required>
                            @if($errorBag->has('attribution_window_days'))<p class="text-xs text-danger-600 mt-1">{{ $errorBag->first('attribution_window_days') }}</p>@endif
                        </div>
                    </div>
                    @if($canManage)
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('product_link.admin.save') }}</button>
                        </div>
                    @endif
                </fieldset>
            </form>
        </x-card>
    @endforeach
</div>

@endsection
