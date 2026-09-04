@extends('layouts.admin')

@section('title', __('admin.edit') . ': ' . e($customPage->name_en))

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
    action="{{ route('admin.custom-pages.update', $customPage->id) }}"
    novalidate
>
    @csrf
    @method('PUT')
    @include('admin.custom-pages._form', ['mode' => 'edit', 'customPage' => $customPage, 'filterableAttributes' => $filterableAttributes])
</form>
@endsection
