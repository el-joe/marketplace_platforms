@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/rich-editor.js'])
@endpush

@section('title', __('admin.static_pages.new_page'))

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.static_pages.new_page') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.static_pages.subtitle') }}</p>
        </div>
        <a href="{{ route('admin.static-pages.index') }}" class="btn btn-secondary btn-sm">{{ __('admin.static_pages.back') }}</a>
    </div>

    @include('admin.static-pages._form')
</div>
@endsection
