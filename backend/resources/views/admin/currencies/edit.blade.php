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
                        maxlength="10" class="w-24"
                        hint="{{ __('admin.currencies_section.symbol_text_hint') }}" />
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-medium text-gray-700">{{ __('admin.currencies_section.symbol_type') }}</label>
                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="symbol_type" value="text"
                                {{ old('symbol_type', $currency->symbol_type?->value ?? 'text') === 'text' ? 'checked' : '' }}>
                            {{ __('admin.currencies_section.symbol_type_text') }}
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="radio" name="symbol_type" value="image"
                                {{ old('symbol_type', $currency->symbol_type?->value ?? 'text') === 'image' ? 'checked' : '' }}>
                            {{ __('admin.currencies_section.symbol_type_image') }}
                        </label>
                    </div>
                </div>

                <div
                    class="rounded-lg border border-gray-200 p-4 space-y-3 max-w-xs"
                    x-data="{
                        uploading: false,
                        imageUrl: '{{ $currency->symbol_image_url ?? '' }}',
                        uploadUrl: '{{ route('admin.currencies.symbol-image.upload', $currency->code) }}',
                        deleteUrl: '{{ route('admin.currencies.symbol-image.delete', $currency->code) }}',
                        async upload(event) {
                            const file = event.target.files[0];
                            if (!file) return;
                            this.uploading = true;
                            const fd = new FormData();
                            fd.append('symbol_image', file);
                            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                            try {
                                const res = await fetch(this.uploadUrl, { method: 'POST', body: fd });
                                const data = await res.json();
                                if (res.ok) { this.imageUrl = data.symbol_image_url; }
                                else { alert(data.message || 'Upload failed'); }
                            } catch(e) { alert('Network error'); }
                            this.uploading = false;
                            event.target.value = '';
                        },
                        async remove() {
                            if (!confirm('Remove symbol image?')) return;
                            const res = await fetch(this.deleteUrl, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                }
                            });
                            if (res.ok) { this.imageUrl = ''; }
                            else { alert('Delete failed'); }
                        }
                    }"
                >
                    <label class="block text-xs font-medium text-gray-700">{{ __('admin.currencies_section.symbol_image') }}</label>

                    <div class="relative rounded-lg overflow-hidden bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center" style="min-height:80px">
                        <template x-if="imageUrl">
                            <img :src="imageUrl" alt="Currency symbol" class="max-h-16 object-contain" />
                        </template>
                        <template x-if="!imageUrl">
                            <span class="text-xs text-gray-400">{{ __('admin.currencies_section.no_symbol_image_yet') }}</span>
                        </template>

                        <template x-if="imageUrl">
                            <button type="button" @click="remove()"
                                class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-700"
                            >✕</button>
                        </template>
                    </div>

                    <input type="file" accept="image/*" @change="upload($event)" :disabled="uploading"
                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer disabled:opacity-50" />
                    <p x-show="uploading" class="text-xs text-primary-600 animate-pulse">{{ __('admin.currencies_section.uploading') }}…</p>
                    <p class="text-xs text-gray-400">{{ __('admin.currencies_section.symbol_image_hint') }}</p>
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
