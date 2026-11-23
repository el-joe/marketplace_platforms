{{--
    The one category form, used by both create and edit (single source of truth).
    Include with: @include('admin.categories._form', ['mode' => 'create'])
                  @include('admin.categories._form', ['mode' => 'edit', 'category' => $category])

    Every section — general, status, countries, attributes, shipping, commissions,
    SEO, marketers, image and contract — is part of this one <form> and is saved
    by one request (CategoryFormService, one DB transaction). There are no
    per-section save buttons. The parent view wraps this in the <form> tag.
--}}
@php
    $category = $category ?? null;
    $isEdit = $category !== null;

    $val = function (string $field, $default = '') use ($isEdit, $category) {
        return old($field, $isEdit ? ($category->{$field} ?? $default) : $default);
    };

    $bool = function (string $field, bool $default = false) use ($isEdit, $category): bool {
        $raw = old($field, $isEdit ? ($category->{$field} ?? $default) : $default);
        return (bool) $raw;
    };

    $sections = [
        ['id' => 'general',     'label' => __('category_form.sections.general'),     'icon' => 'information-circle', 'prefixes' => 'name_en,name_ar,slug,parent_id,description_en,description_ar,sort_order'],
        ['id' => 'status',      'label' => __('category_form.sections.status'),      'icon' => 'eye',                'prefixes' => 'is_active,is_visible,is_featured,has_filters'],
        ['id' => 'countries',   'label' => __('category_form.sections.countries'),   'icon' => 'globe-alt',          'prefixes' => 'availability_mode,availability_countries'],
        ['id' => 'attributes',  'label' => __('category_form.sections.attributes'),  'icon' => 'tag',                'prefixes' => 'attributes'],
        ['id' => 'shipping',    'label' => __('category_form.sections.shipping'),    'icon' => 'truck',              'prefixes' => 'shipping_methods'],
        ['id' => 'commissions', 'label' => __('category_form.sections.commissions'), 'icon' => 'banknotes',          'prefixes' => 'commission_rate,commission_fbp_pct,commission_fbp_fixed,commission_fbm_pct,commission_fbm_fixed,commission_fbn_pct,commission_fbn_fixed,country_commissions,tiers'],
        ['id' => 'seo',         'label' => __('category_form.sections.seo'),         'icon' => 'magnifying-glass',   'prefixes' => 'seo_title_en,seo_title_ar,seo_description_en,seo_description_ar'],
        ['id' => 'marketers',   'label' => __('category_form.sections.marketers'),   'icon' => 'user-group',         'prefixes' => 'influencer_sample_qty,affiliate_sample_qty,platform_sample_qty,min_stock_for_campaign'],
    ];

    $sectionCard = 'bg-white rounded-xl border border-gray-200 shadow-sm scroll-mt-36 transition-shadow data-[has-errors]:border-danger-300 data-[has-errors]:ring-2 data-[has-errors]:ring-danger-100';
    $maxFeatured = \App\Services\CategoryService::MAX_FEATURED;
@endphp

<input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">
{{-- Lists this form renders in full: the server replaces them with what is sent (an emptied list is cleared). --}}
@foreach(['attributes', 'shipping_methods', 'country_commissions', 'tiers', 'availability'] as $syncSection)
    <input type="hidden" name="sync[]" value="{{ $syncSection }}">
@endforeach

{{-- Sticky section navigation --}}
<nav id="category-section-nav"
     class="sticky top-16 z-20 -mx-4 lg:-mx-6 mb-6 border-b border-gray-200 bg-gray-50/95 px-4 lg:px-6 backdrop-blur"
     aria-label="{{ __('category_form.sections.nav_aria') }}">
    <div class="flex items-center gap-3">
        <ul class="flex flex-1 gap-1 overflow-x-auto py-2 [scrollbar-width:none]">
            @foreach($sections as $section)
                <li class="shrink-0">
                    <a href="#sec-{{ $section['id'] }}"
                       data-section-link="{{ $section['id'] }}"
                       class="group inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-white hover:text-gray-900 data-[active]:bg-white data-[active]:text-primary-700 data-[active]:shadow-sm">
                        <x-heroicon name="{{ $section['icon'] }}" class="h-4 w-4" />
                        {{ $section['label'] }}
                        <span data-section-error-badge class="hidden min-w-5 rounded-full bg-danger-600 px-1.5 text-center text-xs font-semibold leading-5 text-white"></span>
                    </a>
                </li>
            @endforeach
        </ul>
        <span data-dirty-indicator class="hidden shrink-0 items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
            {{ __('category_form.unsaved_changes') }}
        </span>
    </div>
</nav>

<div class="flex flex-col items-start gap-6 lg:flex-row">

    {{-- ═══════════════ MAIN COLUMN: every section, stacked ═══════════════ --}}
    <div class="w-full min-w-0 flex-1 space-y-6">

        {{-- General --}}
        <section id="sec-general" data-form-section="general" data-error-prefixes="{{ $sections[0]['prefixes'] }}" class="{{ $sectionCard }}">
            @include('admin.categories._section_header', ['title' => $sections[0]['label'], 'icon' => $sections[0]['icon']])
            <div class="space-y-5 p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-form.input name="name_en" label="{{ __('admin.name_en') }}" :value="$val('name_en')" required maxlength="255" dir="ltr"
                        placeholder="{{ __('admin.categories.name_en_placeholder') }}" />
                    <x-form.input name="name_ar" label="{{ __('admin.name_ar') }}" :value="$val('name_ar')" required maxlength="255" dir="rtl"
                        placeholder="{{ __('admin.categories.name_ar_placeholder') }}" />
                </div>

                <x-form.input name="slug" label="{{ __('admin.slug') }}" :value="$val('slug')" maxlength="255"
                    placeholder="{{ __('admin.categories.slug_auto_generated_placeholder') }}" data-slug-input data-slug-source="name_en" />

                <div class="space-y-1">
                    <label for="parent_id" class="block text-sm font-medium text-gray-700">{{ __('admin.categories.parent_category') }}</label>
                    <select name="parent_id" id="parent_id" class="input w-full">
                        <option value="">{{ __('admin.categories.root_no_parent') }}</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent['id'] }}" @selected(old('parent_id', $isEdit ? $category->parent_id : '') == $parent['id'])>{{ $parent['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-form.textarea name="description_en" label="{{ __('admin.description_en') }}" :value="$val('description_en')" rows="3" maxlength="2000" dir="ltr" />
                    <x-form.textarea name="description_ar" label="{{ __('admin.description_ar') }}" :value="$val('description_ar')" rows="3" maxlength="2000" dir="rtl" />
                </div>

                <div class="max-w-xs">
                    <x-form.input name="sort_order" label="{{ __('admin.sort_order') }}" type="number" :value="$val('sort_order', '0')" min="0" />
                </div>
            </div>
        </section>

        {{-- Status & visibility: what each flag does on the storefront --}}
        <section id="sec-status" data-form-section="status" data-error-prefixes="{{ $sections[1]['prefixes'] }}" class="{{ $sectionCard }}">
            @include('admin.categories._section_header', ['title' => $sections[1]['label'], 'icon' => $sections[1]['icon']])
            <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                @foreach([
                    ['name' => 'is_active',   'label' => __('common.active'),                          'hint' => __('category_form.flags.active_hint'),   'default' => true],
                    ['name' => 'is_visible',  'label' => __('admin.is_visible'),                       'hint' => __('category_form.flags.visible_hint'),  'default' => true],
                    ['name' => 'is_featured', 'label' => __('admin.categories.featured_homepage'),     'hint' => __('category_form.flags.featured_hint', ['max' => $maxFeatured]), 'default' => false],
                    ['name' => 'has_filters', 'label' => __('admin.categories.has_filters'),           'hint' => __('category_form.flags.has_filters_hint'), 'default' => false],
                ] as $flag)
                    <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-4">
                        {{-- Hidden "0" so switching a flag off is actually saved --}}
                        <input type="hidden" name="{{ $flag['name'] }}" value="0">
                        <x-form.toggle :name="$flag['name']" :label="$flag['label']" :value="$bool($flag['name'], $flag['default'])" />
                        <p class="mt-2 text-xs leading-relaxed text-gray-500">{{ $flag['hint'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Country availability (stored as the country "Category exceptions") --}}
        <section id="sec-countries" data-form-section="countries" data-error-prefixes="{{ $sections[2]['prefixes'] }}" class="{{ $sectionCard }}">
            @include('admin.categories._section_header', ['title' => $sections[2]['label'], 'icon' => $sections[2]['icon']])
            @include('admin.categories._availability_section')
        </section>

        {{-- Attributes --}}
        <section id="sec-attributes" data-form-section="attributes" data-error-prefixes="{{ $sections[3]['prefixes'] }}" class="{{ $sectionCard }}">
            @include('admin.categories._section_header', ['title' => $sections[3]['label'], 'icon' => $sections[3]['icon']])
            <div class="space-y-4 p-6">
                <p class="text-sm text-gray-500">{{ __('admin.categories.attributes_tab_hint') }}</p>

                @if($allAttributes->isEmpty())
                    <p class="text-sm italic text-gray-400">{{ __('admin.categories.no_attributes_defined') }} <a href="{{ route('admin.attributes.create') }}" class="text-primary-600 underline">{{ __('admin.categories.create_attributes_first') }}</a></p>
                @else
                    <div class="max-h-96 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200">
                        @foreach($allAttributes as $attr)
                            @php
                                $assigned = $isEdit ? $category->categoryAttributes->firstWhere('attribute_id', $attr->id) : null;
                            @endphp
                            <div class="flex items-center gap-4 bg-white px-4 py-3 hover:bg-gray-50">
                                <input type="checkbox" name="attributes[{{ $loop->index }}][attribute_id]" value="{{ $attr->id }}" id="attr_{{ $attr->id }}"
                                    class="rounded border-gray-300 text-primary-600" @checked($assigned !== null) />
                                <label for="attr_{{ $attr->id }}" class="flex-1 cursor-pointer">
                                    <span class="text-sm font-medium text-gray-900">{{ localized($attr) }}</span>
                                    <span class="ms-2 text-xs text-gray-400">{{ $attr->code }}</span>
                                    <span class="ms-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ $attr->type?->value }}</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-1 text-xs text-gray-600">
                                    <input type="checkbox" name="attributes[{{ $loop->index }}][is_required]" value="1" class="rounded border-gray-300 text-primary-600" @checked($assigned?->is_required) />
                                    {{ __('admin.required') }}
                                </label>
                                <input type="hidden" name="attributes[{{ $loop->index }}][sort_order]" value="{{ $loop->index }}" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Shipping methods --}}
        <section id="sec-shipping" data-form-section="shipping" data-error-prefixes="{{ $sections[4]['prefixes'] }}" class="{{ $sectionCard }}">
            @include('admin.categories._section_header', ['title' => $sections[4]['label'], 'icon' => $sections[4]['icon']])
            @include('admin.categories._shipping_methods_tab')
        </section>

        {{-- Commissions: per-country overrides + price tiers --}}
        <section id="sec-commissions" data-form-section="commissions" data-error-prefixes="{{ $sections[5]['prefixes'] }}" class="{{ $sectionCard }}">
            @include('admin.categories._section_header', ['title' => $sections[5]['label'], 'icon' => $sections[5]['icon']])
            @include('admin.categories._commission_tiers_tab')
        </section>

        {{-- SEO --}}
        <section id="sec-seo" data-form-section="seo" data-error-prefixes="{{ $sections[6]['prefixes'] }}" class="{{ $sectionCard }}">
            @include('admin.categories._section_header', ['title' => $sections[6]['label'], 'icon' => $sections[6]['icon']])
            <div class="space-y-5 p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-form.input name="seo_title_en" label="{{ __('admin.categories.seo_title_en') }}" :value="$val('seo_title_en')" maxlength="70" dir="ltr"
                        placeholder="{{ __('admin.categories.seo_title_placeholder') }}" />
                    <x-form.input name="seo_title_ar" label="{{ __('admin.categories.seo_title_ar') }}" :value="$val('seo_title_ar')" maxlength="70" dir="rtl" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-form.textarea name="seo_description_en" label="{{ __('admin.categories.seo_description_en') }}" :value="$val('seo_description_en')" rows="3" maxlength="160" dir="ltr" />
                    <x-form.textarea name="seo_description_ar" label="{{ __('admin.categories.seo_description_ar') }}" :value="$val('seo_description_ar')" rows="3" maxlength="160" dir="rtl" />
                </div>
            </div>
        </section>

        {{-- Marketers --}}
        <section id="sec-marketers" data-form-section="marketers" data-error-prefixes="{{ $sections[7]['prefixes'] }}" class="{{ $sectionCard }}">
            @include('admin.categories._section_header', ['title' => $sections[7]['label'], 'icon' => $sections[7]['icon']])
            <div class="space-y-5 p-6">
                {{-- Marketer commissions live on the Marketer Settings page; per-marketer values on the marketer profile --}}
                <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800" data-testid="marketer-commission-moved">
                    <div class="font-semibold">{{ __('admin.categories.marketer_commission_moved_title') }}</div>
                    <p class="mt-1 text-xs">{{ __('admin.categories.marketer_commission_moved_body') }}</p>
                    @if (auth('admin')->user()?->can('marketer_commission_settings.view'))
                        <a href="{{ route('admin.marketer-settings.index') }}" class="mt-2 inline-flex items-center text-xs font-medium text-blue-700 underline hover:text-blue-900">
                            {{ __('admin.categories.marketer_commission_moved_link') }}
                        </a>
                    @endif
                </div>

                <div>
                    <h4 class="mb-3 text-sm font-semibold text-gray-700">{{ __('admin.categories.sample_and_campaign_settings') }}</h4>
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        @foreach(['influencer_sample_qty', 'affiliate_sample_qty', 'platform_sample_qty', 'min_stock_for_campaign'] as $sampleField)
                            <x-form.input :name="$sampleField" type="number" min="0" step="1"
                                label="{{ __('admin.categories.'.$sampleField) }}" :value="$val($sampleField, 0)" />
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ═══════════════ SIDEBAR: save, image, contract ═══════════════ --}}
    <aside class="w-full shrink-0 space-y-4 lg:sticky lg:top-36 lg:w-80">

        {{-- Save all --}}
        <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div id="category-form-errors" role="alert" class="{{ $errors->any() ? '' : 'hidden' }} space-y-1 rounded-lg border border-danger-200 bg-danger-50 p-3 text-xs text-danger-700">
                <p class="font-semibold">{{ __('category_form.errors_title') }}</p>
                <ul data-error-list class="list-inside list-disc space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>

            <button type="submit" id="submit-btn" class="btn btn-primary w-full justify-center"
                data-label="{{ $isEdit ? __('category_form.save_all') : __('category_form.create_all') }}">
                {{ $isEdit ? __('category_form.save_all') : __('category_form.create_all') }}
            </button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost block w-full justify-center text-center">{{ __('common.cancel') }}</a>
            <p class="text-xs leading-relaxed text-gray-400">{{ __('category_form.save_all_hint') }}</p>
        </div>

        {{-- Image (uploaded with the form) --}}
        <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-form-section="image" data-error-prefixes="image,remove_image"
             x-data="categoryImageField(@js($isEdit ? ($category->image_url ?? '') : ''))"
             @category-form:saved.window="saved($event.detail)">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.categories.category_image') }}</h3>

            <div class="relative flex min-h-32 items-center justify-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-gray-100">
                <template x-if="preview">
                    <img :src="preview" alt="{{ __('admin.static_text.admin_categories_form.category_image') }}" class="max-h-48 max-w-full object-contain" />
                </template>
                <template x-if="!preview">
                    <span class="text-xs text-gray-400">{{ __('admin.categories.no_image_yet') }}</span>
                </template>
                <button type="button" x-show="preview" x-cloak @click="remove()"
                    class="absolute end-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-gray-600 shadow hover:bg-danger-600 hover:text-white"
                    title="{{ __('category_form.image.remove') }}" aria-label="{{ __('category_form.image.remove') }}">
                    <x-heroicon name="x-mark" class="h-4 w-4" />
                </button>
            </div>

            <input type="hidden" name="remove_image" :value="removed ? 1 : 0">
            <label class="btn btn-secondary btn-sm w-full cursor-pointer justify-center">
                <x-heroicon name="photo" class="h-4 w-4" />
                <span x-text="preview ? @js(__('category_form.image.replace')) : @js(__('category_form.image.choose'))"></span>
                <input type="file" name="image" accept="image/*" class="sr-only" x-ref="file" @change="pick($event)">
            </label>
            <p x-show="pending" x-cloak class="text-xs text-primary-700">{{ __('category_form.image.pending') }}</p>
            <p x-show="removed" x-cloak class="text-xs text-amber-700">{{ __('category_form.image.will_remove') }}</p>
            <p class="text-xs text-gray-400">{{ __('admin.categories.image_hint') }}</p>
        </div>

        {{-- Contract requirement --}}
        <div data-form-section="contract" data-error-prefixes="requires_contract,country_contracts,contract_template_id">
            @include('admin.contracts.partials.requirement', [
                'scope' => 'product',
                'subject' => $category,
                'contractData' => app(\App\Services\Vendor\ContractAssignmentService::class)->formData('product', $category),
                'contractGaps' => $category ? app(\App\Services\Vendor\ContractAssignmentService::class)->gaps($category, 'product') : collect(),
            ])
        </div>
    </aside>
</div>

<script>
    window.CATEGORY_FORM_I18N = @js([
        'saving' => __('category_form.saving'),
        'saved' => __('category_form.all_saved'),
        'fixErrors' => __('category_form.fix_errors'),
        'saveFailed' => __('category_form.save_failed'),
        'leaveConfirm' => __('category_form.unsaved_leave_confirm'),
        'errorsInSection' => __('category_form.errors_in_section'),
    ]);

    function categoryImageField(initialUrl) {
        return {
            initial: initialUrl || '',
            preview: initialUrl || '',
            pending: false,
            removed: false,
            pick(event) {
                const file = event.target.files[0];
                if (!file) return;
                this.pending = true;
                this.removed = false;
                const reader = new FileReader();
                reader.onload = (e) => { this.preview = e.target.result; };
                reader.readAsDataURL(file);
            },
            remove() {
                this.$refs.file.value = '';
                this.removed = this.initial !== '';
                this.pending = false;
                this.preview = '';
                this.$refs.file.dispatchEvent(new Event('change', { bubbles: true }));
            },
            saved(detail) {
                this.$refs.file.value = '';
                this.initial = (detail && detail.image_url) || '';
                this.preview = this.initial;
                this.pending = false;
                this.removed = false;
            },
        };
    }
</script>
