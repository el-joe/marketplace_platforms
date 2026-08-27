{{--
    Shared Category form partial.
    Include with: @include('admin.categories._form', ['mode' => 'create'])
                  @include('admin.categories._form', ['mode' => 'edit', 'category' => $category])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
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
@endphp

<div
    x-data="{ activeTab: 'general' }"
    class="space-y-6"
>
    <input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════════════════════════════════════════ --}}
        {{-- LEFT COLUMN: tabbed panels                  --}}
        {{-- ═══════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0">

            {{-- Tab navigation --}}
            <div class="bg-white rounded-t-xl border border-gray-200 overflow-hidden">
                <nav class="flex overflow-x-auto border-b border-gray-100" aria-label="{{ __('admin.categories.form_tabs_aria') }}">
                    @foreach([
                        ['id' => 'general',    'label' => __('admin.general'),    'icon' => 'information-circle'],
                        ['id' => 'attributes', 'label' => __('admin.categories.attributes_tab'), 'icon' => 'tag'],
                        ['id' => 'shipping',   'label' => __('admin.categories.shipping_methods_tab'), 'icon' => 'truck'],
                        ['id' => 'seo',        'label' => __('admin.categories.seo_tab'),        'icon' => 'magnifying-glass'],
                        ['id' => 'marketers',  'label' => __('admin.categories.marketers_tab'),  'icon' => 'user-group'],
                    ] as $tab)
                    <button
                        type="button"
                        @click="activeTab = '{{ $tab['id'] }}'"
                        :class="activeTab === '{{ $tab['id'] }}'
                            ? 'border-b-2 border-primary-600 text-primary-700 bg-primary-50/50'
                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                        class="flex items-center gap-1.5 px-4 py-3.5 text-sm font-medium -mb-px whitespace-nowrap transition-colors"
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
                {{-- Names --}}
                <div class="grid grid-cols-2 gap-4">
                    <x-form.input
                        name="name_en"
                        label="{{ __('admin.name_en') }}"
                        :value="$val('name_en')"
                        required
                        maxlength="255"
                        dir="ltr"
                        placeholder="{{ __('admin.categories.name_en_placeholder') }}"
                    />
                    <x-form.input
                        name="name_ar"
                        label="{{ __('admin.name_ar') }}"
                        :value="$val('name_ar')"
                        required
                        maxlength="255"
                        dir="rtl"
                        placeholder="{{ __('admin.categories.name_ar_placeholder') }}"
                    />
                </div>

                {{-- Slug --}}
                <x-form.input
                    name="slug"
                    label="{{ __('admin.slug') }}"
                    :value="$val('slug')"
                    maxlength="255"
                    placeholder="{{ __('admin.categories.slug_auto_generated_placeholder') }}"
                    data-slug-input
                    data-slug-source="name_en"
                />

                {{-- Parent --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.categories.parent_category') }}</label>
                    <select name="parent_id" class="input w-full">
                        <option value="">{{ __('admin.categories.root_no_parent') }}</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent['id'] }}"
                                @selected(old('parent_id', $isEdit ? $category->parent_id : '') == $parent['id'])>
                                {!! e($parent['name']) !!}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Descriptions --}}
                <div class="grid grid-cols-2 gap-4">
                    <x-form.textarea
                        name="description_en"
                        label="{{ __('admin.description_en') }}"
                        :value="$val('description_en')"
                        rows="3"
                        maxlength="2000"
                        dir="ltr"
                    />
                    <x-form.textarea
                        name="description_ar"
                        label="{{ __('admin.description_ar') }}"
                        :value="$val('description_ar')"
                        rows="3"
                        maxlength="2000"
                        dir="rtl"
                    />
                </div>

                {{-- Commission Rates (FBP / FBN) --}}
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">
                        {{ __('admin.categories.commission_rates') }}
                        <span class="text-xs font-normal text-gray-400 ml-2">{{ __('admin.categories.commission_rates_hint') }}</span>
                    </h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- FBP block --}}
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-600 mb-3">{{ __('admin.categories.fbp_label') }}</p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.percentage_rate') }}</label>
                                    <input type="number" name="commission_fbp_pct"
                                        step="0.01" min="0" max="100"
                                        value="{{ old('commission_fbp_pct', $category->commission_fbp_pct ?? 0) }}"
                                        class="input w-full text-sm commission-fbp-input">
                                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.categories.percentage_rate_hint') }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.fixed_fee_per_unit') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="commission_fbp_fixed"
                                            step="1" min="0"
                                            value="{{ old('commission_fbp_fixed', $category->commission_fbp_fixed ?? 0) }}"
                                            class="input w-full text-sm commission-fbp-input">
                                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ __('admin.categories.fixed_fee_unit') }}</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.categories.fixed_fee_hint') }}</p>
                                </div>
                                <div class="bg-blue-50 rounded-lg p-2.5 text-xs text-blue-700">
                                    <strong>{{ __('admin.categories.example') }}:</strong> {{ __('admin.categories.example_calc') }}<br>
                                    <span id="fbp-preview">—</span>
                                </div>
                            </div>
                        </div>

                        {{-- FBN block --}}
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-green-600 mb-3">{{ __('admin.categories.fbn_label') }}</p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.percentage_rate') }}</label>
                                    <input type="number" name="commission_fbn_pct"
                                        step="0.01" min="0" max="100"
                                        value="{{ old('commission_fbn_pct', $category->commission_fbn_pct ?? 0) }}"
                                        class="input w-full text-sm commission-fbn-input">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.fixed_fee_per_unit') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="commission_fbn_fixed"
                                            step="1" min="0"
                                            value="{{ old('commission_fbn_fixed', $category->commission_fbn_fixed ?? 0) }}"
                                            class="input w-full text-sm commission-fbn-input">
                                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ __('admin.categories.fixed_fee_unit') }}</span>
                                    </div>
                                </div>
                                <div class="bg-green-50 rounded-lg p-2.5 text-xs text-green-700">
                                    <strong>{{ __('admin.categories.example') }}:</strong> {{ __('admin.categories.example_calc') }}<br>
                                    <span id="fbn-preview">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-400 mt-3">
                        {{ __('admin.categories.commission_formula_note') }}
                    </p>
                </div>

                <script>
                    (function () {
                        function updatePreview(type) {
                            const pct = parseFloat(document.querySelector('[name="commission_' + type + '_pct"]').value) || 0;
                            const fixed = parseInt(document.querySelector('[name="commission_' + type + '_fixed"]').value) || 0;
                            const examplePrice = 10000;
                            const exampleQty = 2;
                            const commissionPerUnit = Math.round(examplePrice * pct / 100) + fixed;
                            const total = commissionPerUnit * exampleQty;
                            document.getElementById(type + '-preview').textContent =
                                '(' + (examplePrice / 100).toFixed(2) + ' × ' + pct + '%) + ' + (fixed / 100).toFixed(2) +
                                ' = ' + (commissionPerUnit / 100).toFixed(2) + ' per unit × ' + exampleQty +
                                ' = ' + (total / 100).toFixed(2) + ' total';
                        }
                        document.addEventListener('DOMContentLoaded', function () {
                            ['fbp', 'fbn'].forEach(function (t) {
                                document.querySelectorAll('.commission-' + t + '-input')
                                    .forEach(function (el) { el.addEventListener('input', function () { updatePreview(t); }); });
                                updatePreview(t);
                            });
                        });
                    })();
                </script>

                {{-- Sort Order --}}
                <x-form.input
                    name="sort_order"
                    label="{{ __('admin.sort_order') }}"
                    type="number"
                    :value="$val('sort_order', '0')"
                    min="0"
                />
            </div>

            {{-- TAB: Attributes --}}
            <div
                x-show="activeTab === 'attributes'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm space-y-4"
            >
                <p class="text-sm text-gray-500">
                    {{ __('admin.categories.attributes_tab_hint') }}
                </p>

                @if($allAttributes->isEmpty())
                    <p class="text-sm text-gray-400 italic">{{ __('admin.categories.no_attributes_defined') }} <a href="{{ route('admin.attributes.create') }}" class="text-primary-600 underline">{{ __('admin.categories.create_attributes_first') }}</a></p>
                @else
                    <div class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                        @foreach($allAttributes as $attr)
                        @php
                            $assigned = $isEdit
                                ? $category->categoryAttributes->firstWhere('attribute_id', $attr->id)
                                : null;
                        @endphp
                        <div class="flex items-center gap-4 px-4 py-3 bg-white hover:bg-gray-50">
                            <input type="checkbox"
                                name="attributes[{{ $loop->index }}][attribute_id]"
                                value="{{ $attr->id }}"
                                id="attr_{{ $attr->id }}"
                                class="attr-assign-cb rounded border-gray-300 text-primary-600"
                                @checked($assigned !== null) />
                            <input type="hidden" name="attributes[{{ $loop->index }}][attribute_id]" value="{{ $attr->id }}" disabled />
                            <label for="attr_{{ $attr->id }}" class="flex-1 cursor-pointer">
                                <span class="text-sm font-medium text-gray-900">{{ $attr->name_en }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ $attr->code }}</span>
                                <span class="ml-1 text-xs px-1.5 py-0.5 bg-gray-100 rounded text-gray-500">{{ $attr->type?->value }}</span>
                            </label>
                            <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                                <input type="checkbox"
                                    name="attributes[{{ $loop->index }}][is_required]"
                                    value="1"
                                    class="rounded border-gray-300 text-primary-600"
                                    @checked($assigned?->is_required) />
                                {{ __('admin.required') }}
                            </label>
                            <input type="hidden" name="attributes[{{ $loop->index }}][sort_order]" value="{{ $loop->index }}" />
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- TAB: Shipping Methods --}}
            <div
                x-show="activeTab === 'shipping'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm space-y-4"
            >
                @if(!$isEdit)
                    <p class="text-sm text-gray-400 italic">{{ __('admin.categories.save_category_first_for_shipping') }}</p>
                @else
                    @include('admin.categories._shipping_methods_tab', ['category' => $category])
                @endif
            </div>

            {{-- TAB: SEO --}}
            <div
                x-show="activeTab === 'seo'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm space-y-5"
            >
                <div class="grid grid-cols-2 gap-4">
                    <x-form.input
                        name="seo_title_en"
                        label="{{ __('admin.categories.seo_title_en') }}"
                        :value="$val('seo_title_en')"
                        maxlength="70"
                        dir="ltr"
                        placeholder="{{ __('admin.categories.seo_title_placeholder') }}"
                    />
                    <x-form.input
                        name="seo_title_ar"
                        label="{{ __('admin.categories.seo_title_ar') }}"
                        :value="$val('seo_title_ar')"
                        maxlength="70"
                        dir="rtl"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-form.textarea
                        name="seo_description_en"
                        label="{{ __('admin.categories.seo_description_en') }}"
                        :value="$val('seo_description_en')"
                        rows="3"
                        maxlength="160"
                        dir="ltr"
                    />
                    <x-form.textarea
                        name="seo_description_ar"
                        label="{{ __('admin.categories.seo_description_ar') }}"
                        :value="$val('seo_description_ar')"
                        rows="3"
                        maxlength="160"
                        dir="rtl"
                    />
                </div>
            </div>

            {{-- TAB: Marketers --}}
            <div
                x-show="activeTab === 'marketers'"
                class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 shadow-sm space-y-6"
            >
                @if(!$isEdit)
                    <p class="text-sm text-gray-400 italic">{{ __('admin.categories.save_category_first_for_shipping') }}</p>
                @else
                    {{-- Commission per Country --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.categories.marketer_commission_per_country') }}</h4>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.categories.country_column_ar') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.categories.currency_column') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.categories.influencer_commission') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('admin.categories.affiliate_commission') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-500"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($activeCountries as $country)
                                        @php $setting = $marketerCommissions->get($country->id); @endphp
                                        <tr>
                                            <form method="POST" action="{{ route('admin.categories.marketer-commission.update', $category->id) }}" class="contents">
                                                @csrf
                                                <input type="hidden" name="country_id" value="{{ $country->id }}">
                                                <input type="hidden" name="currency" value="{{ $setting->currency ?? $country->currency_code }}">
                                                <td class="px-3 py-2 text-gray-900">{{ $country->name_ar }}</td>
                                                <td class="px-3 py-2 text-gray-700">{{ $setting->currency ?? $country->currency_code }}</td>
                                                <td class="px-3 py-2">
                                                    <input type="number" min="0" step="1" name="influencer_commission_amount"
                                                        value="{{ $setting->influencer_commission_amount ?? 0 }}"
                                                        class="w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input type="number" min="0" step="1" name="affiliate_commission_amount"
                                                        value="{{ $setting->affiliate_commission_amount ?? 0 }}"
                                                        class="w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                                                </td>
                                                <td class="px-3 py-2">
                                                    <button type="submit"
                                                        class="inline-flex items-center rounded-md bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                                                        {{ __('common.save') }}
                                                    </button>
                                                </td>
                                            </form>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Sample & Campaign Settings --}}
                    <div class="border-t border-gray-100 pt-5">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ __('admin.categories.sample_and_campaign_settings') }}</h4>

                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.categories.influencer_sample_qty') }}</label>
                                <input type="number" min="0" step="1" name="influencer_sample_qty"
                                    value="{{ old('influencer_sample_qty', $category->influencer_sample_qty ?? 0) }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.categories.affiliate_sample_qty') }}</label>
                                <input type="number" min="0" step="1" name="affiliate_sample_qty"
                                    value="{{ old('affiliate_sample_qty', $category->affiliate_sample_qty ?? 0) }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.categories.platform_sample_qty') }}</label>
                                <input type="number" min="0" step="1" name="platform_sample_qty"
                                    value="{{ old('platform_sample_qty', $category->platform_sample_qty ?? 0) }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.categories.min_stock_for_campaign') }}</label>
                                <input type="number" min="0" step="1" name="min_stock_for_campaign"
                                    value="{{ old('min_stock_for_campaign', $category->min_stock_for_campaign ?? 0) }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ str_replace(':button', __('admin.categories.save_changes'), __('admin.categories.settings_saved_with_button')) }}</p>
                    </div>
                @endif
            </div>

        </div>{{-- /left column --}}

        {{-- ═══════════════════════════════════════════ --}}
        {{-- RIGHT SIDEBAR                               --}}
        {{-- ═══════════════════════════════════════════ --}}
        <div class="w-full lg:w-72 flex-shrink-0 space-y-4">

            {{-- Category Image --}}
            <div
                class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3"
                x-data="{
                    uploading: false,
                    imageUrl: '{{ $isEdit ? ($category->image_url ?? '') : '' }}',
                    uploadUrl: '{{ $isEdit ? route('admin.categories.upload-image', $category->id) : '' }}',
                    deleteUrl: '{{ $isEdit ? route('admin.categories.delete-image', $category->id) : '' }}',
                    async upload(event) {
                        const file = event.target.files[0];
                        if (!file) return;
                        this.uploading = true;
                        const fd = new FormData();
                        fd.append('image', file);
                        fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                        try {
                            const res = await fetch(this.uploadUrl, { method: 'POST', body: fd });
                            const data = await res.json();
                            if (res.ok) { this.imageUrl = data.url; }
                            else { alert(data.message || 'Upload failed'); }
                        } catch(e) { alert('Network error'); }
                        this.uploading = false;
                        event.target.value = '';
                    },
                    async remove() {
                        if (!confirm('Remove image?')) return;
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
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.categories.category_image') }}</h3>

                <div class="relative rounded-lg overflow-hidden bg-gray-100 border border-dashed border-gray-300 flex items-center justify-center" style="min-height:120px">
                    <template x-if="imageUrl">
                        <img :src="imageUrl" alt="Category image" class="max-w-full max-h-48 object-contain" />
                    </template>
                    <template x-if="!imageUrl">
                        <span class="text-xs text-gray-400">{{ __('admin.categories.no_image_yet') }}</span>
                    </template>

                    <template x-if="imageUrl && uploadUrl">
                        <button
                            type="button"
                            @click="remove()"
                            class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-700"
                            title="{{ __('admin.banners.remove') }}"
                        >✕</button>
                    </template>
                </div>

                @if($isEdit)
                    <div>
                        <input
                            type="file"
                            accept="image/*"
                            @change="upload($event)"
                            :disabled="uploading"
                            class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer disabled:opacity-50"
                        />
                        <p x-show="uploading" class="text-xs text-primary-600 mt-1 animate-pulse">{{ __('admin.categories.uploading') }}…</p>
                    </div>
                    <p class="text-xs text-gray-400">{{ __('admin.categories.image_hint') }}</p>
                @else
                    <p class="text-xs text-gray-400">{{ __('admin.categories.save_category_first_for_image') }}</p>
                @endif
            </div>

            {{-- Visibility --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.visibility') }}</h3>
                <x-form.toggle name="is_active"  label="{{ __('common.active') }}"  :value="$bool('is_active', true)" />
                <x-form.toggle name="is_visible" label="{{ __('admin.is_visible') }}" :value="$bool('is_visible', true)" />
                <x-form.toggle name="is_featured" label="{{ __('admin.categories.featured_homepage') }}" :value="$bool('is_featured')" />
            </div>

            {{-- Save actions --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-2">
                @if($errors->any())
                <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-700 space-y-1">
                    @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                    @endforeach
                </div>
                @endif

                <button type="submit" id="submit-btn"
                    class="btn btn-primary w-full justify-center">
                    {{ $isEdit ? __('admin.categories.save_changes') : __('admin.categories.create_category') }}
                </button>
                <a href="{{ route('admin.categories.index') }}"
                    class="btn btn-ghost w-full justify-center text-center block">
                    {{ __('common.cancel') }}
                </a>
            </div>

        </div>{{-- /right sidebar --}}

    </div>{{-- /flex row --}}

</div>{{-- /x-data root --}}

@if($isEdit)
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    savingEllipsis: @json(__('admin.products.saving_ellipsis')),
    saveDeliveryOptions: @json(__('admin.categories.save_delivery_options')),
    saved: @json(__('admin.categories.saved_short')),
    failedLoadMethods: @json(__('admin.categories.failed_load_methods')),
    saveFailed: @json(__('admin.categories.save_failed')),
    networkError: @json(__('admin.products.network_error')),
    categorySaved: @json(__('admin.categories.category_saved')),
    validationError: @json(__('admin.products.validation_error')),
    saveFailedRetry: @json(__('admin.products.save_failed_retry')),
    saveChangesBtn: @json(__('admin.categories.save_changes_btn')),
});
</script>
@endif
