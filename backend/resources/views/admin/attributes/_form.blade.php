{{--
    Shared Attribute form partial.
    Include with: @include('admin.attributes._form', ['mode' => 'create'])
                  @include('admin.attributes._form', ['mode' => 'edit', 'attribute' => $attribute])
--}}
@php
    $attribute = $attribute ?? null;
    $isEdit    = $attribute !== null;

    $val = function (string $field, $default = '') use ($isEdit, $attribute) {
        return old($field, $isEdit ? ($attribute->{$field} ?? $default) : $default);
    };

    $bool = function (string $field, bool $default = false) use ($isEdit, $attribute): bool {
        $raw = old($field, $isEdit ? ($attribute->{$field} ?? $default) : $default);
        return (bool) $raw;
    };

    $typeRequiresValues = $isEdit && in_array($attribute->type?->value, ['select', 'multi_select', 'color']);
@endphp

<div
    x-data="{
        activeTab: 'general',
        attrType: '{{ $val('type', 'text') }}',
        get requiresValues() {
            return ['select', 'multi_select', 'color'].includes(this.attrType);
        }
    }"
    class="space-y-6"
>
    <input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════════════════════════════════════════ --}}
        {{-- LEFT: Tabbed panels                         --}}
        {{-- ═══════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0">

            {{-- Tab navigation --}}
            <div class="bg-white rounded-t-xl border border-gray-200 overflow-hidden">
                <nav class="flex overflow-x-auto border-b border-gray-100" aria-label="{{ __('admin.attributes_section.form_tabs_aria') }}">
                    @foreach([
                        ['id' => 'general', 'label' => __('admin.attributes_section.tab_general'), 'icon' => 'information-circle'],
                        ['id' => 'values',  'label' => __('admin.attributes_section.tab_values'),  'icon' => 'list-bullet'],
                    ] as $tab)
                    <button
                        type="button"
                        @click="activeTab = '{{ $tab['id'] }}'"
                        :class="activeTab === '{{ $tab['id'] }}'
                            ? 'border-b-2 border-primary-600 text-primary-700 bg-primary-50/50'
                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        class="flex items-center gap-1.5 px-4 py-3.5 text-sm font-medium -mb-px whitespace-nowrap transition-colors"
                        @if($tab['id'] === 'values') :class="!requiresValues && 'opacity-40 pointer-events-none'" @endif
                    >
                        <x-heroicon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                        {{ $tab['label'] }}
                    </button>
                    @endforeach
                </nav>
            </div>

            {{-- TAB: General --}}
            <div
                x-show="activeTab === 'general'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm space-y-5"
            >
                <div class="grid grid-cols-2 gap-4">
                    <x-form.input
                        name="name_en"
                        label="{{ __('admin.attributes_section.attribute_name_en') }}"
                        :value="$val('name_en')"
                        required
                        maxlength="255"
                        dir="ltr"
                        placeholder="{{ __('admin.attributes_section.name_en_placeholder') }}"
                    />
                    <x-form.input
                        name="name_ar"
                        label="{{ __('admin.attributes_section.attribute_name_ar') }}"
                        :value="$val('name_ar')"
                        required
                        maxlength="255"
                        dir="rtl"
                        placeholder="{{ __('admin.attributes_section.name_ar_placeholder') }}"
                    />
                </div>

                {{-- Code — locked after creation --}}
                <div>
                    <x-form.input
                        name="code"
                        label="{{ __('admin.attributes_section.code') }}"
                        :value="$val('code')"
                        :disabled="$isEdit"
                        required="{{ !$isEdit }}"
                        maxlength="100"
                        placeholder="{{ __('admin.attributes_section.code_placeholder') }}"
                        pattern="[a-z_]+"
                    />
                    @if($isEdit)
                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.attributes_section.code_hint') }}</p>
                    @endif
                </div>

                {{-- Type — locked after creation --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.attributes_section.type') }}</label>
                    <select name="type" class="input w-full" x-model="attrType" @if($isEdit) disabled @endif>
                        <option value="text"         @selected($val('type') === 'text')>{{ __('admin.attributes_section.type_text') }}</option>
                        <option value="number"       @selected($val('type') === 'number')>{{ __('admin.attributes_section.type_number') }}</option>
                        <option value="select"       @selected($val('type') === 'select')>{{ __('admin.attributes_section.type_select') }}</option>
                        <option value="multi_select" @selected($val('type') === 'multi_select')>{{ __('admin.attributes_section.type_multi_select') }}</option>
                        <option value="boolean"      @selected($val('type') === 'boolean')>{{ __('admin.attributes_section.type_boolean') }}</option>
                        <option value="color"        @selected($val('type') === 'color')>{{ __('admin.attributes_section.type_color') }}</option>
                        <option value="date"         @selected($val('type') === 'date')>{{ __('admin.attributes_section.type_date') }}</option>
                    </select>
                    @if($isEdit)
                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.attributes_section.type_hint') }}</p>
                    @endif
                </div>

                {{-- Unit --}}
                <x-form.input
                    name="unit"
                    label="{{ __('admin.attributes_section.unit') }}"
                    :value="$val('unit')"
                    maxlength="50"
                    placeholder="{{ __('admin.attributes_section.unit_placeholder') }}"
                />

                {{-- Sort Order --}}
                <x-form.input
                    name="sort_order"
                    label="{{ __('admin.attributes_section.sort_order') }}"
                    type="number"
                    :value="$val('sort_order', '0')"
                    min="0"
                />
            </div>

            {{-- TAB: Values (only enabled for select / multi_select / color) --}}
            <div
                x-show="activeTab === 'values'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm space-y-4"
            >
                <div x-show="!requiresValues" class="text-sm text-gray-400 italic">
                    {{ __('admin.attributes_section.values_only_hint') }}
                </div>

                <div x-show="requiresValues" class="space-y-3">

                    {{-- On create: add rows inline --}}
                    @if(!$isEdit)
                    <div id="value-rows" class="space-y-2">
                        <div class="value-row grid grid-cols-12 gap-2 items-end">
                            <div class="col-span-3">
                                <label class="text-xs font-medium text-gray-600">{{ __('admin.attributes_section.value_en') }}</label>
                                <input type="text" name="values[0][value_en]" class="input w-full mt-1 value-en-input" placeholder="{{ __('admin.attributes_section.value_en_placeholder') }}" dir="ltr" />
                            </div>
                            <div class="col-span-3">
                                <label class="text-xs font-medium text-gray-600">{{ __('admin.attributes_section.value_ar') }}</label>
                                <input type="text" name="values[0][value_ar]" class="input w-full mt-1" placeholder="{{ __('admin.attributes_section.value_ar_placeholder') }}" dir="rtl" />
                            </div>
                            <div class="col-span-4">
                                <label class="text-xs font-medium text-gray-600">{{ __('admin.attributes_section.slug') }}</label>
                                <input type="text" name="values[0][slug]" class="input w-full mt-1 font-mono text-sm value-slug-input" placeholder="{{ __('admin.attributes_section.slug_placeholder') }}" dir="ltr" pattern="[a-z0-9-]+" />
                            </div>
                            <div class="col-span-1" x-show="attrType === 'color'">
                                <label class="text-xs font-medium text-gray-600">{{ __('admin.attributes_section.hex_color') }}</label>
                                <input type="color" name="values[0][code_hex]" class="w-full h-9 mt-1 rounded border border-gray-300 cursor-pointer" value="#000000" />
                            </div>
                            <div class="col-span-1 flex items-end pb-0.5">
                                <button type="button" class="remove-value-row text-gray-300 hover:text-red-500 transition-colors" title="{{ __('admin.remove') }}">
                                    <x-heroicon name="x-mark" class="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="add-value-row"
                        class="btn btn-ghost btn-sm text-primary-600">
                        <x-heroicon name="plus" class="w-4 h-4 mr-1" />
                        {{ __('admin.attributes_section.add_value') }}
                    </button>
                    @else
                    {{-- On edit: AJAX-managed list --}}
                    <div id="values-list" class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                        @forelse($attribute->values as $val)
                        <div class="flex items-center gap-3 px-4 py-2.5 bg-white value-item" data-id="{{ $val->id }}">
                            <div class="swatch-image-widget flex-shrink-0" data-id="{{ $val->id }}"
                                data-upload-url="{{ route('admin.attributes.values.upload-swatch', [$attribute->id, $val->id]) }}"
                                data-delete-url="{{ route('admin.attributes.values.delete-swatch', [$attribute->id, $val->id]) }}">
                                <div class="swatch-image-preview w-8 h-8 rounded border border-gray-200 bg-gray-50 bg-cover bg-center flex items-center justify-center cursor-pointer overflow-hidden"
                                    @if($val->swatch_image_url) style="background-image:url('{{ $val->swatch_image_url }}')" @endif
                                    title="{{ __('admin.attributes_section.swatch_image') }}">
                                    @unless($val->swatch_image_url)
                                    <x-heroicon name="photo" class="w-4 h-4 text-gray-300" />
                                    @endunless
                                </div>
                                <input type="file" class="swatch-image-input hidden" accept="image/png,image/jpeg,image/webp" />
                                @if($val->swatch_image_url)
                                <button type="button" class="swatch-image-remove text-[10px] text-red-500 hover:underline block mt-0.5">{{ __('common.remove') }}</button>
                                @endif
                            </div>
                            <span class="flex-1 text-sm text-gray-800">{{ $val->value_en }}</span>
                            <span class="text-sm text-gray-400" dir="rtl">{{ $val->value_ar }}</span>
                            <span class="text-xs font-mono text-gray-400 value-slug" title="{{ __('admin.attributes_section.slug') }}">{{ $val->slug }}</span>
                            @if($attribute->type === \App\Enums\AttributeType::Color && $val->code_hex)
                            <span class="w-5 h-5 rounded-full border border-gray-200 flex-shrink-0"
                                style="background:{{ $val->code_hex }}"></span>
                            @endif
                            <button type="button"
                                class="edit-value-btn text-xs text-primary-600 hover:underline"
                                data-id="{{ $val->id }}"
                                data-value-en="{{ $val->value_en }}"
                                data-value-ar="{{ $val->value_ar }}"
                                data-slug="{{ $val->slug }}"
                                data-color-hex="{{ $val->code_hex }}"
                                data-regenerate-url="{{ route('admin.attributes.values.regenerate-variant-slugs', [$attribute->id, $val->id]) }}">
                                {{ __('common.edit') }}
                            </button>
                            <button type="button"
                                class="delete-value-btn text-xs text-red-500 hover:underline"
                                data-id="{{ $val->id }}"
                                data-url="{{ route('admin.attributes.values.destroy', [$attribute->id, $val->id]) }}">
                                {{ __('common.delete') }}
                            </button>
                        </div>
                        <div class="value-slug-warning hidden px-4 py-2 bg-amber-50 border-t border-amber-200 text-xs text-amber-800 flex items-center justify-between gap-3" data-id="{{ $val->id }}">
                            <span class="warning-text"></span>
                            <button type="button" class="regenerate-slugs-btn btn btn-xs btn-outline text-amber-800 border-amber-300 hover:bg-amber-100 whitespace-nowrap" data-id="{{ $val->id }}">
                                {{ __('admin.attributes_section.regenerate_variant_slugs') }}
                            </button>
                        </div>
                        @empty
                        <p class="px-4 py-3 text-sm text-gray-400 italic">{{ __('admin.attributes_section.no_values') }}</p>
                        @endforelse
                    </div>

                    {{-- Add value form --}}
                    <div id="add-value-form" class="border border-dashed border-gray-300 rounded-lg p-4 space-y-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.attributes_section.add_new_value') }}</p>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" id="new-value-en" class="input w-full" placeholder="{{ __('admin.attributes_section.new_value_en_placeholder') }}" dir="ltr" />
                            <input type="text" id="new-value-ar" class="input w-full" placeholder="{{ __('admin.attributes_section.new_value_ar_placeholder') }}" dir="rtl" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">{{ __('admin.attributes_section.slug') }}</label>
                            <input type="text" id="new-value-slug" class="input w-full mt-1 font-mono text-sm" placeholder="{{ __('admin.attributes_section.slug_placeholder') }}" dir="ltr" pattern="[a-z0-9-]+" />
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.attributes_section.slug_hint') }}</p>
                        </div>
                        @if($attribute->type === \App\Enums\AttributeType::Color)
                        <div class="flex items-center gap-3">
                            <label class="text-xs font-medium text-gray-600">{{ __('admin.attributes_section.hex_color') }}</label>
                            <input type="color" id="new-color-hex" class="w-10 h-8 rounded border border-gray-300 cursor-pointer" value="#000000" />
                            <input type="text" id="new-color-hex-text" class="input w-28 text-xs" placeholder="#000000" maxlength="7" />
                        </div>
                        @endif
                        <button type="button" id="save-new-value" class="btn btn-primary btn-sm">{{ __('admin.attributes_section.add_value') }}</button>
                    </div>
                    @endif

                </div>
            </div>

        </div>{{-- /left column --}}

        {{-- ═══════════════════════════════════════════ --}}
        {{-- RIGHT SIDEBAR                               --}}
        {{-- ═══════════════════════════════════════════ --}}
        <div class="w-full lg:w-72 flex-shrink-0 space-y-4">

            {{-- Flags --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.attributes_section.behaviour_heading') }}</h3>
                <x-form.toggle name="is_variant_attribute" label="{{ __('admin.attributes_section.generates_variants') }}"   :checked="$bool('is_variant_attribute')" />
                <x-form.toggle name="is_filterable"        label="{{ __('admin.attributes_section.show_in_filters') }}"       :checked="$bool('is_filterable')" />
                <x-form.toggle name="is_required"          label="{{ __('admin.attributes_section.required_for_products') }}" :checked="$bool('is_required')" />
            </div>

            {{-- Save --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-2">
                @if($errors->any())
                <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-700 space-y-1">
                    @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                    @endforeach
                </div>
                @endif

                <button type="submit" id="submit-btn" class="btn btn-primary w-full justify-center">
                    {{ $isEdit ? __('admin.attributes_section.save_changes') : __('admin.attributes_section.create_attribute') }}
                </button>
                <a href="{{ route('admin.attributes.index') }}"
                    class="btn btn-ghost w-full justify-center text-center block">
                    {{ __('common.cancel') }}
                </a>
            </div>

        </div>{{-- /right sidebar --}}

    </div>{{-- /flex row --}}

</div>{{-- /x-data root --}}

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            valueEnLabel: @json(__('admin.attributes_section.value_en')),
            valueArLabel: @json(__('admin.attributes_section.value_ar')),
            hexColorLabel: @json(__('admin.attributes_section.hex_color')),
            valueEnPlaceholder: @json(__('admin.attributes_section.value_en_placeholder')),
            valueArPlaceholder: @json(__('admin.attributes_section.value_ar_placeholder')),
            remove: @json(__('admin.remove')),
            valueEnRequired: @json(__('admin.attributes_section.value_en_required')),
            valueAdded: @json(__('admin.attributes_section.value_added')),
            valueAddFailed: @json(__('admin.attributes_section.value_add_failed')),
            deleteValueConfirm: @json(__('admin.attributes_section.delete_value_confirm')),
            deleteValueTitle: @json(__('admin.attributes_section.delete_value_title')),
            valueDeleted: @json(__('admin.attributes_section.value_deleted')),
            valueDeleteFailed: @json(__('admin.attributes_section.value_delete_failed')),
            promptValueEn: @json(__('admin.attributes_section.prompt_value_en')),
            promptValueAr: @json(__('admin.attributes_section.prompt_value_ar')),
            valueUpdated: @json(__('admin.attributes_section.value_updated')),
            valueUpdateFailed: @json(__('admin.attributes_section.value_update_failed')),
            edit: @json(__('common.edit')),
            delete: @json(__('common.delete')),
            savingEllipsis: @json(__('admin.attributes_section.saving_ellipsis')),
            saveChanges: @json(__('admin.attributes_section.save_changes')),
            attributeSaved: @json(__('admin.attributes_section.attribute_saved')),
            validationError: @json(__('admin.attributes_section.validation_error')),
            saveFailedGeneric: @json(__('admin.attributes_section.save_failed_generic')),
            promptSlug: @json(__('admin.attributes_section.prompt_slug')),
            slugStaleWarning: @json(__('admin.attributes_section.slug_stale_warning')),
            regenerateVariantSlugs: @json(__('admin.attributes_section.regenerate_variant_slugs')),
            regenerateSlugsFailed: @json(__('admin.attributes_section.regenerate_slugs_failed')),
        });
    </script>
@endpush
