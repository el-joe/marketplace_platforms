@extends('layouts.admin')

@section('title', __('common.edit') . ': ' . e($product->name_en))

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
    action="{{ route('admin.products.update', $product->id) }}"
    data-validate-url="{{ route('admin.products.validate-update', $product->id) }}"
    enctype="multipart/form-data"
    novalidate
>
    @csrf
    @method('PUT')
    @include('admin.products._form', ['mode' => 'edit', 'product' => $product])
</form>
@endsection
