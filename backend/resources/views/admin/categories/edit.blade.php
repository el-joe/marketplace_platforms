@extends('layouts.admin')

@section('title', __('admin.edit') . ': ' . localized($category))

@push('styles')
@vite([
    'resources/js/components/slug-input.js',
    'resources/js/admin/categories.js',
])
@endpush

@section('content')
<form
    id="category-form"
    method="POST"
    action="{{ route('admin.categories.update', $category->id) }}"
    enctype="multipart/form-data"
    novalidate
>
    @csrf
    @method('PUT')
    @include('admin.categories._form', ['mode' => 'edit', 'category' => $category])
</form>
@endsection
