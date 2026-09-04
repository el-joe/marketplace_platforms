@extends('layouts.admin')

@section('title', __('admin.custom_pages.title'))

@push('styles')
    @vite(['resources/js/admin/custom-pages.js'])
@endpush

@section('content')
    <div class="space-y-4">

        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div></div>
            <a href="{{ route('admin.custom-pages.create') }}" class="btn btn-primary btn-sm">
                <x-heroicon name="plus" class="w-4 h-4 mr-1" />
                {{ __('admin.custom_pages.create_title') }}
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm" id="custom-pages-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('admin.name_en') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('admin.custom_pages.slug') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('admin.custom_pages.categories') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('admin.categories.has_filters') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">{{ __('admin.is_active') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($customPages as $page)
                            <tr data-id="{{ $page->id }}">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $page->name_en }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $page->slugRecord?->slug_url }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $page->categories()->count() }}</td>
                                <td class="px-4 py-3 text-gray-500">
                                    {{ $page->has_filters ? __('admin.yes') : __('admin.no') }}
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" class="js-toggle-active" data-id="{{ $page->id }}">
                                        <span class="px-2 py-0.5 rounded-full text-xs {{ $page->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $page->is_active ? __('admin.active') : __('admin.inactive') }}
                                        </span>
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.custom-pages.edit', $page->id) }}" class="text-primary-600 hover:underline">{{ __('admin.edit') }}</a>
                                    <button type="button" class="js-delete-custom-page ml-3 text-rose-500 hover:underline" data-id="{{ $page->id }}">{{ __('admin.delete') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('admin.custom_pages.no_custom_pages_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
