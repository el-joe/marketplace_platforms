@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/rich-editor.js', 'resources/js/components/file-upload.js'])
@endpush

@section('title', __('common.edit') . ': ' . $post->title_en)

@section('content')
<div class="p-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 truncate max-w-2xl">{{ $post->title_en }}</h1>
            <div class="flex items-center gap-3 mt-1">
                @php
                    $statusColors = [
                        'draft'     => 'bg-gray-100 text-gray-600',
                        'scheduled' => 'bg-blue-100 text-blue-700',
                        'published' => 'bg-emerald-100 text-emerald-700',
                        'archived'  => 'bg-amber-100 text-amber-700',
                    ];
                    $statusPostLabels = [
                        'draft'     => __('common.draft'),
                        'scheduled' => __('common.scheduled'),
                        'published' => __('common.published'),
                        'archived'  => __('common.archived'),
                    ];
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$post->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                    {{ $statusPostLabels[$post->status->value] ?? $post->status->label() }}
                </span>
                @if($post->published_at)
                    <span class="text-xs text-gray-400">{{ __('admin.blog.published_at') }} {{ $post->published_at->format('d M Y') }}</span>
                @endif
                <span class="text-xs text-gray-400">{{ number_format($post->views_count) }} {{ __('admin.blog.views') }}</span>
            </div>
        </div>
        <a href="{{ route('admin.blog.posts.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.blog.back_to_posts') }}</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="post-form" method="POST" action="{{ route('admin.blog.posts.update', $post->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" name="action" id="form-action" value="draft">

        <div class="flex flex-col lg:flex-row gap-6 items-start">

            {{-- ────────────── LEFT COLUMN (70%) ────────────── --}}
            <div class="flex-1 min-w-0 space-y-5">

                {{-- Content --}}
                <x-card title="{{ __('admin.blog.title') }}">
                    <div class="p-6 space-y-5">

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">{{ __('admin.blog.title_en') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="title_en" id="title-en" required maxlength="255"
                                       value="{{ old('title_en', $post->title_en) }}"
                                       class="form-input w-full @error('title_en') is-invalid @enderror">
                                @error('title_en') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">{{ __('admin.blog.title_ar') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="title_ar" id="title-ar" required maxlength="255" dir="rtl"
                                       value="{{ old('title_ar', $post->title_ar) }}"
                                       class="form-input w-full @error('title_ar') is-invalid @enderror">
                                @error('title_ar') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="form-label">{{ __('admin.blog.slug') }}</label>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 whitespace-nowrap">portal.com/blog/</span>
                                <input type="text" name="slug" id="slug-input" maxlength="255"
                                       value="{{ old('slug', $post->slug) }}"
                                       class="form-input flex-1 text-sm font-mono @error('slug') is-invalid @enderror">
                            </div>
                            @error('slug') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <label class="form-label">{{ __('admin.blog.excerpt_en') }}</label>
                                    <span class="text-xs text-gray-400" data-char-counter="excerpt_en" data-max="300">0 / 300</span>
                                </div>
                                <textarea name="excerpt_en" rows="3" maxlength="300"
                                          class="form-textarea w-full @error('excerpt_en') is-invalid @enderror">{{ old('excerpt_en', $post->excerpt_en) }}</textarea>
                                @error('excerpt_en') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <label class="form-label">{{ __('admin.blog.excerpt_ar') }}</label>
                                    <span class="text-xs text-gray-400" data-char-counter="excerpt_ar" data-max="300">0 / 300</span>
                                </div>
                                <textarea name="excerpt_ar" rows="3" maxlength="300" dir="rtl"
                                          class="form-textarea w-full @error('excerpt_ar') is-invalid @enderror">{{ old('excerpt_ar', $post->excerpt_ar) }}</textarea>
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
                                    :value="old('body_en', $post->body_en)"
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
                                    :value="old('body_ar', $post->body_ar)"
                                />
                                @error('body_ar') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="form-label">{{ __('admin.blog.tags') }}</label>
                            <input type="text" name="tags" id="tags-input"
                                   value="{{ old('tags', is_array($post->tags) ? implode(', ', $post->tags) : $post->tags) }}"
                                   class="form-input w-full text-sm"
                                   placeholder="travel, tips, budget (comma-separated)">
                        </div>
                    </div>
                </x-card>

                {{-- Attachments --}}
                <x-card title="{{ __('admin.blog.attachments') }}">
                    <div class="p-4 space-y-4">
                        @if($post->attachments->isNotEmpty())
                            <ul class="space-y-2" id="existing-attachments">
                                @foreach($post->attachments as $attachment)
                                    <li class="flex items-center justify-between gap-2 text-sm bg-gray-50 rounded-lg px-3 py-2" data-attachment-id="{{ $attachment->id }}">
                                        <a href="{{ Storage::disk('public')->url($attachment->path) }}" target="_blank" class="text-gray-700 truncate hover:underline">
                                            📎 {{ basename($attachment->path) }}
                                            <span class="text-xs text-gray-400">({{ number_format($attachment->size / 1024, 0) }} KB)</span>
                                        </a>
                                        <button type="button" class="text-red-500 hover:text-red-700 text-xs shrink-0" onclick="removeAttachment('{{ $attachment->id }}', this)">
                                            {{ __('common.remove') }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div id="delete-attachment-ids"></div>
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
                <div class="flex items-center gap-3 flex-wrap">
                    <button type="button" class="btn btn-secondary" onclick="submitForm('draft')">{{ __('admin.blog.save_draft') }}</button>
                    <button type="button" class="btn bg-emerald-600 text-white hover:bg-emerald-700" onclick="submitForm('publish')">
                        {{ $post->status->value === 'archived' ? __('admin.blog.republish') : __('admin.blog.publish_now') }}
                    </button>
                    @if(in_array($post->status->value, ['published', 'scheduled']))
                        <button type="button" class="btn bg-amber-500 text-white hover:bg-amber-600" onclick="submitForm('archive')">
                            {{ __('admin.blog.archive') }}
                        </button>
                    @endif
                </div>

            </div>

            {{-- ────────────── RIGHT COLUMN (30%) ────────────── --}}
            <div class="w-full lg:w-80 flex-shrink-0 space-y-4">

                {{-- Publish Settings --}}
                <x-card title="{{ __('admin.blog.publish_settings') }}">
                    <div class="p-4 space-y-4">
                        <div class="text-xs text-gray-500">
                            {{ __('admin.blog.current_status') }}
                            <span class="font-semibold text-gray-800">{{ $post->status->label() }}</span>
                        </div>

                        <div class="flex flex-col gap-2">
                            <button type="button" class="btn btn-secondary w-full text-sm" onclick="submitForm('draft')">
                                {{ __('admin.blog.save_draft') }}
                            </button>
                            <button type="button" id="btn-schedule" class="btn btn-secondary w-full text-sm text-blue-600 border-blue-300">
                                {{ __('admin.blog.schedule') }}…
                            </button>
                            <button type="button" class="btn bg-emerald-600 text-white hover:bg-emerald-700 w-full text-sm" onclick="submitForm('publish')">
                                {{ $post->status->value === 'archived' ? __('admin.blog.republish') : __('admin.blog.publish_now') }}
                            </button>
                            @if(in_array($post->status->value, ['published', 'scheduled']))
                                <button type="button" class="btn bg-amber-500 text-white hover:bg-amber-600 w-full text-sm" onclick="submitForm('archive')">
                                    {{ __('admin.blog.archive') }}
                                </button>
                            @endif
                        </div>

                        <div id="schedule-panel" class="{{ $post->status->value === 'scheduled' ? '' : 'hidden' }} space-y-2 pt-2 border-t border-gray-100">
                            <label class="form-label text-xs">{{ __('admin.blog.scheduled_for') }}</label>
                            <input type="datetime-local" name="scheduled_for" id="scheduled-for"
                                   value="{{ old('scheduled_for', $post->scheduled_for?->format('Y-m-d\TH:i')) }}"
                                   class="form-input w-full text-sm">
                            <button type="button" class="btn btn-primary w-full text-sm" onclick="submitForm('schedule')">
                                {{ __('admin.blog.update_schedule') }}
                            </button>
                        </div>

                        <div class="pt-2 border-t border-gray-100 space-y-1">
                            <div class="text-xs text-gray-500">{{ __('admin.blog.reading_time') }}: <span id="reading-time-display" class="font-medium text-gray-700">~{{ $post->reading_time_minutes ?? 1 }} min read</span></div>
                            @if($post->published_at)
                                <div class="text-xs text-gray-500">{{ __('admin.blog.published_at') }}: <span class="font-medium text-gray-700">{{ $post->published_at->format('d M Y H:i') }}</span></div>
                            @endif
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
                                        <option value="{{ $parent->id }}" {{ old('blog_category_id', $post->blog_category_id) == $parent->id ? 'selected' : '' }}>
                                            {{ $parent->name_en }}
                                        </option>
                                        @foreach($parent->children as $child)
                                            <option value="{{ $child->id }}" {{ old('blog_category_id', $post->blog_category_id) == $child->id ? 'selected' : '' }}>
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
                                    <option value="{{ $c->id }}" {{ old('country_id', $post->country_id) == $c->id ? 'selected' : '' }}>
                                        {{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label text-xs">{{ __('admin.blog.author') }}</label>
                            <select name="author_admin_id" class="form-input w-full text-sm">
                                @foreach($authors as $a)
                                    <option value="{{ $a->id }}" {{ old('author_admin_id', $post->author_admin_id) == $a->id ? 'selected' : '' }}>
                                        {{ $a->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="allow_comments" value="1"
                                       class="rounded text-primary-600"
                                       {{ old('allow_comments', $post->allow_comments) ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ __('admin.blog.allow_comments') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="is_featured" value="1" id="is-featured-cb"
                                       class="rounded text-amber-500"
                                       {{ old('is_featured', $post->is_featured) ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ __('admin.blog.featured_star') }}</span>
                            </label>
                        </div>
                    </div>
                </x-card>

                {{-- Featured Image --}}
                <x-card title="{{ __('admin.blog.featured_image') }}">
                    <div class="p-4 space-y-4">
                        @if($post->featured_image_path)
                            <div class="mb-2">
                                <img src="{{ Storage::disk('public')->url($post->featured_image_path) }}"
                                     alt="{{ $post->featured_image_alt_en }}"
                                     class="w-full h-40 object-cover rounded-lg border border-gray-200">
                                <p class="text-xs text-gray-400 mt-1">{{ __('admin.blog.upload_new_replace') }}</p>
                            </div>
                        @endif
                        <x-form.file-upload
                            name="featured_image"
                            label="{{ $post->featured_image_path ? __('admin.blog.replace_featured_image') : __('admin.blog.featured_image') }}"
                            accept="image/*"
                            :maxSizeMb="5"
                        />
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="form-label text-xs">{{ __('admin.blog.alt_text_en') }}</label>
                                <input type="text" name="featured_image_alt_en"
                                       value="{{ old('featured_image_alt_en', $post->featured_image_alt_en) }}"
                                       class="form-input w-full text-sm">
                            </div>
                            <div>
                                <label class="form-label text-xs">{{ __('admin.blog.alt_text_ar') }}</label>
                                <input type="text" name="featured_image_alt_ar" dir="rtl"
                                       value="{{ old('featured_image_alt_ar', $post->featured_image_alt_ar) }}"
                                       class="form-input w-full text-sm">
                            </div>
                        </div>
                        <div class="border-t border-gray-100 pt-3">
                            @if($post->og_image_path)
                                <div class="mb-2">
                                    <img src="{{ Storage::disk('public')->url($post->og_image_path) }}"
                                         class="w-full h-28 object-cover rounded-lg border border-gray-200">
                                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.blog.current_og_replace') }}</p>
                                </div>
                            @endif
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
                                   value="{{ old('seo_title_en', $post->seo_title_en) }}"
                                   class="form-input w-full text-sm">
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="form-label text-xs">{{ __('admin.blog.seo_title') }} (AR)</label>
                                <span class="text-xs text-gray-400" data-char-counter="seo_title_ar" data-max="60">0 / 60</span>
                            </div>
                            <input type="text" name="seo_title_ar" maxlength="200" dir="rtl"
                                   value="{{ old('seo_title_ar', $post->seo_title_ar) }}"
                                   class="form-input w-full text-sm">
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="form-label text-xs">{{ __('admin.blog.seo_description') }} (EN)</label>
                                <span class="text-xs text-gray-400" data-char-counter="seo_description_en" data-max="160">0 / 160</span>
                            </div>
                            <textarea name="seo_description_en" id="seo-desc-en" rows="2" maxlength="500"
                                      class="form-textarea w-full text-sm">{{ old('seo_description_en', $post->seo_description_en) }}</textarea>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="form-label text-xs">{{ __('admin.blog.seo_description') }} (AR)</label>
                                <span class="text-xs text-gray-400" data-char-counter="seo_description_ar" data-max="160">0 / 160</span>
                            </div>
                            <textarea name="seo_description_ar" rows="2" maxlength="500" dir="rtl"
                                      class="form-textarea w-full text-sm">{{ old('seo_description_ar', $post->seo_description_ar) }}</textarea>
                        </div>

                        {{-- Google snippet preview --}}
                        <div class="border border-gray-200 rounded-lg p-3 bg-gray-50 space-y-0.5">
                            <p class="text-xs text-gray-400 mb-2 font-medium uppercase">{{ __('admin.blog.seo_preview') }}</p>
                            <div id="seo-preview-title" class="text-blue-700 text-sm font-medium leading-snug truncate">
                                {{ $post->seo_title_en ?: $post->title_en }}
                            </div>
                            <div class="text-green-700 text-xs truncate">portal.com/blog/<span id="seo-preview-slug">{{ $post->slug }}</span></div>
                            <div id="seo-preview-desc" class="text-gray-500 text-xs leading-relaxed line-clamp-2">
                                {{ $post->seo_description_en ?: ($post->excerpt_en ?: __('admin.blog.seo_desc_placeholder')) }}
                            </div>
                        </div>
                    </div>
                </x-card>

            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    // ── SEO preview ───────────────────────────────────────────────────────────
    const seoTitle = document.getElementById('seo-title-en');
    const seoDesc  = document.getElementById('seo-desc-en');
    const slugInput = document.getElementById('slug-input');

    seoTitle?.addEventListener('input', () => {
        document.getElementById('seo-preview-title').textContent = seoTitle.value || '{{ addslashes($post->title_en) }}';
    });
    seoDesc?.addEventListener('input', () => {
        document.getElementById('seo-preview-desc').textContent = seoDesc.value || '{{ addslashes($post->excerpt_en ?? __("admin.blog.seo_desc_placeholder")) }}';
    });
    slugInput?.addEventListener('input', () => {
        document.getElementById('seo-preview-slug').textContent = slugInput.value || '{{ $post->slug }}';
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
    document.getElementById('btn-schedule')?.addEventListener('click', () => {
        document.getElementById('schedule-panel').classList.toggle('hidden');
    });

    // ── Reading time estimate ─────────────────────────────────────────────────
    const bodyEnEl = document.getElementById('body_en');
    if (bodyEnEl) {
        bodyEnEl.addEventListener('input', () => {
            clearTimeout(bodyEnEl._rt);
            bodyEnEl._rt = setTimeout(() => {
                const text = bodyEnEl.value.replace(/<[^>]+>/g, ' ');
                const words = text.trim().split(/\s+/).filter(Boolean).length;
                const mins = Math.max(1, Math.ceil(words / 200));
                document.getElementById('reading-time-display').textContent = `~${mins} min read`;
            }, 600);
        });
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

    // ── Attachment removal (marks for deletion on next save) ───────────────────
    window.removeAttachment = function (fileId, button) {
        const li = button.closest('li');
        li?.remove();
        const holder = document.getElementById('delete-attachment-ids');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_attachment_ids[]';
        input.value = fileId;
        holder.appendChild(input);
    };
})();
</script>
@endpush
@endsection
