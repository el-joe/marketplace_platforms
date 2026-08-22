@extends('layouts.admin')

@php
    $highlight = $highlight ?? null;
    $isEdit = $highlight !== null;
@endphp

@section('title', $isEdit ? __('admin.product_highlights.title') : __('admin.product_highlights.new_highlight'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
    @php
        $val = fn(string $field, $default = '') => old($field, $isEdit ? ($highlight->{$field} ?? $default) : $default);
    @endphp

    <form method="POST"
        action="{{ $isEdit ? route('admin.product-highlights.update', $highlight->id) : route('admin.product-highlights.store') }}"
        novalidate>
        @csrf
        @if($isEdit)
            @method('PATCH')
        @endif

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">
                    {{ $isEdit ? __('admin.product_highlights.title') : __('admin.product_highlights.new_highlight') }}
                </h1>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-5 space-y-4 max-w-xl">

                    <div>
                        <label for="product_id" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.product_highlights.product') }} <span class="text-red-500">*</span>
                        </label>
                        <select name="product_id" id="product_id" required data-async-select
                            data-config='{{ json_encode(['url' => route('admin.product-highlights.search.products'), 'param' => 'q', 'minLength' => 2, 'delay' => 300]) }}'
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('product_id') border-red-400 @enderror">
                            @if($isEdit && $highlight->product)
                                <option value="{{ $highlight->product->id }}" selected>{{ $highlight->product->name_en }}</option>
                            @endif
                        </select>
                        @error('product_id')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="text_en" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('common.name') }} (EN) <span class="text-red-500">*</span>
                        </label>
                        <textarea name="text_en" id="text_en" rows="2" dir="ltr" required
                            class="input w-full @error('text_en') border-red-400 @enderror">{{ $val('text_en') }}</textarea>
                        @error('text_en')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="text_ar" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('common.name') }} (AR) <span class="text-red-500">*</span>
                        </label>
                        <textarea name="text_ar" id="text_ar" rows="2" dir="rtl" required
                            class="input w-full @error('text_ar') border-red-400 @enderror">{{ $val('text_ar') }}</textarea>
                        @error('text_ar')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="position" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.product_highlights.sort_order') }}
                        </label>
                        <input type="number" name="position" id="position" min="0" value="{{ $val('position', 0) }}"
                            class="input w-full @error('position') border-red-400 @enderror">
                        @error('position')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.product-highlights.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
@endsection
