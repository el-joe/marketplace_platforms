{{-- Shared create/edit form. Expects: $page (?StaticPage), $copyFrom (?StaticPage, create pre-fill), $audiences, $footerGroups, $countries. --}}
@php
    $isEdit = (bool) $page;
    $source = $page ?? $copyFrom ?? null;
    $val = fn (string $field, $default = null) => old($field, $source?->{$field} ?? $default);
    $showInFooter = (bool) old('show_in_footer', $source?->show_in_footer ?? false);
    $isActive = (bool) old('is_active', $page?->is_active ?? true);
@endphp

<form id="static-page-form" method="POST"
      action="{{ $isEdit ? route('admin.static-pages.update', $page->id) : route('admin.static-pages.store') }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- MAIN COLUMN --}}
        <div class="flex-1 min-w-0 space-y-5">
            <x-card title="{{ __('admin.static_pages.content') }}">
                <div class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">{{ __('admin.static_pages.page_title') }} ({{ __('common.english') }}) <span class="text-red-500">*</span></label>
                            <input type="text" name="title_en" id="title-en-input" required maxlength="255" dir="ltr"
                                   value="{{ $val('title_en') }}"
                                   class="form-input w-full @error('title_en') is-invalid @enderror">
                            @error('title_en') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.static_pages.page_title') }} ({{ __('common.arabic') }}) <span class="text-red-500">*</span></label>
                            <input type="text" name="title_ar" required maxlength="255" dir="rtl"
                                   value="{{ $val('title_ar') }}"
                                   class="form-input w-full @error('title_ar') is-invalid @enderror">
                            @error('title_ar') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.static_pages.slug') }}</label>
                        <input type="text" name="slug" id="slug-input" maxlength="150" dir="ltr"
                               value="{{ $val('slug') }}"
                               class="form-input w-full text-sm font-mono @error('slug') is-invalid @enderror">
                        @error('slug')
                            <p class="form-error">{{ $message }}</p>
                        @else
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.static_pages.slug_hint') }}</p>
                        @enderror
                    </div>

                    <x-form.rich-editor
                        name="content_en"
                        label="{{ __('admin.static_pages.content') }} ({{ __('common.english') }})"
                        profile="full"
                        :minHeight="360"
                        :value="old('content_en', $source?->content_en ?? '')"
                    />

                    <div dir="rtl">
                        <x-form.rich-editor
                            name="content_ar"
                            label="{{ __('admin.static_pages.content') }} ({{ __('common.arabic') }})"
                            profile="full"
                            :minHeight="360"
                            :value="old('content_ar', $source?->content_ar ?? '')"
                        />
                    </div>
                </div>
            </x-card>

            <x-card title="{{ __('admin.static_pages.seo') }}">
                <div class="p-6 space-y-4">
                    <p class="text-xs text-gray-400">{{ __('admin.static_pages.seo_hint') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">{{ __('admin.static_pages.seo_title') }} ({{ __('common.english') }})</label>
                            <input type="text" name="seo_title_en" maxlength="255" dir="ltr" value="{{ $val('seo_title_en') }}"
                                   class="form-input w-full @error('seo_title_en') is-invalid @enderror">
                            @error('seo_title_en') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.static_pages.seo_title') }} ({{ __('common.arabic') }})</label>
                            <input type="text" name="seo_title_ar" maxlength="255" dir="rtl" value="{{ $val('seo_title_ar') }}"
                                   class="form-input w-full @error('seo_title_ar') is-invalid @enderror">
                            @error('seo_title_ar') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.static_pages.seo_description') }} ({{ __('common.english') }})</label>
                            <textarea name="seo_description_en" rows="3" maxlength="500" dir="ltr"
                                      class="form-textarea w-full @error('seo_description_en') is-invalid @enderror">{{ $val('seo_description_en') }}</textarea>
                            @error('seo_description_en') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.static_pages.seo_description') }} ({{ __('common.arabic') }})</label>
                            <textarea name="seo_description_ar" rows="3" maxlength="500" dir="rtl"
                                      class="form-textarea w-full @error('seo_description_ar') is-invalid @enderror">{{ $val('seo_description_ar') }}</textarea>
                            @error('seo_description_ar') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- SIDE COLUMN --}}
        <div class="w-full lg:w-80 flex-shrink-0 space-y-4">
            <x-card title="{{ __('admin.static_pages.settings') }}">
                <div class="p-4 space-y-4">
                    @if($copyFrom)
                        <p class="text-xs rounded-lg bg-primary-50 text-primary-700 px-3 py-2">{{ __('admin.static_pages.copy_hint', ['title' => $copyFrom->title_en]) }}</p>
                    @endif

                    <div>
                        <label class="form-label text-xs">{{ __('admin.static_pages.country') }}</label>
                        <select name="country_id" class="form-input w-full text-sm @error('country_id') is-invalid @enderror">
                            <option value="">{{ __('admin.static_pages.all_countries') }}</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}" @selected((string) old('country_id', $page?->country_id) === (string) $country->id)>{{ trim(($country->flag_emoji ?? '').' '.$country->name_en) }}{{ $country->is_active ? '' : ' ('.__('common.inactive').')' }}</option>
                            @endforeach
                        </select>
                        @error('country_id')
                            <p class="form-error">{{ $message }}</p>
                        @else
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.static_pages.country_hint') }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label text-xs">{{ __('admin.static_pages.audience') }} <span class="text-red-500">*</span></label>
                        <select name="audience" class="form-input w-full text-sm @error('audience') is-invalid @enderror">
                            @foreach($audiences as $audience)
                                <option value="{{ $audience }}" @selected($val('audience', \App\Models\StaticPage::AUDIENCE_BOTH) === $audience)>{{ __('admin.static_pages.audiences.'.$audience) }}</option>
                            @endforeach
                        </select>
                        @error('audience') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded text-primary-600" @checked($isActive)>
                        <span class="text-sm text-gray-700">{{ __('admin.static_pages.is_active') }}</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="hidden" name="show_in_footer" value="0">
                        <input type="checkbox" name="show_in_footer" value="1" class="rounded text-primary-600" @checked($showInFooter)>
                        <span class="text-sm text-gray-700">{{ __('admin.static_pages.show_in_footer') }}</span>
                    </label>

                    <div>
                        <label class="form-label text-xs">{{ __('admin.static_pages.footer_group') }}</label>
                        <select name="footer_group" class="form-input w-full text-sm @error('footer_group') is-invalid @enderror">
                            <option value="">{{ __('admin.static_pages.no_footer_group') }}</option>
                            @foreach($footerGroups as $group)
                                <option value="{{ $group }}" @selected($val('footer_group') === $group)>{{ __('admin.static_pages.footer_groups.'.$group) }}</option>
                            @endforeach
                        </select>
                        @error('footer_group') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label text-xs">{{ __('admin.static_pages.sort_order') }}</label>
                        <input type="number" name="sort_order" min="0" max="65535" value="{{ $val('sort_order', 0) }}"
                               class="form-input w-full text-sm @error('sort_order') is-invalid @enderror">
                        @error('sort_order') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </x-card>

            <x-card>
                <div class="p-4 space-y-2">
                    <button type="submit" class="btn btn-primary w-full text-sm">{{ __('common.save') }}</button>
                    @if($isEdit && $page->is_active && $page->showsOn(\App\Models\StaticPage::AUDIENCE_PORTAL))
                        <a href="{{ route('portal.pages.show', $page->slug) }}" target="_blank" rel="noopener" class="btn btn-secondary w-full text-sm">{{ __('admin.static_pages.view_portal') }}</a>
                    @endif
                    @if($isEdit && $page->updated_at)
                        <p class="text-xs text-gray-400 text-center pt-1">{{ __('admin.static_pages.last_updated', ['date' => admin_tz($page->updated_at)?->format('d M Y H:i')]) }}{{ $page->updatedByAdmin ? ' · '.$page->updatedByAdmin->name : '' }}</p>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const titleInput = document.getElementById('title-en-input');
    const slugInput = document.getElementById('slug-input');
    if (!titleInput || !slugInput) return;
    // Only auto-fill on a new page; never rewrite an existing page's URL behind the admin's back.
    let slugManual = {{ $isEdit ? 'true' : 'false' }} || slugInput.value !== '';

    slugInput.addEventListener('input', () => { slugManual = slugInput.value !== ''; });
    titleInput.addEventListener('input', () => {
        if (!slugManual) {
            slugInput.value = titleInput.value.toLowerCase().trim()
                .replace(/[^a-z0-9\s-]/g, '').replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '');
        }
    });
})();
</script>
@endpush
