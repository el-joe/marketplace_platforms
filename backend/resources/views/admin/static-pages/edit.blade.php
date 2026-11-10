@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/rich-editor.js'])
@endpush

@section('title', __('admin.static_pages.edit_page'))

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900 truncate">{{ $page->title_en }}</h1>
            <p class="text-sm text-gray-500 mt-0.5 font-mono" dir="ltr">/{{ $page->slug }}</p>
        </div>
        <a href="{{ route('admin.static-pages.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.static_pages.back') }}</a>
    </div>

    @include('admin.static-pages._form')
</div>
@endsection
