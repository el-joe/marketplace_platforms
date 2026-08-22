{{--
    Shared Banner form partial.
    @include('admin.banners._form', ['mode' => 'create'])
    @include('admin.banners._form', ['mode' => 'edit', 'banner' => $banner])

    Parent view must wrap in <form> with proper action / @csrf / @method.
    Requires: $countries, $placements (BannerPlacementDefinition collection)
    For edit: $banner, $desktopImage (File|null), $mobileImage (File|null)
--}}
@php
    $banner      = $banner ?? null;
    $isEdit      = $banner !== null;
    $desktopImage = $desktopImage ?? null;
    $mobileImage  = $mobileImage ?? null;

    $val = function (string $field, $default = '') use ($isEdit, $banner) {
        $current = $isEdit ? ($banner->{$field} ?? $default) : $default;
        if ($current instanceof \BackedEnum) {
            $current = $current->value;
        }
        return old($field, $current);
    };

    $placementsJson = $placements->keyBy('code')->map(fn ($p) => [
        'name'          => $p->name,
        'width_px'      => $p->width_px,
        'height_px'     => $p->height_px,
        'max_file_size_kb' => $p->max_file_size_kb,
        'allowed_formats'  => $p->allowed_formats,
        'device_restriction' => $p->device_restriction,
        'base_rate_weekly' => $p->base_rate_weekly,
    ])->toJson();

    $currentStatus       = old('status',       $isEdit ? $banner->status?->value       : 'active');
    $currentDeviceTarget = old('device_target', $isEdit ? $banner->device_target?->value : 'all');
    $currentLinkType     = old('link_type',     $isEdit ? $banner->link_type?->value    : '');
    $currentPlacement    = old('placement_code',$isEdit ? $banner->placement_code : '');
@endphp

<div
    id="banner-form-root"
    x-data="{
        deviceTarget: '{{ $currentDeviceTarget }}',
        linkType:     '{{ $currentLinkType }}',
        placement:    '{{ $currentPlacement }}',
        placements:   {{ $placementsJson }},
        get currentPlacement() {
            return this.placements[this.placement] || null;
        },
        get dimensionHint() {
            const p = this.currentPlacement;
            if (!p) return '';
            const rate = (p.base_rate_weekly).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
            return `${p.width_px} × ${p.height_px}px · max ${p.max_file_size_kb}KB · ${rate}/week`;
        },
        get desktopAspect() {
            const p = this.currentPlacement;
            if (!p || !p.width_px || !p.height_px) return '16/9';
            return p.width_px + '/' + p.height_px;
        },
        get showMobileUpload() {
            return this.deviceTarget === 'all' || this.deviceTarget === 'mobile' || this.deviceTarget === 'app';
        },
        get showLinkRef() {
            return this.linkType && this.linkType !== 'url' && this.linkType !== 'none' && this.linkType !== '';
        },
    }"
    class="space-y-6">

    <input type="hidden" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">

    {{-- ─── Page header ─────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $isEdit ? __('admin.banners.edit_banner_name_title', ['name' => $banner->name]) : __('admin.banners.new_banner_title') }}
            </h1>
            @if($isEdit)
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('admin.banners.last_updated', ['time' => $banner->updated_at ? $banner->updated_at->diffForHumans() : __('admin.banners.never')]) }}
                </p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.banners.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" id="save-btn" class="btn btn-primary">
                {{ $isEdit ? __('admin.banners.save_changes') : __('admin.banners.create_banner') }}
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ═══════════════════════════ LEFT COLUMN ═══════════════════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Banner Content card ─────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.banners.banner_content') }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    {{-- Name --}}
                    <div class="sm:col-span-2">
                        <x-form-input
                            name="name"
                            label="{{ __('admin.banners.internal_name') }} *"
                            :value="$val('name')"
                            placeholder="{{ __('admin.banners.internal_name_placeholder') }}"
                            required />
                    </div>

                    {{-- Placement --}}
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.banners.placement') }} <span class="text-red-500">*</span>
                        </label>
                        <select name="placement_code" x-model="placement" class="form-input w-full" required>
                            <option value="">{{ __('admin.banners.select_placement') }}</option>
                            @foreach($placements as $p)
                                <option value="{{ $p->code }}" @selected($val('placement_code') === $p->code)>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1" x-text="dimensionHint"></p>
                    </div>

                </div>
            </x-card>

            {{-- Image Upload card ───────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.banners.creative_images') }}">

                {{-- Desktop image --}}
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('admin.banners.desktop_image') }}
                        <span class="text-xs text-gray-400 font-normal"
                            x-text="currentPlacement ? '(' + currentPlacement.width_px + '×' + currentPlacement.height_px + 'px)' : ''">
                        </span>
                    </label>

                    <div
                        id="desktop-preview-wrap"
                        class="relative rounded-lg overflow-hidden bg-gray-100 border border-dashed border-gray-300 mb-3 flex items-center justify-center"
                        style="min-height:120px"
                        :style="currentPlacement ? `aspect-ratio: ${desktopAspect}; max-height:240px;` : ''">
                        @if($isEdit && $desktopImage)
                            <img
                                id="desktop-preview-img"
                                src="{{ $desktopImage->full_path }}"
                                alt="{{ __('admin.banners.desktop_alt') }}"
                                class="max-w-full max-h-full object-contain" />
                            <button
                                type="button"
                                class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs js-remove-img-btn"
                                data-slot="desktop"
                                data-file-id="{{ $desktopImage->id }}"
                                data-delete-url="{{ route('admin.banners.delete-image') }}"
                                title="{{ __('admin.banners.remove') }}">✕</button>
                        @else
                            <img id="desktop-preview-img" src="" alt="" class="max-w-full max-h-full object-contain hidden" />
                            <span id="desktop-upload-placeholder" class="text-sm text-gray-400">{{ __('admin.banners.no_image_yet') }}</span>
                        @endif
                    </div>

                    <input
                        type="file"
                        name="desktop_image"
                        id="desktop-image-input"
                        accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer" />
                </div>

                {{-- Mobile image --}}
                <div x-show="showMobileUpload" x-transition>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('admin.banners.mobile_image') }}
                        <span class="text-xs text-gray-400 font-normal">{{ __('admin.banners.mobile_image_optional') }}</span>
                    </label>

                    <div
                        id="mobile-preview-wrap"
                        class="relative rounded-lg overflow-hidden bg-gray-100 border border-dashed border-gray-300 mb-3 flex items-center justify-center"
                        style="min-height:120px; max-width:200px">
                        @if($isEdit && $mobileImage)
                            <img
                                id="mobile-preview-img"
                                src="{{ $mobileImage->full_path }}"
                                alt="{{ __('admin.banners.mobile_alt') }}"
                                class="max-w-full max-h-full object-contain" />
                            <button
                                type="button"
                                class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs js-remove-img-btn"
                                data-slot="mobile"
                                data-file-id="{{ $mobileImage->id }}"
                                data-delete-url="{{ route('admin.banners.delete-image') }}"
                                title="{{ __('admin.banners.remove') }}">✕</button>
                        @else
                            <img id="mobile-preview-img" src="" alt="" class="max-w-full max-h-full object-contain hidden" />
                            <span id="mobile-upload-placeholder" class="text-sm text-gray-400">{{ __('admin.banners.no_image_yet') }}</span>
                        @endif
                    </div>

                    <input
                        type="file"
                        name="mobile_image"
                        id="mobile-image-input"
                        accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer" />
                </div>

            </x-card>

            {{-- Text Content card ───────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.banners.text_content') }}" subtitle="{{ __('admin.banners.all_fields_optional') }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-form-input name="title_en"    label="{{ __('admin.banners.title_en') }}"    :value="$val('title_en')" dir="ltr" />
                    <x-form-input name="title_ar"    label="{{ __('admin.banners.title_ar') }}"     :value="$val('title_ar')" dir="rtl" />
                    <x-form-input name="subtitle_en" label="{{ __('admin.banners.subtitle_en') }}" :value="$val('subtitle_en')" dir="ltr" />
                    <x-form-input name="subtitle_ar" label="{{ __('admin.banners.subtitle_ar') }}"  :value="$val('subtitle_ar')" dir="rtl" />
                    <x-form-input name="cta_label_en" label="{{ __('admin.banners.cta_label_en') }}" :value="$val('cta_label_en')" placeholder="{{ __('admin.banners.cta_label_en_placeholder') }}" dir="ltr" />
                    <x-form-input name="cta_label_ar" label="{{ __('admin.banners.cta_label_ar') }}"  :value="$val('cta_label_ar')" placeholder="{{ __('admin.banners.cta_label_ar_placeholder') }}" dir="rtl" />
                </div>
            </x-card>

            {{-- Link card ───────────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.banners.link_and_target') }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.banners.link_type') }}</label>
                        <select name="link_type" x-model="linkType" class="form-input w-full">
                            <option value="">{{ __('admin.banners.none_option') }}</option>
                            <option value="url" @selected($val('link_type') === 'url')>{{ __('admin.banners.external_url') }}</option>
                            <option value="product" @selected($val('link_type') === 'product')>{{ __('admin.banners.product') }}</option>
                            <option value="category" @selected($val('link_type') === 'category')>{{ __('admin.banners.category') }}</option>
                            <option value="brand" @selected($val('link_type') === 'brand')>{{ __('admin.banners.brand') }}</option>
                            <option value="flash_sale" @selected($val('link_type') === 'flash_sale')>{{ __('admin.banners.flash_sale') }}</option>
                            <option value="page" @selected($val('link_type') === 'page')>{{ __('admin.banners.page') }}</option>
                            <option value="none" @selected($val('link_type') === 'none')>{{ __('admin.banners.no_link') }}</option>
                        </select>
                    </div>

                    <div>
                        <x-form-input
                            name="cta_url"
                            label="{{ __('admin.banners.url') }}"
                            :value="$val('cta_url')"
                            placeholder="{{ __('admin.banners.url_placeholder') }}" />
                    </div>

                    <div x-show="showLinkRef" x-transition class="sm:col-span-2">
                        <x-form-input
                            name="link_reference_id"
                            label="{{ __('admin.banners.reference_id') }}"
                            :value="$val('link_reference_id')"
                            placeholder="{{ __('admin.banners.reference_id_placeholder') }}" />
                    </div>

                </div>
            </x-card>

        </div>
        {{-- ═══════════════════════════ RIGHT SIDEBAR ═══════════════════════════ --}}
        <div class="space-y-6">

            {{-- Settings card ───────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.banners.settings') }}">
                <div class="space-y-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.banners.country') }}</label>
                        <select name="country_id" class="form-input w-full">
                            <option value="">{{ __('admin.banners.all_countries_option') }}</option>
                            @foreach($countries as $c)
                                <option value="{{ $c->id }}" @selected($val('country_id') === $c->id)>
                                    {{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.banners.device_target') }}</label>
                        <select name="device_target" x-model="deviceTarget" class="form-input w-full">
                            <option value="all"     @selected($val('device_target', 'all') === 'all')>{{ __('admin.banners.all_devices_option') }}</option>
                            <option value="desktop" @selected($val('device_target', 'all') === 'desktop')>{{ __('admin.banners.desktop_only') }}</option>
                            <option value="mobile"  @selected($val('device_target', 'all') === 'mobile')>{{ __('admin.banners.mobile_only') }}</option>
                            <option value="app"     @selected($val('device_target', 'all') === 'app')>{{ __('admin.banners.app_only') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.banners.audience') }}</label>
                        <select name="audience" class="form-input w-full">
                            <option value="all"       @selected($val('audience', 'all') === 'all')>{{ __('admin.banners.everyone') }}</option>
                            <option value="guest"     @selected($val('audience', 'all') === 'guest')>{{ __('admin.banners.guests_only') }}</option>
                            <option value="logged_in" @selected($val('audience', 'all') === 'logged_in')>{{ __('admin.banners.logged_in') }}</option>
                            <option value="vip"       @selected($val('audience', 'all') === 'vip')>{{ __('admin.banners.vip_members') }}</option>
                            <option value="new_user"  @selected($val('audience', 'all') === 'new_user')>{{ __('admin.banners.new_users') }}</option>
                        </select>
                    </div>

                    <div>
                        <x-form-input
                            name="priority"
                            label="{{ __('admin.banners.priority') }}"
                            type="number"
                            :value="$val('priority', 0)"
                            min="0"
                            placeholder="{{ __('admin.banners.priority_placeholder') }}" />
                        <p class="text-xs text-gray-400 mt-1">{{ __('admin.banners.priority_help') }}</p>
                    </div>

                </div>
            </x-card>

            {{-- Schedule card ───────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.banners.schedule') }}">
                <div class="space-y-4">

                    <div>
                        <x-form-input
                            name="starts_at"
                            label="{{ __('admin.banners.starts_at') }} *"
                            type="datetime-local"
                            :value="$val('starts_at', $isEdit ? optional($banner->starts_at)->format('Y-m-d\TH:i') : '')"
                            required />
                    </div>

                    <div>
                        <x-form-input
                            name="ends_at"
                            label="{{ __('admin.banners.ends_at') }} *"
                            type="datetime-local"
                            :value="$val('ends_at', $isEdit ? optional($banner->ends_at)->format('Y-m-d\TH:i') : '')"
                            required />
                        <p class="text-xs text-red-500 mt-1 hidden" id="date-error">{{ __('admin.banners.end_after_start_error') }}</p>
                    </div>

                    <div class="rounded bg-gray-50 px-3 py-2 text-xs text-gray-600" id="duration-preview">
                        —
                    </div>

                </div>
            </x-card>

            {{-- Status card ─────────────────────────────────────────────────── --}}
            <x-card title="{{ __('admin.banners.status') }}">
                <div class="space-y-2">
                    @foreach(['active' => __('admin.banners.active'), 'inactive' => __('admin.banners.inactive'), 'scheduled' => __('admin.banners.scheduled')] as $statusVal => $statusLabel)
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input
                                type="radio"
                                name="status"
                                value="{{ $statusVal }}"
                                class="mt-0.5"
                                @checked($currentStatus === $statusVal)>
                            <span class="text-sm font-medium text-gray-700">{{ $statusLabel }}</span>
                        </label>
                    @endforeach
                    <p class="text-xs text-gray-400 mt-1">
                        {{ __('admin.banners.if_active_but_future_note') }}
                    </p>
                </div>
            </x-card>

            {{-- Stats card (edit only) ──────────────────────────────────────── --}}
            @if($isEdit)
                <x-card title="{{ __('admin.banners.performance_stats') }}">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.banners.impressions_today') }}</dt>
                            <dd class="font-semibold text-gray-800">{{ number_format($banner->impressions_count) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.banners.clicks') }}</dt>
                            <dd class="font-semibold text-gray-800">{{ number_format($banner->clicks_count) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.banners.ctr') }}</dt>
                            <dd class="font-semibold text-gray-800">
                                @if($banner->impressions_count > 0)
                                    {{ round($banner->clicks_count / $banner->impressions_count * 100, 2) }}%
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('admin.banners.created') }}</dt>
                            <dd class="text-gray-700">{{ $banner->created_at?->format('d M Y') }}</dd>
                        </div>
                    </dl>
                </x-card>
            @endif

        </div>
    </div>
</div>
