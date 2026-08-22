@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/rich-editor.js'])
@endpush

@section('title', $article->title)

@section('content')
<div class="p-6">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $article->title }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.helpcenter.fill_content_below') }}</p>
        </div>
        <a href="{{ route('admin.helpcenter.articles.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.helpcenter.back_to_articles') }}</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    <form id="article-form" method="POST" action="{{ route('admin.helpcenter.articles.update', $article->id) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="action" id="form-action" value="{{ $article->status->value === 'published' ? 'publish' : 'draft' }}">

        <div class="flex flex-col lg:flex-row gap-6 items-start">

            {{-- LEFT COLUMN --}}
            <div class="flex-1 min-w-0 space-y-5">
                <x-card title="{{ __('admin.helpcenter.title') }}">
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ __('admin.helpcenter.article_title') }} ({{ __('common.english') }}) <span class="text-red-500">*</span></label>
                                <input type="text" name="title_en" id="title-input" required maxlength="255"
                                       value="{{ old('title_en', $article->title_en) }}"
                                       class="form-input w-full @error('title_en') is-invalid @enderror">
                                @error('title_en') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">{{ __('admin.helpcenter.article_title') }} ({{ __('common.arabic') }}) <span class="text-red-500">*</span></label>
                                <input type="text" name="title_ar" required maxlength="255" dir="rtl"
                                       value="{{ old('title_ar', $article->title_ar) }}"
                                       class="form-input w-full @error('title_ar') is-invalid @enderror">
                                @error('title_ar') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="form-label">{{ __('admin.helpcenter.slug') }}</label>
                            <input type="text" name="slug" id="slug-input" maxlength="255"
                                   value="{{ old('slug', $article->slug) }}"
                                   class="form-input w-full text-sm font-mono @error('slug') is-invalid @enderror">
                            @error('slug') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <label class="form-label">{{ __('admin.helpcenter.excerpt') }} ({{ __('common.english') }})</label>
                                    <span class="text-xs text-gray-400" data-char-counter="excerpt_en" data-max="500">0 / 500</span>
                                </div>
                                <textarea name="excerpt_en" id="excerpt" rows="2" maxlength="500"
                                          class="form-textarea w-full @error('excerpt_en') is-invalid @enderror">{{ old('excerpt_en', $article->excerpt_en) }}</textarea>
                                @error('excerpt_en') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <label class="form-label">{{ __('admin.helpcenter.excerpt') }} ({{ __('common.arabic') }})</label>
                                    <span class="text-xs text-gray-400" data-char-counter="excerpt_ar" data-max="500">0 / 500</span>
                                </div>
                                <textarea name="excerpt_ar" rows="2" maxlength="500" dir="rtl"
                                          class="form-textarea w-full @error('excerpt_ar') is-invalid @enderror">{{ old('excerpt_ar', $article->excerpt_ar) }}</textarea>
                                @error('excerpt_ar') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <x-form.rich-editor
                                name="body_en"
                                label="{{ __('admin.helpcenter.body') }} ({{ __('common.english') }})"
                                :required="true"
                                profile="full"
                                :minHeight="400"
                                :value="old('body_en', $article->body_en)"
                            />
                            @error('body_en') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <x-form.rich-editor
                                name="body_ar"
                                label="{{ __('admin.helpcenter.body') }} ({{ __('common.arabic') }})"
                                :required="true"
                                profile="full"
                                :minHeight="400"
                                :value="old('body_ar', $article->body_ar)"
                            />
                            @error('body_ar') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </x-card>

                <div class="flex items-center gap-3">
                    <button type="button" class="btn btn-secondary" onclick="submitForm('draft')">{{ __('admin.helpcenter.save_draft') }}</button>
                    <button type="button" class="btn bg-emerald-600 text-white hover:bg-emerald-700" onclick="submitForm('publish')">{{ __('admin.helpcenter.publish_now') }}</button>
                </div>
            </div>

            {{-- RIGHT COLUMN --}}
            <div class="w-full lg:w-80 flex-shrink-0 space-y-4">
                <x-card title="{{ __('admin.helpcenter.publish_settings') }}">
                    <div class="p-4 space-y-2">
                        <div class="text-xs text-gray-500 mb-2">
                            {{ __('common.status') }}:
                            <span class="font-medium {{ $article->status->value === 'published' ? 'text-emerald-600' : 'text-gray-600' }}">
                                {{ $article->status->label() }}
                            </span>
                        </div>
                        <button type="button" class="btn btn-secondary w-full text-sm" onclick="submitForm('draft')">{{ __('admin.helpcenter.save_draft') }}</button>
                        <button type="button" class="btn bg-emerald-600 text-white hover:bg-emerald-700 w-full text-sm" onclick="submitForm('publish')">{{ __('admin.helpcenter.publish_now') }}</button>
                    </div>
                </x-card>

                <x-card title="{{ __('admin.helpcenter.organization') }}">
                    <div class="p-4 space-y-4">
                        <div>
                            <label class="form-label text-xs">{{ __('admin.helpcenter.category_label') }} <span class="text-red-500">*</span></label>
                            <select name="help_center_category_id" class="form-input w-full text-sm @error('help_center_category_id') is-invalid @enderror">
                                <option value="">{{ __('admin.helpcenter.select_category') }}</option>
                                @foreach($categories as $parent)
                                    <optgroup label="{{ $parent->name }}">
                                        <option value="{{ $parent->id }}" {{ old('help_center_category_id', $article->help_center_category_id) == $parent->id ? 'selected' : '' }}>
                                            {{ $parent->name }}
                                        </option>
                                        @foreach($parent->children as $child)
                                            <option value="{{ $child->id }}" {{ old('help_center_category_id', $article->help_center_category_id) == $child->id ? 'selected' : '' }}>
                                                ↳ {{ $child->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('help_center_category_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label text-xs">{{ __('admin.helpcenter.country') }}</label>
                            <select name="country_id" class="form-input w-full text-sm">
                                <option value="">{{ __('admin.helpcenter.all_countries') }}</option>
                                @foreach($countries as $c)
                                    <option value="{{ $c->site_code }}" {{ old('country_id', $article->country_id) == $c->site_code ? 'selected' : '' }}>{{ $c->flag_emoji }} {{ $c->name_en }}</option>
                                @endforeach
                            </select>
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="is_featured" value="1" class="rounded text-primary-600" {{ old('is_featured', $article->is_featured) ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700">{{ __('admin.helpcenter.is_featured') }}</span>
                        </label>
                        <p class="text-xs text-gray-400">{{ __('admin.helpcenter.is_featured_hint') }}</p>
                    </div>
                </x-card>

                <x-card title="{{ __('admin.helpcenter.related_articles') }}">
                    <div class="p-4 space-y-2">
                        <select name="related_article_ids[]" multiple size="6" class="form-input w-full text-sm">
                            @foreach($allArticles as $a)
                                @continue($a->id === $article->id)
                                <option value="{{ $a->id }}" {{ in_array($a->id, old('related_article_ids', $article->related_article_ids ?? [])) ? 'selected' : '' }}>{{ $a->title }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400">{{ __('admin.helpcenter.related_articles_hint') }}</p>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const titleInput = document.getElementById('title-input');
    const slugInput = document.getElementById('slug-input');
    let slugManual = true;

    slugInput.addEventListener('input', () => { slugManual = true; });
    titleInput.addEventListener('input', () => {
        if (!slugManual) {
            slugInput.value = titleInput.value.toLowerCase().trim()
                .replace(/[^\w\s-]/g, '').replace(/[\s_]+/g, '-').replace(/^-+|-+$/g, '');
        }
    });

    document.querySelectorAll('[data-char-counter]').forEach(counter => {
        const fieldName = counter.dataset.charCounter;
        const max = parseInt(counter.dataset.max);
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        const update = () => { counter.textContent = `${field.value.length} / ${max}`; };
        field.addEventListener('input', update);
        update();
    });

    window.submitForm = function (action) {
        document.getElementById('form-action').value = action;
        document.getElementById('article-form').submit();
    };
})();
</script>
@endpush
@endsection
