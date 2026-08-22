@extends('layouts.admin')

@section('title', __('admin.categories.new_category'))

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
    action="{{ route('admin.categories.store') }}"
    novalidate
>
    @csrf
    @include('admin.categories._form', ['mode' => 'create'])
</form>
@endsection
