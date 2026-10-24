@extends('layouts.admin')

@section('title', __('admin.portal_content.title'))

@section('content')
@php($locale = app()->getLocale() === 'ar' ? 'ar' : 'en')
<div class="p-6 space-y-5">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.portal_content.title') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.portal_content.subtitle') }}</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.portal_content.page') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.portal_content.fields') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.portal_content.edit_by_country') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pages as $page)
                        @php($label = $config[$page->page_key]['label_'.$locale] ?? null)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium text-gray-900">{{ $label ?? $page->page_key }}</div>
                                @if($label)<div class="text-xs font-mono text-gray-400">{{ $page->page_key }}</div>@endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $page->fields_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($countries as $country)
                                        <a href="{{ route('admin.portal-content.page', [$country->site_code, $page->page_key]) }}"
                                           title="{{ __('admin.portal_content.edit_for_country', ['country' => $locale === 'ar' ? ($country->name_ar ?: $country->name_en) : $country->name_en]) }}"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 hover:border-blue-400">
                                            @include('admin.portal-content._flag', ['country' => $country])
                                            {{ $locale === 'ar' ? ($country->name_ar ?: $country->name_en) : $country->name_en }}
                                        </a>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400">{{ __('admin.portal_content.empty_state') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
