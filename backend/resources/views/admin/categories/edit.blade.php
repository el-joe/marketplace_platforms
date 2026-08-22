@extends('layouts.admin')

@section('title', __('admin.edit') . ': ' . e($category->name_en))

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
    novalidate
>
    @csrf
    @method('PUT')
    @include('admin.categories._form', ['mode' => 'edit', 'category' => $category])
</form>

<script>
    window.ROUTES_CAT_EDIT = {
        syncAttributes: '{{ route('admin.categories.sync-attributes', $category->id) }}',
    };
</script>
@endsection
