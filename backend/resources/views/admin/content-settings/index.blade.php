@php
    $activeGroup = request('group', $groups->keys()->first());
    $sections = $groups->get($activeGroup, collect());
@endphp

@extends('layouts.admin')

@section('title', __('admin.content_settings.title'))

@push('head')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css" rel="stylesheet">
@endpush

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.content_settings.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.content_settings.subtitle') }}</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ─── Sidebar: groups ────────────────────────────────────────────── --}}
        <div class="w-full lg:w-64 flex-shrink-0">
            <x-card padding="sm">
                <nav class="flex flex-col gap-1">
                    @foreach ($groups->keys() as $groupKey)
                        <a href="{{ route('admin.content-settings.index', ['group' => $groupKey]) }}"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ $groupKey === $activeGroup ? 'bg-primary-50 text-primary-700' : 'text-gray-600 hover:bg-gray-50' }}">
                            {{ Str::of($groupKey)->replace('_', ' ')->title() }}
                        </a>
                    @endforeach
                </nav>
            </x-card>
        </div>

        {{-- ─── Content: sections for active group ─────────────────────────── --}}
        <div class="flex-1 min-w-0 w-full">
            <form action="{{ route('admin.content-settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" name="group" value="{{ $activeGroup }}">

                @foreach ($sections as $sectionKey => $settings)
                    <x-card :title="Str::of($sectionKey)->replace('_', ' ')->title()">
                        <div class="space-y-6">
                            @foreach ($settings as $setting)
                                @php
                                    $key = $setting->key;
                                    $name = "settings[{$key}][value]";
                                    $label = Str::of($key)->replace('_', ' ')->title();
                                @endphp
                                <div class="border-b border-gray-100 pb-5 last:border-0 last:pb-0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="font-semibold text-gray-900 text-sm">{{ $label }}</span>
                                        <x-badge color="gray">{{ $setting->type }}</x-badge>
                                        @if ($setting->is_public)
                                            <x-badge color="success">{{ __('admin.content_settings.public_api') }}</x-badge>
                                        @endif
                                    </div>

                                    @switch($setting->type)
                                        @case('textarea')
                                            <textarea name="{{ $name }}" rows="4"
                                                class="form-textarea" placeholder="{{ $setting->default_value }}">{{ $setting->value }}</textarea>
                                            @break

                                        @case('editor')
                                            <textarea id="editor_{{ $key }}" name="{{ $name }}"
                                                class="form-textarea summernote">{{ $setting->value }}</textarea>
                                            @break

                                        @case('file')
                                            @if ($setting->value)
                                                <div class="mb-2">
                                                    @if (Str::endsWith($setting->value, ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '.ico']))
                                                        <img src="{{ Storage::disk('public')->url($setting->value) }}"
                                                            class="h-16 w-auto rounded border border-gray-200 object-contain p-1">
                                                    @else
                                                        <a href="{{ Storage::disk('public')->url($setting->value) }}" target="_blank"
                                                            class="text-sm text-primary-600 hover:underline">{{ __('admin.content_settings.current_file') }}</a>
                                                    @endif
                                                </div>
                                            @endif
                                            <input type="file" name="{{ $name }}" data-filepond
                                                data-allowed-extensions="{{ $setting->allowed_extensions }}"
                                                accept="{{ $setting->allowed_extensions ? '.' . str_replace(',', ',.', $setting->allowed_extensions) : '*' }}"
                                                class="filepond-setting">
                                            @break

                                        @case('boolean')
                                            <label class="flex items-center gap-2">
                                                <input type="hidden" name="{{ $name }}" value="0">
                                                <input type="checkbox" name="{{ $name }}" value="1"
                                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                    {{ $setting->value ? 'checked' : '' }}>
                                                <span class="text-sm text-gray-600">{{ __('admin.content_settings.enabled') }}</span>
                                            </label>
                                            @break

                                        @case('color')
                                            <input type="color" name="{{ $name }}"
                                                value="{{ $setting->value ?? $setting->default_value ?? '#000000' }}"
                                                class="h-10 w-16 rounded border border-gray-300">
                                            @break

                                        @case('select')
                                            <select name="{{ $name }}" class="form-select">
                                                @foreach ($setting->options ?? [] as $option)
                                                    <option value="{{ $option }}" {{ (string) $setting->value === (string) $option ? 'selected' : '' }}>
                                                        {{ $option }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @break

                                        @case('json')
                                            <textarea name="{{ $name }}" rows="6" class="form-textarea font-mono text-sm">{{ $setting->value ? json_encode(json_decode($setting->value), JSON_PRETTY_PRINT) : '' }}</textarea>
                                            @break

                                        @case('number')
                                            <input type="number" name="{{ $name }}" value="{{ $setting->value }}"
                                                placeholder="{{ $setting->default_value }}" class="form-input">
                                            @break

                                        @case('url')
                                            <input type="url" name="{{ $name }}" value="{{ $setting->value }}"
                                                placeholder="{{ $setting->default_value }}" class="form-input">
                                            @break

                                        @case('email')
                                            <input type="email" name="{{ $name }}" value="{{ $setting->value }}"
                                                placeholder="{{ $setting->default_value }}" class="form-input">
                                            @break

                                        @case('phone')
                                            <input type="tel" name="{{ $name }}" value="{{ $setting->value }}"
                                                placeholder="{{ $setting->default_value }}" class="form-input">
                                            @break

                                        @default
                                            <input type="text" name="{{ $name }}" value="{{ $setting->value }}"
                                                placeholder="{{ $setting->default_value }}" class="form-input">
                                    @endswitch

                                    @if ($setting->default_value && !in_array($setting->type, ['number', 'url', 'email', 'phone'], true))
                                        <p class="form-hint">{{ __('admin.content_settings.default_value') }} {{ $setting->default_value }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">{{ __('admin.content_settings.save_all') }}</button>
                </div>
            </form>
        </div>

    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('.summernote').summernote({ height: 300, lang: 'en-US' });
        }, { once: true });
    </script>
@endpush
