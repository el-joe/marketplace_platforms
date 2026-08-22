@extends('layouts.admin')

@section('title', __('admin.gift_cards_section.edit_batch'))

@section('content')
    <form method="POST" action="{{ route('admin.gift-cards.batches.update', $batch->id) }}" novalidate
          x-data="{
              isPurchasable: {{ old('is_purchasable', $batch->is_purchasable) ? 'true' : 'false' }},
              imageUrl: '{{ old('image_url', $batch->image_url) }}',
          }">
        @csrf
        @method('PUT')

        <div class="max-w-3xl space-y-6">

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
                    <ul class="list-disc ps-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.gift_cards_section.batch_details') }}</h2>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.name') }}</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $batch->name) }}" class="form-input" maxlength="255" required>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.description') }}</label>
                    <textarea id="description" name="description" rows="3" class="form-textarea">{{ old('description', $batch->description) }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.currency_code') }}</label>
                        <input type="text" value="{{ $batch->currency_code }}" class="form-input" disabled>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.amount') }}</label>
                        <input type="text" value="{{ (int) $batch->amount }}" class="form-input" disabled>
                    </div>
                </div>

                <div>
                    <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.expires_at') }}</label>
                    <input type="text" id="expires_at" name="expires_at"
                           value="{{ old('expires_at', $batch->expires_at?->format('Y-m-d H:i')) }}"
                           data-flatpickr data-enable-time="true" class="form-input" placeholder="YYYY-MM-DD HH:MM">
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.gift_cards_section.storefront_settings') }}</h2>

                <div class="flex items-center gap-3">
                    <label for="is_purchasable" class="text-sm font-medium text-gray-700">{{ __('admin.gift_cards_section.is_purchasable') }}</label>
                    <input type="hidden" name="is_purchasable" value="0">
                    <input type="checkbox" id="is_purchasable" name="is_purchasable" value="1" class="form-checkbox"
                           x-model="isPurchasable" {{ old('is_purchasable', $batch->is_purchasable) ? 'checked' : '' }}>
                </div>

                <div x-show="isPurchasable" x-cloak class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="title_ar" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.title_ar') }}</label>
                            <input type="text" id="title_ar" name="title_ar" dir="rtl" value="{{ old('title_ar', $batch->title_ar) }}" class="form-input" maxlength="255">
                        </div>
                        <div>
                            <label for="title_en" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.title_en') }}</label>
                            <input type="text" id="title_en" name="title_en" value="{{ old('title_en', $batch->title_en) }}" class="form-input" maxlength="255">
                        </div>
                    </div>

                    <div>
                        <label for="image_url" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.image_url') }}</label>
                        <input type="url" id="image_url" name="image_url" value="{{ old('image_url', $batch->image_url) }}" class="form-input" maxlength="500" x-model="imageUrl">
                        <img :src="imageUrl" x-show="imageUrl" x-cloak class="mt-2 h-24 rounded-lg border border-gray-200 object-cover" alt="">
                    </div>

                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.sort_order') }}</label>
                        <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $batch->sort_order ?? 0) }}" min="0" max="255" class="form-input">
                        <p class="text-xs text-gray-500 mt-1">{{ __('admin.gift_cards_section.sort_order_hint') }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="min_quantity" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.min_quantity') }}</label>
                            <input type="number" id="min_quantity" name="min_quantity" value="{{ old('min_quantity', $batch->min_quantity ?? 1) }}" min="1" max="100" class="form-input">
                        </div>
                        <div>
                            <label for="max_quantity" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.gift_cards_section.max_quantity') }}</label>
                            <input type="number" id="max_quantity" name="max_quantity" value="{{ old('max_quantity', $batch->max_quantity ?? 10) }}" min="1" max="100" class="form-input">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.gift-cards.batches.show', $batch->id) }}" class="btn btn-secondary">{{ __('admin.cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('admin.save') }}</button>
            </div>

        </div>
    </form>
@endsection

@push('styles')
    @vite(['resources/js/components/flatpickr.js'])
@endpush
