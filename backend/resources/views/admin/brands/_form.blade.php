{{--
    Shared Brand form partial.
    Include with: @include('admin.brands._form', ['mode' => 'create'])
                  @include('admin.brands._form', ['mode' => 'edit', 'brand' => $brand])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
--}}
@php
    $brand  = $brand ?? null;
    $isEdit = $brand !== null;

    $val = function (string $field, $default = '') use ($isEdit, $brand) {
        return old($field, $isEdit ? ($brand->{$field} ?? $default) : $default);
    };

    $bool = function (string $field, bool $default = false) use ($isEdit, $brand): bool {
        $raw = old($field, $isEdit ? ($brand->{$field} ?? $default) : $default);
        return (bool) $raw;
    };
@endphp

<div class="space-y-6">
    <input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">

    {{-- ─── Page header ──────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $isEdit ? __('admin.brands_section.edit_brand_prefix') . e($brand->name_en) : __('admin.brands_section.new_brand') }}
            </h1>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- LEFT: main fields                                               --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 space-y-4">

            {{-- Names -------------------------------------------------------- --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.brands_section.brand_names_heading') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div>
                        <label for="name_en" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.brands.brand_form.name_en') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_en"
                            name="name_en"
                            value="{{ $val('name_en') }}"
                            class="input w-full @error('name_en') border-red-400 @enderror"
                            placeholder="{{ __('admin.brands_section.name_en_placeholder') }}"
                            dir="ltr"
                            required
                        />
                        @error('name_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="name_ar" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.brands.brand_form.name_ar') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name_ar"
                            name="name_ar"
                            value="{{ $val('name_ar') }}"
                            class="input w-full @error('name_ar') border-red-400 @enderror"
                            placeholder="{{ __('admin.brands_section.name_ar_placeholder') }}"
                            dir="rtl"
                            required
                        />
                        @error('name_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="slug" class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __('admin.brands_section.slug') }} <span class="text-gray-400 font-normal">{{ __('admin.brands_section.slug_auto') }}</span>
                        </label>
                        <input
                            type="text"
                            id="slug"
                            name="slug"
                            value="{{ $val('slug') }}"
                            class="input w-full font-mono text-xs @error('slug') border-red-400 @enderror"
                            placeholder="{{ __('admin.brands_section.slug_placeholder') }}"
                            dir="ltr"
                            data-slug-source="name_en"
                        />
                        @error('slug') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="website_url" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.brands_section.website') }}</label>
                        <input
                            type="url"
                            id="website_url"
                            name="website_url"
                            value="{{ $val('website_url') }}"
                            class="input w-full @error('website_url') border-red-400 @enderror"
                            placeholder="{{ __('admin.brands_section.website_url_placeholder') }}"
                            dir="ltr"
                        />
                        @error('website_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Description -------------------------------------------------- --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.brands_section.description_heading') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <div>
                        <label for="description_en" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.brands.brand_form.description_en') }}</label>
                        <textarea
                            id="description_en"
                            name="description_en"
                            rows="4"
                            class="input w-full @error('description_en') border-red-400 @enderror"
                            placeholder="{{ __('admin.brands_section.description_en_placeholder') }}"
                            dir="ltr"
                        >{{ $val('description_en') }}</textarea>
                        @error('description_en') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description_ar" class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.brands.brand_form.description_ar') }}</label>
                        <textarea
                            id="description_ar"
                            name="description_ar"
                            rows="4"
                            dir="rtl"
                            class="input w-full @error('description_ar') border-red-400 @enderror"
                            placeholder="{{ __('admin.brands_section.description_ar_placeholder') }}"
                        >{{ $val('description_ar') }}</textarea>
                        @error('description_ar') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT: sidebar                                                  --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="w-full lg:w-72 flex-shrink-0 space-y-4">

            {{-- Brand Logo --}}
            <div
                class="bg-white rounded-xl border border-gray-200 shadow-sm"
                x-data="{
                    uploading: false,
                    logoUrl: '{{ $isEdit ? ($brand->logo_url ?? '') : '' }}',
                    uploadUrl: '{{ $isEdit ? route('admin.brands.upload-logo', $brand->id) : '' }}',
                    deleteUrl: '{{ $isEdit ? route('admin.brands.delete-logo', $brand->id) : '' }}',
                    async upload(event) {
                        const file = event.target.files[0];
                        if (!file) return;
                        this.uploading = true;
                        const fd = new FormData();
                        fd.append('logo', file);
                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                        try {
                            const res  = await fetch(this.uploadUrl, { method: 'POST', body: fd });
                            const data = await res.json();
                            if (res.ok) { this.logoUrl = data.logo_url; }
                            else { alert(data.message || 'Upload failed'); }
                        } catch(e) { alert('Network error'); }
                        this.uploading = false;
                        event.target.value = '';
                    },
                    async remove() {
                        if (!confirm('Remove logo?')) return;
                        const res = await fetch(this.deleteUrl, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            }
                        });
                        if (res.ok) { this.logoUrl = ''; }
                        else { alert('Delete failed'); }
                    }
                }"
            >
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.brands_section.logo_heading') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-3">

                    {{-- Preview area --}}
                    <div
                        class="relative rounded-lg overflow-hidden bg-gray-50 border border-dashed border-gray-300 flex items-center justify-center"
                        style="min-height: 100px"
                    >
                        <template x-if="logoUrl">
                            <img :src="logoUrl" alt="Brand logo" class="max-w-full max-h-32 object-contain p-2" />
                        </template>
                        <template x-if="!logoUrl">
                            <span class="text-xs text-gray-400">{{ __('admin.brands_section.no_logo_yet') }}</span>
                        </template>

                        {{-- Remove button --}}
                        <template x-if="logoUrl && uploadUrl">
                            <button
                                type="button"
                                @click="remove()"
                                class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-700"
                                title="{{ __('admin.banners.remove') }}"
                            >✕</button>
                        </template>
                    </div>

                    @if($isEdit)
                        <input
                            type="file"
                            accept="image/*"
                            @change="upload($event)"
                            :disabled="uploading"
                            class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer disabled:opacity-50"
                        />
                        <p x-show="uploading" class="text-xs text-primary-600 animate-pulse">{{ __('admin.brands_section.uploading') }}…</p>
                        <p class="text-xs text-gray-400">{{ __('admin.brands_section.logo_hint') }}</p>
                    @else
                        <p class="text-xs text-gray-400">{{ __('admin.brands_section.save_first_for_logo') }}</p>
                    @endif

                </div>
            </div>

            {{-- Save card ---------------------------------------------------- --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.brands_section.save_heading') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-3">
                    <button type="submit" class="btn btn-primary w-full" id="brand-save-btn">
                        <x-heroicon name="check" class="w-4 h-4 mr-1.5" />
                        {{ $isEdit ? __('admin.brands_section.save_changes') : __('admin.brands_section.create_brand') }}
                    </button>
                    <a href="{{ route('admin.brands.index') }}" class="btn btn-ghost w-full">
                        {{ __('common.cancel') }}
                    </a>
                    @if($isEdit)
                    <div class="pt-1 border-t border-gray-100">
                        <p class="text-xs text-gray-400">
                            {{ __('admin.brands_section.created_ago', ['time' => $brand->created_at->diffForHumans()]) }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Toggles card ------------------------------------------------- --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.brands_section.settings_heading') }}</h2>
                </div>
                <div class="px-5 py-5 divide-y divide-gray-100">

                    {{-- is_active --}}
                    <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ __('common.active') }}</p>
                            <p class="text-xs text-gray-500">{{ __('admin.brands_section.active_hint') }}</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer" dir="ltr">
                            <input type="hidden" name="is_active" value="0">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                class="sr-only peer"
                                {{ $bool('is_active', true) ? 'checked' : '' }}
                            >
                            <div class="relative w-10 h-5 bg-gray-200 peer-checked:bg-primary-600 rounded-full transition-colors duration-200"></div>
                            <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
                        </label>
                    </div>

                    {{-- is_verified --}}
                    <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ __('admin.brands_section.verified') }}</p>
                            <p class="text-xs text-gray-500">{{ __('admin.brands_section.verified_hint') }}</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer" dir="ltr">
                            <input type="hidden" name="is_verified" value="0">
                            <input
                                type="checkbox"
                                name="is_verified"
                                value="1"
                                class="sr-only peer"
                                {{ $bool('is_verified') ? 'checked' : '' }}
                            >
                            <div class="relative w-10 h-5 bg-gray-200 peer-checked:bg-success-600 rounded-full transition-colors duration-200"></div>
                            <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
                        </label>
                    </div>

                    {{-- is_restricted --}}
                    <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ __('admin.brands_section.restricted') }}</p>
                            <p class="text-xs text-gray-500">{{ __('admin.brands_section.restricted_hint') }}</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer" dir="ltr">
                            <input type="hidden" name="is_restricted" value="0">
                            <input
                                type="checkbox"
                                name="is_restricted"
                                value="1"
                                class="sr-only peer"
                                {{ $bool('is_restricted') ? 'checked' : '' }}
                            >
                            <div class="relative w-10 h-5 bg-gray-200 peer-checked:bg-warning-500 rounded-full transition-colors duration-200"></div>
                            <span class="absolute top-0.5 left-[2px] bg-white rounded-full h-4 w-4 transition-transform peer-checked:translate-x-5 pointer-events-none"></span>
                        </label>
                    </div>

                </div>
            </div>

        </div>

    </div>{{-- /flex --}}

    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            savingEllipsis: @json(__('admin.brands.saving_ellipsis')),
            brandSavedSuccess: @json(__('admin.brands.saved_success')),
            saveFailedGeneric: @json(__('admin.brands.save_failed_generic')),
            networkErrorRetry: @json(__('admin.brands.network_error_retry')),
            saveChangesBtn: @json(__('admin.brands_section.save_changes')),
        });
    </script>

</div>{{-- /space-y-6 --}}
