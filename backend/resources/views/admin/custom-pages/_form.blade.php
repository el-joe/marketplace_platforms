{{--
    Shared Custom Page form partial.
    Include with: @include('admin.custom-pages._form', ['mode' => 'create'])
                  @include('admin.custom-pages._form', ['mode' => 'edit', 'customPage' => $customPage, 'filterableAttributes' => $filterableAttributes])

    Parent view must wrap this in a <form> tag with proper action / @csrf / @method.
--}}
@php
    $customPage = $customPage ?? null;
    $isEdit = $customPage !== null;
    $filterableAttributes = $filterableAttributes ?? collect();

    $val = function (string $field, $default = '') use ($isEdit, $customPage) {
        return old($field, $isEdit ? ($customPage->{$field} ?? $default) : $default);
    };

    $selectedCategories = $isEdit ? $customPage->categories->map(fn ($c) => ['id' => $c->id, 'text' => $c->name_en])->values() : collect();
@endphp

<div class="space-y-6">
    <input type="hidden" id="form-mode" name="_form_mode" value="{{ $isEdit ? 'edit' : 'create' }}">
    <input type="hidden" id="selected-category-ids" data-initial="{{ $selectedCategories->toJson() }}">

    <script>
        window.ROUTES_CUSTOM_PAGE = {
            searchCategories: '{{ route('admin.page-builder.search.categories') }}',
        };
    </script>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- LEFT: general fields --}}
        <div class="flex-1 min-w-0 bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <x-form.input name="name_en" label="{{ __('admin.name_en') }}" :value="$val('name_en')" required />
                <x-form.input name="name_ar" label="{{ __('admin.name_ar') }}" :value="$val('name_ar')" required dir="rtl" />
            </div>

            <x-form.slug-input
                name="slug"
                label="{{ __('admin.custom_pages.slug') }}"
                :value="$isEdit ? $customPage->slugRecord?->slug_url : ''"
                source-field="name_en"
                :editable="true"
                required
                help-text="{{ __('admin.custom_pages.slug_help') }}"
            />

            <div class="grid grid-cols-2 gap-4">
                <x-form.textarea name="description_en" label="{{ __('admin.description_en') }}" :value="$val('description_en')" rows="3" />
                <x-form.textarea name="description_ar" label="{{ __('admin.description_ar') }}" :value="$val('description_ar')" rows="3" dir="rtl" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-form.input name="sort_order" type="number" label="{{ __('admin.sort_order') }}" :value="$val('sort_order', 0)" />
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2">
                <x-form.toggle name="is_active" label="{{ __('admin.is_active') }}" :checked="$isEdit ? $customPage->is_active : true" />
                <x-form.toggle name="has_filters" label="{{ __('admin.categories.has_filters') }}"
                    :checked="$isEdit ? $customPage->has_filters : false"
                    help-text="{{ __('admin.categories.has_filters_hint') }}" />
            </div>

            <hr class="border-gray-100">

            <div class="grid grid-cols-2 gap-4">
                <x-form.input name="seo_title_en" label="{{ __('admin.categories.seo_title_en') }}" :value="$val('seo_title_en')" />
                <x-form.input name="seo_title_ar" label="{{ __('admin.categories.seo_title_ar') }}" :value="$val('seo_title_ar')" dir="rtl" />
                <x-form.textarea name="seo_description_en" label="{{ __('admin.categories.seo_description_en') }}" :value="$val('seo_description_en')" rows="2" />
                <x-form.textarea name="seo_description_ar" label="{{ __('admin.categories.seo_description_ar') }}" :value="$val('seo_description_ar')" rows="2" dir="rtl" />
            </div>
        </div>

        {{-- RIGHT: category picker + inherited filters --}}
        <div class="w-full lg:w-96 shrink-0 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                    {{ __('admin.custom_pages.categories') }}
                </h4>
                <p class="text-xs text-gray-400 mb-3">{{ __('admin.custom_pages.categories_help') }}</p>

                <div class="relative mb-3">
                    <input type="search" id="custom-page-category-search" autocomplete="off"
                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        placeholder="{{ __('admin.custom_pages.search_categories_placeholder') }}" />
                    <x-heroicon name="magnifying-glass" class="w-4 h-4 absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                </div>

                <div id="custom-page-category-results"
                    class="hidden mb-2 rounded-lg border border-gray-200 bg-white shadow-sm text-sm divide-y divide-gray-100 max-h-48 overflow-y-auto"></div>

                <div id="custom-page-category-list" class="space-y-1 text-sm text-gray-500">
                    <div class="text-xs text-gray-400 px-2 py-3 text-center">{{ __('admin.custom_pages.no_categories_yet') }}</div>
                </div>
            </div>

            @if($isEdit)
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                    {{ __('admin.custom_pages.inherited_filters') }}
                </h4>
                <p class="text-xs text-gray-400 mb-3">{{ __('admin.custom_pages.inherited_filters_help') }}</p>
                <div class="space-y-1 text-sm text-gray-600">
                    @forelse($filterableAttributes as $attribute)
                        <div class="px-2 py-1 rounded bg-gray-50">{{ $attribute->name_en }}</div>
                    @empty
                        <div class="text-xs text-gray-400 px-2 py-3 text-center">{{ __('admin.custom_pages.no_filters_yet') }}</div>
                    @endforelse
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.custom-pages.index') }}" class="btn btn-ghost btn-sm">{{ __('admin.cancel') }}</a>
        <button type="submit" id="submit-btn" class="btn btn-primary btn-sm">
            {{ $isEdit ? __('admin.save_changes') : __('admin.custom_pages.create_title') }}
        </button>
    </div>
</div>
