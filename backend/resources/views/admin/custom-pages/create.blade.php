@extends('layouts.admin')

@section('title', __('admin.custom_pages.create_title'))

@push('styles')
@vite([
    'resources/js/components/slug-input.js',
    'resources/js/admin/custom-pages.js',
])
@endpush

@section('content')
<form
    id="custom-page-form"
    method="POST"
    action="{{ route('admin.custom-pages.store') }}"
    novalidate
>
    @csrf
    @include('admin.custom-pages._form', ['mode' => 'create'])
</form>
@endsection
