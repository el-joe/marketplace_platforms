@php $__badgeIcons = json_decode('{"bolt": "<path d=\"M13 2 4 14h7l-1 8 9-12h-7z\"/>", "truck": "<path d=\"M1 6h13v10H1zM14 9h4l3 3v4h-7zM6 19a2 2 0 1 0 0-.01M17 19a2 2 0 1 0 0-.01\"/>", "clock": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 7v5l3 2\"/>", "rocket": "<path d=\"M5 15c-1 1-2 4-2 6 2 0 5-1 6-2M14 4c3-2 6-2 7-2 0 1 0 4-2 7l-6 6-5-5zM9 14l-3-1 2-3 3-1M10 18l1 3 3-2 1-3\"/>", "box": "<path d=\"M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8\"/>", "plane": "<path d=\"M2 12l20-9-6 18-4-8z\"/>", "star": "<path d=\"m12 2 3 7 7 .6-5.3 4.7 1.6 7.2L12 17.8 5.7 21.5l1.6-7.2L2 9.6 9 9z\"/>", "gift": "<path d=\"M3 8h18v4H3zM5 12v9h14v-9M12 8v13M12 8S8 8 8 5a2 2 0 0 1 4 0M12 8s4 0 4-3a2 2 0 0 0-4 0\"/>", "shield-check": "<path d=\"M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z\"/><path d=\"m9 12 2 2 4-4\"/>", "tag": "<path d=\"M3 3h8l10 10-8 8L3 11z\"/><circle cx=\"7.5\" cy=\"7.5\" r=\"1\"/>"}', true); @endphp
{{--
    Shared Shipping Method form partial.
    Include with: @include('admin.shipping-methods._form', ['mode' => 'create'])
                  @include('admin.shipping-methods._form', ['mode' => 'edit', 'shippingMethod' => $shippingMethod])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
--}}
@php
    $isEdit = $mode === 'edit';

    $val = function (string $field, $default = '') use ($shippingMethod) {
        return old($field, $shippingMethod->{$field} ?? $default);
    };

    $bool = function (string $field, bool $default = false) use ($shippingMethod) {
        return (bool) old($field, $shippingMethod->{$field} ?? $default);
    };
@endphp

<div class="space-y-6"
     x-data="{
         badgeLabel: {{ json_encode($val('badge_label_en')) }},
         badgeColor: {{ json_encode($val('badge_color_hex', '#1a1a2e')) }},
         badgeTextColor: {{ json_encode($val('badge_text_color_hex', '#FFFFFF')) }},
         badgeIcon: {{ json_encode($val('badge_icon', 'bolt') ?: 'none') }},
         iconPaths: {{ json_encode($__badgeIcons) }},
         showDelivery: {{ $bool('badge_show_delivery_time') ? 'true' : 'false' }},
         deliveryText: {{ json_encode($val('badge_delivery_text_en') ?: $val('badge_delivery_text_ar')) }},
     }">

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">
            {{ $isEdit ? __('admin.shipping_section.edit_shipping_method') : __('admin.shipping_section.add_shipping_method') }}
        </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <div class="lg:col-span-2 space-y-4">
            {{-- ─── Basics ──────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shipping_section.name_label') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.name_label') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name" value="{{ $val('name') }}" maxlength="100"
                                   class="input w-full @error('name') border-red-400 @enderror" required>
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="code" class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __('admin.shipping_section.code_label') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="code" name="code" value="{{ $val('code') }}" maxlength="50"
                                   pattern="^[a-z_]+$"
                                   class="input w-full font-mono @error('code') border-red-400 @enderror"
                                   {{ $isEdit ? 'readonly' : '' }} required>
                            @if($isEdit)
                                <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.code_immutable_note') }}</p>
                            @else
                                <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.code_help') }}</p>
                            @endif
                            @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.shipping_section.description_label') }}
                        </label>
                        <textarea id="description" name="description" rows="3"
                                  class="input w-full @error('description') border-red-400 @enderror">{{ $val('description') }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="min_delivery_days" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.min_days') }}</label>
                            <input type="number" id="min_delivery_days" name="min_delivery_days" value="{{ $val('min_delivery_days') }}" min="0"
                                   class="input w-full @error('min_delivery_days') border-red-400 @enderror">
                            @error('min_delivery_days') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="max_delivery_days" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.max_days') }}</label>
                            <input type="number" id="max_delivery_days" name="max_delivery_days" value="{{ $val('max_delivery_days') }}" min="0"
                                   class="input w-full @error('max_delivery_days') border-red-400 @enderror">
                            @error('max_delivery_days') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="handling_time_hours" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.handling_time_hours') }}</label>
                            <input type="number" id="handling_time_hours" name="handling_time_hours" value="{{ $val('handling_time_hours', 24) }}" min="0" max="72"
                                   class="input w-full @error('handling_time_hours') border-red-400 @enderror">
                            @error('handling_time_hours') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="order_cutoff_time" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.order_cutoff_time') }}</label>
                            <input type="text" id="order_cutoff_time" name="order_cutoff_time"
                                   value="{{ $val('order_cutoff_time') ? substr($val('order_cutoff_time'), 0, 5) : '' }}"
                                   data-flatpickr data-time-only="true"
                                   placeholder="HH:MM"
                                   class="input w-full @error('order_cutoff_time') border-red-400 @enderror">
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.order_cutoff_time_help') }}</p>
                            @error('order_cutoff_time') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="display_priority" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.display_priority') }}</label>
                            <input type="number" id="display_priority" name="display_priority" value="{{ $val('display_priority', 0) }}" min="0"
                                   class="input w-full @error('display_priority') border-red-400 @enderror">
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.shipping_section.display_priority_help') }}</p>
                            @error('display_priority') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-8 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="relative inline-flex items-center" dir="ltr">
                                <input type="hidden" name="is_express_type" value="0">
                                <input type="checkbox" name="is_express_type" value="1" class="sr-only peer" {{ $bool('is_express_type') ? 'checked' : '' }}>
                                <span class="relative w-10 h-5 bg-gray-200 peer-checked:bg-primary-600 rounded-full transition-colors duration-200 block"></span>
                                <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
                            </span>
                            <span class="text-sm text-gray-700">{{ __('admin.shipping_section.express_type') }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="relative inline-flex items-center" dir="ltr">
                                <input type="hidden" name="show_estimated_price" value="0">
                                <input type="checkbox" name="show_estimated_price" value="1" class="sr-only peer" {{ $bool('show_estimated_price', true) ? 'checked' : '' }}>
                                <span class="relative w-10 h-5 bg-gray-200 peer-checked:bg-primary-600 rounded-full transition-colors duration-200 block"></span>
                                <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
                            </span>
                            <span class="text-sm text-gray-700">{{ __('admin.shipping_section.show_estimated_price') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ─── Badge & delivery labels ────────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shipping_section.badge_label') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="badge_label_en" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.badge_label_en') }}</label>
                            <input type="text" id="badge_label_en" name="badge_label_en" x-model="badgeLabel" maxlength="50"
                                   placeholder="Express" class="input w-full @error('badge_label_en') border-red-400 @enderror">
                            @error('badge_label_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="badge_label_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.badge_label_ar') }}</label>
                            <input type="text" id="badge_label_ar" name="badge_label_ar" value="{{ $val('badge_label_ar') }}" maxlength="50"
                                   dir="rtl" placeholder="سريع" class="input w-full @error('badge_label_ar') border-red-400 @enderror">
                            @error('badge_label_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="badge_color_hex" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.badge_color') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" id="badge_color_hex" name="badge_color_hex" x-model="badgeColor"
                                       class="h-9 w-16 rounded border border-gray-300 p-0.5 cursor-pointer">
                                <span class="text-xs text-gray-400 font-mono" x-text="badgeColor"></span>
                            </div>
                            @error('badge_color_hex') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="badge_text_color_hex" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.badge_text_color') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" id="badge_text_color_hex" name="badge_text_color_hex" x-model="badgeTextColor"
                                       class="h-9 w-16 rounded border border-gray-300 p-0.5 cursor-pointer">
                                <span class="text-xs text-gray-400 font-mono" x-text="badgeTextColor"></span>
                            </div>
                            @error('badge_text_color_hex') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="pt-2 space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="relative inline-flex items-center" dir="ltr">
                                <input type="hidden" name="badge_show_delivery_time" value="0">
                                <input type="checkbox" name="badge_show_delivery_time" value="1" x-model="showDelivery" class="sr-only peer">
                                <span class="relative w-10 h-5 bg-gray-200 peer-checked:bg-primary-600 rounded-full transition-colors duration-200 block"></span>
                                <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
                            </span>
                            <span class="text-sm text-gray-700">{{ __('admin.shipping_section.badge_show_delivery_time') }}</span>
                        </label>
                        <div x-show="showDelivery" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="badge_delivery_text_en" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.badge_delivery_text_en') }}</label>
                                <input type="text" id="badge_delivery_text_en" name="badge_delivery_text_en" x-model="deliveryText" maxlength="100"
                                       value="{{ $val('badge_delivery_text_en') }}" placeholder="Get it in 2 days" class="input w-full @error('badge_delivery_text_en') border-red-400 @enderror">
                                @error('badge_delivery_text_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="badge_delivery_text_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.badge_delivery_text_ar') }}</label>
                                <input type="text" id="badge_delivery_text_ar" name="badge_delivery_text_ar" value="{{ $val('badge_delivery_text_ar') }}" maxlength="100"
                                       dir="rtl" placeholder="يصلك خلال يومين" class="input w-full @error('badge_delivery_text_ar') border-red-400 @enderror">
                                @error('badge_delivery_text_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Badge icon picker --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.badge_icon') }}</label>
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                            @foreach (config('shipping_badge.badge_icons') as $key)
                                <label class="cursor-pointer">
                                    <input type="radio" name="badge_icon" value="{{ $key }}" x-model="badgeIcon" class="sr-only">
                                    <span class="flex flex-col items-center gap-1 rounded-lg border p-2 text-xs"
                                          :class="badgeIcon === '{{ $key }}' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'">
                                        @if ($key !== 'none')
                                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $__badgeIcons[$key] !!}</svg>
                                        @else
                                            <span class="w-5 h-5 inline-flex items-center justify-center">&mdash;</span>
                                        @endif
                                        {{ __('admin.shipping_section.badge_icons.' . $key) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('badge_icon') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Live badge preview — reflects exactly how the badge appears to customers --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.preview') }}</label>
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 flex items-center">
                            <span x-show="showDelivery ? deliveryText : badgeLabel"
                                  :style="`background-color: ${badgeColor}; color: ${badgeTextColor};`"
                                  class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold">
                                <svg x-show="badgeIcon !== 'none' && iconPaths[badgeIcon]" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" x-html="iconPaths[badgeIcon]"></svg>
                                <span x-text="showDelivery ? deliveryText : badgeLabel"></span>
                                <svg class="w-3 h-3 rtl:-scale-x-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                            </span>
                            <span x-show="!(showDelivery ? deliveryText : badgeLabel)" class="text-xs text-gray-400">{{ __('admin.shipping_section.no_badge_configured') }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Badge Image --}}
                    <div
                        class="pt-2"
                        x-data="{
                            uploading: false,
                            imageUrl: '{{ $shippingMethod->badge_image_url ?? '' }}',
                            uploadUrl: '{{ $shippingMethod->id ? route('admin.shipping-methods.upload-badge-image', $shippingMethod->id) : '' }}',
                            deleteUrl: '{{ $shippingMethod->id ? route('admin.shipping-methods.delete-badge-image', $shippingMethod->id) : '' }}',
                            async upload(event) {
                                const file = event.target.files[0];
                                if (!file) return;
                                this.uploading = true;
                                const fd = new FormData();
                                fd.append('image', file);
                                fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                                try {
                                    const res  = await fetch(this.uploadUrl, { method: 'POST', body: fd });
                                    const data = await res.json();
                                    if (res.ok) { this.imageUrl = data.badge_image_url; }
                                    else { alert(data.message || 'Upload failed'); }
                                } catch(e) { alert('Network error'); }
                                this.uploading = false;
                                event.target.value = '';
                            },
                            async remove() {
                                if (!confirm('Remove badge image?')) return;
                                const res = await fetch(this.deleteUrl, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                        'Accept': 'application/json',
                                    },
                                });
                                if (res.ok) { this.imageUrl = ''; }
                                else { alert('Delete failed'); }
                            }
                        }"
                    >
                        <label class="block text-xs font-medium text-gray-700 mb-2">
                            {{ __('admin.shipping_section.badge_image') }}
                            <span class="font-normal text-gray-400">{{ __('admin.shipping_section.badge_image_hint') }}</span>
                        </label>

                        <div
                            class="relative inline-flex items-center justify-center w-24 h-24 rounded-lg border border-dashed border-gray-300 bg-gray-50 overflow-hidden mb-2"
                            :style="imageUrl ? 'border-style:solid' : ''"
                        >
                            <template x-if="imageUrl">
                                <img :src="imageUrl" alt="Badge image" class="max-w-full max-h-full object-contain p-1" />
                            </template>
                            <template x-if="!imageUrl">
                                <x-heroicon name="photo" class="w-8 h-8 text-gray-300" />
                            </template>
                            <template x-if="imageUrl && uploadUrl">
                                <button
                                    type="button"
                                    @click.prevent="remove()"
                                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] hover:bg-red-600"
                                    title="{{ __('admin.banners.remove') }}"
                                >✕</button>
                            </template>
                        </div>

                        @if($shippingMethod->id)
                            <div>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="upload($event)"
                                    :disabled="uploading"
                                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer disabled:opacity-50"
                                />
                                <p x-show="uploading" class="text-xs text-primary-600 mt-1 animate-pulse">{{ __('admin.brands_section.uploading') }}…</p>
                            </div>
                        @else
                            <p class="text-xs text-gray-400">{{ __('admin.shipping_section.save_first_for_image') }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="delivery_label_en" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.delivery_panel_label_en') }}</label>
                            <input type="text" id="delivery_label_en" name="delivery_label_en" value="{{ $val('delivery_label_en') }}" maxlength="100"
                                   placeholder="Delivered within 2-4 days" class="input w-full @error('delivery_label_en') border-red-400 @enderror">
                            @error('delivery_label_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="delivery_label_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.shipping_section.delivery_panel_label_ar') }}</label>
                            <input type="text" id="delivery_label_ar" name="delivery_label_ar" value="{{ $val('delivery_label_ar') }}" maxlength="100"
                                   dir="rtl" placeholder="يتم التوصيل خلال 2-4 أيام" class="input w-full @error('delivery_label_ar') border-red-400 @enderror">
                            @error('delivery_label_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Sidebar ─────────────────────────────────────────────────── --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-5 space-y-3">
                    <button type="submit" class="btn btn-primary w-full">
                        <x-heroicon name="check" class="w-4 h-4 mr-1.5" />
                        {{ $isEdit ? __('common.save') : __('admin.shipping_section.add_shipping_method') }}
                    </button>
                    <a href="{{ route('admin.shipping-methods.index') }}" class="btn btn-ghost w-full">
                        {{ __('common.cancel') }}
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('common.active') }}</h2>
                </div>
                <div class="px-5 py-5">
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="text-sm font-medium text-gray-900">{{ __('common.active') }}</span>
                        <span class="relative inline-flex items-center" dir="ltr">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $bool('is_active', true) ? 'checked' : '' }}>
                            <span class="relative w-10 h-5 bg-gray-200 peer-checked:bg-primary-600 rounded-full transition-colors duration-200 block"></span>
                            <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

    </div>
</div>
