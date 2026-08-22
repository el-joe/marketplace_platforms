@extends('layouts.admin')

@push('head')
    @vite('resources/js/admin/currencies.js')
@endpush

@section('title', __('admin.currencies_section.edit_currency_title', ['code' => $currency->code]))

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div></div>
            <div class="flex gap-2">
                <a href="{{ route('admin.currencies.index') }}" class="btn btn-ghost">
                    {{ __('admin.currencies_section.back_to_currencies') }}
                </a>
                <button type="submit" form="currency-form" class="btn btn-primary">
                    <x-heroicon name="check" class="w-4 h-4" />
                    {{ __('common.save') }}
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-700 space-y-1">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($currency->is_manually_overridden)
            <div class="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-800">
                {{ __('admin.currencies_section.manual_override_notice') }}
            </div>
        @endif

        <form id="currency-form" method="POST" action="{{ route('admin.currencies.update', $currency->code) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">

                <div class="flex items-center gap-3 mb-2">
                    <span class="text-3xl font-mono font-bold text-gray-900">{{ $currency->code }}</span>
                    <span class="text-lg text-gray-500">{{ $currency->name }}</span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-form.input name="name" label="{{ __('admin.currencies_section.currency_name_label') }}" :value="old('name', $currency->name)" required />
                    <x-form.input name="symbol" label="{{ __('admin.currencies_section.symbol') }}" :value="old('symbol', $currency->symbol)" required
                        maxlength="10" class="w-24" />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <x-form.input name="exchange_rate_to_base" label="{{ __('admin.currencies_section.exchange_rate_to_base') }}" type="number" step="0.000001"
                        min="0.000001" :value="old('exchange_rate_to_base', $currency->exchange_rate_to_base)" required
                        hint="{{ __('admin.currencies_section.exchange_rate_hint', ['code' => $currency->code, 'base' => $currency->base_currency_code]) }}" />
                    <x-form.input name="base_currency_code" label="{{ __('admin.currencies_section.base_currency') }}" :value="old('base_currency_code', $currency->base_currency_code)" maxlength="3" class="uppercase" required />
                    <x-form.input name="decimal_places" label="{{ __('admin.currencies_section.decimal_places') }}" type="number" min="0" max="4"
                        :value="old('decimal_places', $currency->decimal_places)" required />
                </div>

                <div class="flex items-center gap-6 pt-2">
                    <x-form.toggle name="is_active" label="{{ __('common.active') }}" :checked="(bool) old('is_active', $currency->is_active)" />
                    <x-form.toggle name="is_manually_overridden" label="{{ __('admin.currencies_section.manually_override_rate') }}"
                        :checked="(bool) old('is_manually_overridden', $currency->is_manually_overridden)" />
                </div>

                @if ($currency->rate_updated_at)
                    <p class="text-xs text-gray-400 pt-1">
                        {{ __('admin.currencies_section.rate_last_updated', ['date' => $currency->rate_updated_at->format('d M Y, H:i'), 'diff' => $currency->rate_updated_at->diffForHumans()]) }}
                    </p>
                @endif
            </div>
        </form>
    </div>
@endsection
