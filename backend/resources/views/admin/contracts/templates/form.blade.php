@extends('layouts.admin')
@push('scripts')
    @vite(['resources/js/components/rich-editor.js'])
@endpush

@section('title', __('admin.contracts.template_form_title'))

@section('content')
<div class="mx-auto max-w-4xl space-y-5 p-6">
    <div>
        <a href="{{ route('admin.contracts.templates.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← {{ __('common.back') }}</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $template ? __('admin.contracts.edit_template') : __('admin.contracts.new_template') }}</h1>
        @if ($template && $template->is_published)
            <p class="mt-1 text-sm text-amber-700">{{ __('admin.contracts.published_edit_note', ['version' => $template->version + 1]) }}</p>
        @endif
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $template ? route('admin.contracts.templates.update', $template) : route('admin.contracts.templates.store') }}"
          x-data="{ mode: @js(old('content_mode', $template?->content_mode ?? 'editor')), scope: @js(old('category_scope', $template?->category_scope ?? 'classified')), acceptance: @js(old('acceptance_mode', $template?->acceptance_mode ?? 'click_wrap')) }"
          class="space-y-5 rounded-xl border bg-white p-6">
        @csrf
        @if ($template) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.contracts.col_name') }}</label>
                <input type="text" name="name" value="{{ old('name', $template?->name) }}" required maxlength="200"
                       class="form-input w-full text-sm" {{ $template && $template->is_published ? 'readonly' : '' }}>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.contracts.col_scope') }}</label>
                @if ($template)
                    <input type="text" value="{{ __('admin.contracts.scope_' . $template->category_scope) }}" readonly class="form-input w-full bg-gray-50 text-sm">
                @else
                    <select name="category_scope" x-model="scope" required class="form-input w-full text-sm">
                        <option value="classified">{{ __('admin.contracts.scope_classified') }}</option>
                        <option value="product">{{ __('admin.contracts.scope_product') }}</option>
                        <option value="platform">{{ __('admin.contracts.scope_platform') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500" x-show="scope === 'platform'">{{ __('admin.contracts.scope_platform_help') }}</p>
                    <p class="mt-1 text-xs text-gray-500" x-show="scope !== 'platform'" x-cloak>{{ __('admin.contracts.scope_category_help') }}</p>
                @endif
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">{{ __('admin.contracts.content_mode') }}</label>
            <input type="hidden" name="content_mode" :value="mode">
            <div class="inline-flex rounded-lg border bg-gray-50 p-1 text-sm">
                <button type="button" @click="mode = 'editor'" :class="mode === 'editor' ? 'bg-white shadow text-primary-700' : 'text-gray-600'" class="rounded-md px-4 py-1.5 font-medium">{{ __('admin.contracts.mode_editor') }}</button>
                <button type="button" @click="mode = 'file'" :class="mode === 'file' ? 'bg-white shadow text-primary-700' : 'text-gray-600'" class="rounded-md px-4 py-1.5 font-medium">{{ __('admin.contracts.mode_file') }}</button>
            </div>
            <p class="mt-1 text-xs text-gray-500" x-show="mode === 'file'" x-cloak>{{ __('admin.contracts.mode_file_help', ['mb' => $maxMb]) }}</p>
        </div>

        <div x-show="scope === 'platform'" x-cloak class="space-y-3 rounded-lg border border-blue-100 bg-blue-50/50 p-4">
            <p class="text-sm font-semibold text-gray-800">{{ __('admin.contracts.amendment_title') }}</p>
            <p class="text-xs text-gray-600">{{ __('admin.contracts.amendment_help') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700">{{ __('admin.contracts.acceptance_mode') }}</label>
                    <select name="acceptance_mode" x-model="acceptance" class="form-input w-full text-sm">
                        <option value="click_wrap">{{ __('admin.contracts.acceptance_click_wrap') }}</option>
                        <option value="notice">{{ __('admin.contracts.acceptance_notice') }}</option>
                    </select>
                </div>
                <div x-show="acceptance === 'notice'" x-cloak>
                    <label class="mb-1 block text-xs font-medium text-gray-700">{{ __('admin.contracts.notice_days') }}</label>
                    <input type="number" name="notice_days" min="1" max="90" value="{{ old('notice_days', $template?->notice_days ?? 14) }}" class="form-input w-full text-sm">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">{{ __('admin.contracts.change_summary') }}</label>
                <textarea name="change_summary" rows="3" maxlength="2000" class="form-input w-full text-sm">{{ old('change_summary', $template?->change_summary) }}</textarea>
            </div>
        </div>

        <div x-data="{ open: false }" x-show="mode === 'editor'">
            <button type="button" @click="open = !open" class="text-xs text-primary-600 underline">{{ __('admin.contracts.variables_helper') }} ▾</button>
            <div x-show="open" x-cloak class="mt-2 grid grid-cols-1 gap-2 rounded-lg border bg-gray-50 p-3 sm:grid-cols-2">
                @foreach ($variables as $variable => $description)
                    <div class="flex items-center justify-between rounded border bg-white px-2 py-1 text-xs">
                        <code class="select-all text-primary-700">{{ $variable }}</code>
                        <span class="ms-2 text-gray-500">{{ $description }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div x-show="mode === 'editor'" class="space-y-5">
        <x-form.rich-editor
            name="content_en"
            label="{{ __('admin.contracts.body_en') }}"
            :required="true"
            profile="default"
            :value="$template?->editorHtml('content_en') ?? ''"
            helpText="{{ __('admin.contracts.editor_help') }}"
        />
        <x-form.rich-editor
            name="content_ar"
            label="{{ __('admin.contracts.body_ar') }}"
            :required="true"
            profile="default"
            :value="$template?->editorHtml('content_ar') ?? ''"
        />
        </div>

        <div x-show="mode === 'file'" x-cloak class="space-y-4">
            @foreach (['ar' => __('admin.contracts.file_ar'), 'en' => __('admin.contracts.file_en')] as $language => $label)
                @php($current = $template?->files[$language] ?? null)
                <div class="rounded-lg border p-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700">{{ $label }}</label>
                    @if ($current)
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                            <span><strong>{{ $current['name'] }}</strong> · {{ number_format($current['size'] / 1024, 0) }} KB · <code>{{ substr($current['sha256'], 0, 12) }}</code></span>
                            <span class="space-x-3">
                                <a href="{{ route('admin.contracts.templates.file', [$template, $language]) }}" target="_blank" class="font-medium text-primary-600 hover:underline">{{ __('admin.contracts.view_file') }}</a>
                                <a href="{{ route('admin.contracts.templates.file', [$template, $language, 'download' => 1]) }}" class="font-medium text-primary-600 hover:underline">{{ __('admin.contracts.download_file') }}</a>
                                <label class="inline-flex items-center gap-1 text-red-600"><input type="checkbox" name="remove_file[]" value="{{ $language }}"> {{ __('admin.contracts.remove_file') }}</label>
                            </span>
                        </div>
                    @endif
                    <input type="file" name="file_{{ $language }}" accept=".pdf,.doc,.docx" class="block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-primary-700">
                    <p class="mt-1 text-xs text-gray-500">{{ $current ? __('admin.contracts.replace_file_help') : __('admin.contracts.upload_file_help') }}</p>
                </div>
            @endforeach
            <p class="text-xs text-gray-500">{{ __('admin.contracts.file_fallback_help') }}</p>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.contracts.templates.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
        </div>
    </form>
</div>
@endsection
