@extends('layouts.admin')

@section('title', __('admin.products.add_product'))

@push('styles')
@vite([
    'resources/js/components/rich-editor.js',
    'resources/js/components/file-upload.js',
    'resources/js/components/slug-input.js',
    'resources/js/components/select2.js',
    'resources/js/admin/products.js',
])
@endpush

@section('content')
<form
    id="product-form"
    method="POST"
    action="{{ route('admin.products.store') }}"
    data-validate-url="{{ route('admin.products.validate') }}"
    enctype="multipart/form-data"
    novalidate
>
    @csrf
    @include('admin.products._form', ['mode' => 'create'])
</form>
@endsection
