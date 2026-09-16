@php
    $groupMeta = [
        'social' => ['label' => __('admin.footer_settings.social_links'), 'hasPlatform' => true, 'hasLabel' => false, 'hasIcon' => true],
        'bottom_nav' => ['label' => __('admin.footer_settings.bottom_nav_links'), 'hasPlatform' => false, 'hasLabel' => true, 'hasIcon' => false],
        'app_store' => ['label' => __('admin.footer_settings.app_store_links'), 'hasPlatform' => true, 'hasLabel' => false, 'hasIcon' => true],
        'payment_method' => ['label' => __('admin.footer_settings.payment_methods'), 'hasPlatform' => false, 'hasLabel' => true, 'hasIcon' => true],
    ];
@endphp

@extends('layouts.admin')

@section('title', __('admin.footer_settings.title'))

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.footer_settings.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.footer_settings.subtitle') }}</p>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="space-y-6">
        @foreach ($groupMeta as $groupKey => $meta)
            @php $groupLinks = $links->get($groupKey, collect()); @endphp

            <x-card :title="$meta['label']">
                <div class="overflow-x-auto">
                    <table class="table-base w-full text-sm" id="footer-links-table-{{ $groupKey }}">
                        <thead>
                            <tr>
                                @if($meta['hasPlatform'])
                                    <th>{{ __('admin.footer_settings.platform') }}</th>
                                @endif
                                @if($meta['hasLabel'])
                                    <th>{{ __('admin.footer_settings.label_en') }}</th>
                                @endif
                                @if($meta['hasIcon'])
                                    <th>{{ __('admin.footer_settings.icon_path') }}</th>
                                @endif
                                <th>{{ __('admin.footer_settings.url') }}</th>
                                <th class="text-center">{{ __('common.status') }}</th>
                                <th class="text-end">{{ __('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($groupLinks as $link)
                                <tr x-data="{ editing: false }">
                                    <td colspan="{{ ($meta['hasPlatform'] ? 1 : 0) + ($meta['hasLabel'] ? 1 : 0) + ($meta['hasIcon'] ? 1 : 0) + 3 }}" class="p-0">
                                        <div x-show="!editing" class="flex items-center px-4 py-2 gap-4">
                                            @if($meta['hasPlatform'])
                                                <span class="flex-1 min-w-0 truncate">{{ $link->platform ?? '—' }}</span>
                                            @endif
                                            @if($meta['hasLabel'])
                                                <span class="flex-1 min-w-0 truncate">{{ $link->label_en ?? '—' }}</span>
                                            @endif
                                            @if($meta['hasIcon'])
                                                <span class="flex-1 min-w-0 truncate text-xs text-gray-500">{{ $link->icon_path ?? '—' }}</span>
                                            @endif
                                            <span class="flex-1 min-w-0 truncate text-xs text-gray-500">{{ $link->url ?? '—' }}</span>
                                            <span class="w-24 text-center">
                                                <button type="button" class="footer-link-toggle-active-btn inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $link->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}"
                                                    data-url="{{ route('admin.footer-settings.toggle-active', $link->id) }}">
                                                    {{ $link->is_active ? __('admin.footer_settings.active') : __('admin.footer_settings.inactive') }}
                                                </button>
                                            </span>
                                            <span class="w-32 flex items-center justify-end gap-1">
                                                <button type="button" class="btn btn-ghost btn-xs" @click="editing = true">{{ __('common.edit') }}</button>
                                                <form action="{{ route('admin.footer-settings.destroy', $link->id) }}" method="POST" onsubmit="return confirm('{{ __('admin.footer_settings.delete_confirm') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-ghost btn-xs text-red-600 hover:bg-red-50">{{ __('common.delete') }}</button>
                                                </form>
                                            </span>
                                        </div>

                                        <div x-show="editing" x-cloak class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                                            <form action="{{ route('admin.footer-settings.update', $link->id) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="group" value="{{ $groupKey }}">

                                                @if($meta['hasPlatform'])
                                                    <input type="text" name="platform" value="{{ $link->platform }}" placeholder="{{ __('admin.footer_settings.platform') }}" class="form-input">
                                                @endif
                                                @if($meta['hasLabel'])
                                                    <input type="text" name="label_en" value="{{ $link->label_en }}" placeholder="{{ __('admin.footer_settings.label_en') }}" class="form-input">
                                                    <input type="text" name="label_ar" value="{{ $link->label_ar }}" placeholder="{{ __('admin.footer_settings.label_ar') }}" dir="rtl" class="form-input">
                                                @endif
                                                @if($meta['hasIcon'])
                                                    <input type="text" name="icon_path" value="{{ $link->icon_path }}" placeholder="{{ __('admin.footer_settings.icon_path') }}" class="form-input">
                                                @endif
                                                <input type="url" name="url" value="{{ $link->url }}" placeholder="{{ __('admin.footer_settings.url') }}" class="form-input">
                                                <input type="number" name="sort_order" value="{{ $link->sort_order }}" class="form-input" placeholder="Sort order">

                                                <label class="flex items-center gap-2">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" {{ $link->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-primary-600">
                                                    <span class="text-sm text-gray-600">{{ __('admin.footer_settings.active') }}</span>
                                                </label>

                                                <div class="flex items-center gap-2 md:col-span-2">
                                                    <button type="submit" class="btn btn-primary btn-xs">{{ __('common.save') }}</button>
                                                    <button type="button" class="btn btn-ghost btn-xs" @click="editing = false">{{ __('common.cancel') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-400 text-sm">{{ __('admin.footer_settings.no_links') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <details class="mt-4 border-t border-gray-100 pt-4">
                    <summary class="text-sm font-medium text-primary-600 cursor-pointer">{{ __('admin.footer_settings.add_link') }}</summary>
                    <form action="{{ route('admin.footer-settings.store') }}" method="POST" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                        @csrf
                        <input type="hidden" name="group" value="{{ $groupKey }}">

                        @if($meta['hasPlatform'])
                            <input type="text" name="platform" placeholder="{{ __('admin.footer_settings.platform') }}" class="form-input">
                        @endif
                        @if($meta['hasLabel'])
                            <input type="text" name="label_en" placeholder="{{ __('admin.footer_settings.label_en') }}" class="form-input">
                            <input type="text" name="label_ar" placeholder="{{ __('admin.footer_settings.label_ar') }}" dir="rtl" class="form-input">
                        @endif
                        @if($meta['hasIcon'])
                            <input type="text" name="icon_path" placeholder="{{ __('admin.footer_settings.icon_path') }}" class="form-input">
                        @endif
                        <input type="url" name="url" placeholder="{{ __('admin.footer_settings.url') }}" class="form-input">

                        <label class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm text-gray-600">{{ __('admin.footer_settings.active') }}</span>
                        </label>

                        <div class="md:col-span-2">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.footer_settings.add_link') }}</button>
                        </div>
                    </form>
                </details>
            </x-card>
        @endforeach
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[id^="footer-links-table-"]').forEach(function (table) {
                table.addEventListener('click', function (e) {
                    const btn = e.target.closest('.footer-link-toggle-active-btn');
                    if (!btn) return;

                    $.ajax({
                        url: btn.dataset.url,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    }).done(function (res) {
                        btn.textContent = res.is_active ? @json(__('admin.footer_settings.active')) : @json(__('admin.footer_settings.inactive'));
                        btn.classList.toggle('bg-green-100', res.is_active);
                        btn.classList.toggle('text-green-700', res.is_active);
                        btn.classList.toggle('bg-gray-100', !res.is_active);
                        btn.classList.toggle('text-gray-500', !res.is_active);
                    }).fail(function () {
                        window.Toast?.error('Failed to toggle status.');
                    });
                });
            });
        });
    </script>
@endpush
