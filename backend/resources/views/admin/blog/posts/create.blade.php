@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/rich-editor.js', 'resources/js/components/file-upload.js'])
@endpush

@section('title', __('admin.blog.new_blog_post'))

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.blog.new_blog_post') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.blog.fill_content_below') }}</p>
        </div>
        <a href="{{ route('admin.blog.posts.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.blog.back_to_posts') }}</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    <form id="post-form" method="POST" action="{{ route('admin.blog.posts.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="action" id="form-action" value="draft">

        <div class="flex flex-col lg:flex-row gap-6 items-start">

            {{-- ────────────── LEFT COLUMN (70%) ────────────── --}}
            <div class="flex-1 min-w-0 space-y-5">

                {{-- Content section --}}
                <x-card title="{{ __('admin.blog.title') }}">
                    <div class="p-6 space-y-5">

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ __('admin.blog.title_en') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="title_en" id="title-en" required maxlength="255"
                                       value="{{ old('title_en') }}"
                                       class="form-input w-full @error('title_en') is-invalid @enderror"
                                       placeholder="{{ __('admin.blog.title_placeholder') }}">
                                @error('title_en') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">{{ __('admin.blog.title_ar') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="title_ar" id="title-ar" required maxlength="255" dir="rtl"
                                       value="{{ old('title_ar') }}"
                                       class="form-input w-full @error('title_ar') is-invalid @enderror"
                                       placeholder="{{ __('admin.blog.title_ar_placeholder') }}">
                                @error('title_ar') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="form-label">{{ __('admin.blog.slug') }}</label>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 whitespace-nowrap">portal.com/blog/</span>
                                <input type="text" name="slug" id="slug-input" maxlength="255"
                                       value="{{ old('slug') }}"
                                       class="form-input flex-1 text-sm font-mono @error('slug') is-invalid @enderror"
                                       placeholder="{{ __('admin.blog.slug_auto_generated') }}">
                            </div>
                            @error('slug') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <label class="form-label">{{ __('admin.blog.excerpt_en') }}</label>
                                    <span class="text-xs text-gray-400" data-char-counter="excerpt_en" data-max="300">0 / 300</span>
                                </div>
                                <textarea name="excerpt_en" id="excerpt_en" rows="3" maxlength="300"
                                          class="form-textarea w-full @error('excerpt_en') is-invalid @enderror"
                                          placeholder="{{ __('admin.blog.excerpt_placeholder_en') }}">{{ old('excerpt_en') }}</textarea>
                                @error('excerpt_en') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <label class="form-label">{{ __('admin.blog.excerpt_ar') }}</label>
                                    <span class="text-xs text-gray-400" data-char-counter="excerpt_ar" data-max="300">0 / 300</span>
                                </div>
                                <textarea name="excerpt_ar" id="excerpt_ar" rows="3" maxlength="300" dir="rtl"
                                          class="form-textarea w-full @error('excerpt_ar') is-invalid @enderror"
                                          placeholder="{{ __('admin.blog.excerpt_placeholder_ar') }}">{{ old('excerpt_ar') }}</textarea>
                                @error('excerpt_ar') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- EN / AR body tabs --}}
                        <div>
                            <div class="flex border-b border-gray-200 mb-4">
                                <button type="button" class="lang-tab px-4 py-2 text-sm font-medium border-b-2 border-primary-500 text-primary-600 -mb-px" data-lang="en">
                                    {{ __('common.english') }}
                                </button>
                                <button type="button" class="lang-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 -mb-px" data-lang="ar">
                                    {{ __('common.arabic') }}
                                </button>
                            </div>

                            <div id="body-en-section">
                                <x-form.rich-editor
                                    name="body_en"
                                    label="{{ __('admin.blog.body_en') }}"
                                    :required="true"
                                    profile="full"
                                    :minHeight="350"
                                    :value="old('body_en', '')"
                                />
                                @error('body_en') <p class="form-error">{{ $message }}</p> @enderror
                            </div>

                            <div id="body-ar-section" class="hidden">
                                <x-form.rich-editor
                                    name="body_ar"
                                    label="{{ __('admin.blog.body_ar') }}"
                                    :required="true"
                                    profile="full"
                                    :minHeight="350"
                                    :value="old('body_ar', '')"
                                />
                                @error('body_ar') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="form-label">{{ __('admin.blog.tags') }}</label>
                            <input type="text" name="tags" id="tags-input"
                                   value="{{ old('tags') }}"
                                   class="form-input w-full text-sm"
                                   placeholder="{{ __('admin.blog.tags_placeholder') }}">
                            <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.blog.tags_hint') }}</p>
                        </div>
                    </div>
                </x-card>

                {{-- Attachments --}}
                <x-card title="{{ __('admin.blog.attachments') }}">
                    <div class="p-4 space-y-4">
                        <div>
                            <label class="form-label text-xs">{{ __('admin.blog.add_attachments') }}</label>
                            <input type="file" name="attachments[]" multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.png,.jpg,.jpeg"
                                   class="form-input w-full text-sm">
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.blog.attachments_hint') }}</p>
                            @error('attachments.*') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </x-card>

                {{-- Bottom action buttons --}}
                <div class="flex items-center gap-3">
                    <button type="button" class="btn btn-secondary" onclick="submitForm('draft')">{{ __('admin.blog.save_draft') }}</button>
                    <button type="button" class="btn bg-emerald-600 text-white hover:bg-emerald-700" onclick="submitForm('publish')">{{ __('admin.blog.publish_now') }}</button>
                </div>

            </div>

            {{-- ────────────── RIGHT COLUMN (30%) ────────────── --}}
            <div class="w-full lg:w-80 flex-shrink-0 space-y-4">

                {{-- Publish Settings --}}
                <x-card title="{{ __('admin.blog.publish_settings') }}">
                    <div class="p-4 space-y-4">
                        <div class="flex flex-col gap-2">
                            <button type="button" class="btn btn-secondary w-full text-sm" onclick="submitForm('draft')">
                                {{ __('admin.blog.save_draft') }}
                            </button>
                            <button type="button" id="btn-schedule" class="btn btn-secondary w-full text-sm text-blue-600 border-blue-300">
                                {{ __('admin.blog.schedule') }}…
                            </button>
                            <button type="button" class="btn bg-emerald-600 text-white hover:bg-emerald-700 w-full text-sm" onclick="submitForm('publish')">
                                {{ __('admin.blog.publish_now') }}
                            </button>
                        </div>

                        <div id="schedule-panel" class="hidden space-y-2 pt-2 border-t border-gray-100">
                            <label class="form-label text-xs">{{ __('admin.blog.scheduled_for') }}</label>
                            <input type="datetime-local" name="scheduled_for" id="scheduled-for"
                                   value="{{ old('scheduled_for') }}"
                                   class="form-input w-full text-sm">
                            <button type="button" class="btn btn-primary w-full text-sm" onclick="submitForm('schedule')">
                                {{ __('admin.blog.set_schedule') }}
                            </button>
                        </div>

                        <div class="pt-2 border-t border-gray-100">
                            <div class="text-xs text-gray-500">{{ __('admin.blog.reading_time') }}: <span id="reading-time-display" class="font-medium text-gray-700">~1 min read</span></div>
                        </div>
                    </div>
                </x-card>

                {{-- Organization --}}
                <x-card title="{{ __('admin.blog.organization') }}">
                    <div class="p-4 space-y-4">
                        <div>
                            <label class="form-label text-xs">{{ __('admin.blog.category_label') }} <span class="text-red-500">*</span></label>
                            <select name="blog_category_id" class="form-input w-full text-sm @error('blog_category_id') is-invalid @enderror">
                                <option value="">{{ __('admin.blog.select_category') }}</option>
                                @foreach($categories as $parent)
                                    <optgroup label="{{ $parent->name_en }}">
                                        <option value="{{ $parent->id }}" {{ old('blog_category_id') == $parent->id ? 'selected' : '' }}>
                                            {{ $parent->name_en }}
                                        </option>
                                        @foreach($parent->children as $child)
                                            <option value="{{ $child->id }}" {{ old('blog_category_id') == $child->id ? 'selected' : '' }}>
                                                ↳ {{ $child->name_en }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('blog_category_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="form-label text-xs">{{ __('common.country') }}</label>
                            <select name="country_id" class="form-input w-full text-sm">
                                <option value="">{{ __('common.all') }} {{ __('common.country') }}</option>
                                @foreach($countries as $c)
                                    <option value="{{ $c->id }}" {{ old('country_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label text-xs">{{ __('admin.blog.author') }}</label>
                            <select name="author_admin_id" class="form-input w-full text-sm">
                                @foreach($authors as $a)
                                    <option value="{{ $a->id }}" {{ old('author_admin_id', auth('admin')->id()) == $a->id ? 'selected' : '' }}>
                                        {{ $a->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="allow_comments" value="1"
                                   class="rounded text-primary-600"
                                   {{ old('allow_comments', '1') ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700">{{ __('admin.blog.allow_comments') }}</span>
                        </label>
                    </div>
                </x-card>

                {{-- Featured Image --}}
                <x-card title="{{ __('admin.blog.featured_image') }}">
                    <div class="p-4 space-y-4">
                        <x-form.file-upload
                            name="featured_image"
                            label="{{ __('admin.blog.featured_image') }}"
                            accept="image/*"
                            :maxSizeMb="5"
                        />
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="form-label text-xs">{{ __('admin.blog.alt_text_en') }}</label>
                                <input type="text" name="featured_image_alt_en" value="{{ old('featured_image_alt_en') }}"
                                       class="form-input w-full text-sm" placeholder="{{ __('admin.blog.alt_text_en_placeholder') }}">
                            </div>
                            <div>
                                <label class="form-label text-xs">{{ __('admin.blog.alt_text_ar') }}</label>
                                <input type="text" name="featured_image_alt_ar" value="{{ old('featured_image_alt_ar') }}" dir="rtl"
                                       class="form-input w-full text-sm">
                            </div>
                        </div>
                        <div class="border-t border-gray-100 pt-3">
                            <x-form.file-upload
                                name="og_image"
                                label="{{ __('admin.blog.social_share_optional') }}"
                                accept="image/*"
                                :maxSizeMb="5"
                            />
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.blog.uses_featured_if_not_set') }}</p>
                        </div>
                    </div>
                </x-card>

                {{-- SEO --}}
                <x-card title="{{ __('admin.blog.seo_section_title') }}">
                    <div class="p-4 space-y-4">
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="form-label text-xs">{{ __('admin.blog.seo_title') }} (EN)</label>
                                <span class="text-xs text-gray-400" data-char-counter="seo_title_en" data-max="60">0 / 60</span>
                            </div>
                            <input type="text" name="seo_title_en" id="seo-title-en" maxlength="200"
                                   value="{{ old('seo_title_en') }}"
                                   class="form-input w-full text-sm">
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="form-label text-xs">{{ __('admin.blog.seo_title') }} (AR)</label>
                                <span class="text-xs text-gray-400" data-char-counter="seo_title_ar" data-max="60">0 / 60</span>
                            </div>
                            <input type="text" name="seo_title_ar" maxlength="200" dir="rtl"
                                   value="{{ old('seo_title_ar') }}"
                                   class="form-input w-full text-sm">
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="form-label text-xs">{{ __('admin.blog.seo_description') }} (EN)</label>
                                <span class="text-xs text-gray-400" data-char-counter="seo_description_en" data-max="160">0 / 160</span>
                            </div>
                            <textarea name="seo_description_en" id="seo-desc-en" rows="2" maxlength="500"
                                      class="form-textarea w-full text-sm">{{ old('seo_description_en') }}</textarea>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="form-label text-xs">{{ __('admin.blog.seo_description') }} (AR)</label>
                                <span class="text-xs text-gray-400" data-char-counter="seo_description_ar" data-max="160">0 / 160</span>
                            </div>
                            <textarea name="seo_description_ar" rows="2" maxlength="500" dir="rtl"
                                      class="form-textarea w-full text-sm">{{ old('seo_description_ar') }}</textarea>
                        </div>

                        {{-- Google snippet preview --}}
                        <div class="border border-gray-200 rounded-lg p-3 bg-gray-50 space-y-0.5">
                            <p class="text-xs text-gray-400 mb-2 font-medium uppercase">{{ __('admin.blog.seo_preview') }}</p>
                            <div id="seo-preview-title" class="text-blue-700 text-sm font-medium leading-snug truncate">{{ __('admin.blog.seo_title_placeholder') }}</div>
                            <div class="text-green-700 text-xs truncate">portal.com/blog/<span id="seo-preview-slug">slug</span></div>
                            <div id="seo-preview-desc" class="text-gray-500 text-xs leading-relaxed line-clamp-2">{{ __('admin.blog.seo_desc_placeholder') }}</div>
                        </div>
                    </div>
                </x-card>

            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    seo_title_placeholder: @json(__('admin.blog.seo_title_placeholder')),
    seo_desc_placeholder: @json(__('admin.blog.seo_desc_placeholder')),
});
(function () {
    // ── Slug auto-generate ────────────────────────────────────────────────────
    const titleEn  = document.getElementById('title-en');
    const slugInput = document.getElementById('slug-input');
    let slugManual  = false;

    slugInput.addEventListener('input', () => { slugManual = true; });
    titleEn.addEventListener('input', () => {
        if (!slugManual) {
            slugInput.value = titleEn.value.toLowerCase().trim()
                .replace(/[^\w\s-]/g, '').replace(/[\s_]+/g, '-').replace(/^-+|-+$/g, '');
            document.getElementById('seo-preview-slug').textContent = slugInput.value || 'slug';
        }
    });

    // ── SEO preview ───────────────────────────────────────────────────────────
    const seoTitle = document.getElementById('seo-title-en');
    const seoDesc  = document.getElementById('seo-desc-en');
    seoTitle.addEventListener('input', () => {
        document.getElementById('seo-preview-title').textContent = seoTitle.value || window.TRANSLATIONS.seo_title_placeholder;
    });
    seoDesc.addEventListener('input', () => {
        document.getElementById('seo-preview-desc').textContent = seoDesc.value || window.TRANSLATIONS.seo_desc_placeholder;
    });
    slugInput.addEventListener('input', () => {
        document.getElementById('seo-preview-slug').textContent = slugInput.value || 'slug';
    });

    // ── Lang tabs ─────────────────────────────────────────────────────────────
    document.querySelectorAll('.lang-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const lang = tab.dataset.lang;
            document.querySelectorAll('.lang-tab').forEach(t => {
                t.classList.toggle('border-primary-500', t === tab);
                t.classList.toggle('text-primary-600', t === tab);
                t.classList.toggle('border-transparent', t !== tab);
                t.classList.toggle('text-gray-500', t !== tab);
            });
            document.getElementById('body-en-section').classList.toggle('hidden', lang !== 'en');
            document.getElementById('body-ar-section').classList.toggle('hidden', lang !== 'ar');
        });
    });

    // ── Schedule panel toggle ─────────────────────────────────────────────────
    document.getElementById('btn-schedule').addEventListener('click', () => {
        document.getElementById('schedule-panel').classList.toggle('hidden');
    });

    // ── Reading time estimate ─────────────────────────────────────────────────
    function estimateReadingTime() {
        const bodyEl = document.getElementById('body_en');
        if (!bodyEl) return;
        const text = bodyEl.value.replace(/<[^>]+>/g, ' ');
        const words = text.trim().split(/\s+/).filter(Boolean).length;
        const mins = Math.max(1, Math.ceil(words / 200));
        document.getElementById('reading-time-display').textContent = `~${mins} min read`;
    }
    const bodyEnEl = document.getElementById('body_en');
    if (bodyEnEl) {
        bodyEnEl.addEventListener('input', () => { clearTimeout(bodyEnEl._rt); bodyEnEl._rt = setTimeout(estimateReadingTime, 600); });
    }

    // ── Char counters ─────────────────────────────────────────────────────────
    document.querySelectorAll('[data-char-counter]').forEach(counter => {
        const fieldName = counter.dataset.charCounter;
        const max = parseInt(counter.dataset.max);
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        const update = () => { counter.textContent = `${field.value.length} / ${max}`; };
        field.addEventListener('input', update);
        update();
    });

    // ── Form submit helper ────────────────────────────────────────────────────
    window.submitForm = function (action) {
        document.getElementById('form-action').value = action;
        document.getElementById('post-form').submit();
    };
})();
</script>
@endpush
@endsection
