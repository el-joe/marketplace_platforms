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

    <form method="POST" action="{{ $template ? route('admin.contracts.templates.update', $template) : route('admin.contracts.templates.store') }}" class="space-y-5 rounded-xl border bg-white p-6">
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
                    <select name="category_scope" required class="form-input w-full text-sm">
                        <option value="classified" @selected(old('category_scope') === 'classified')>{{ __('admin.contracts.scope_classified') }}</option>
                        <option value="product" @selected(old('category_scope') === 'product')>{{ __('admin.contracts.scope_product') }}</option>
                    </select>
                @endif
            </div>
        </div>

        <div x-data="{ open: false }">
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

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.contracts.templates.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
        </div>
    </form>
</div>
@endsection
