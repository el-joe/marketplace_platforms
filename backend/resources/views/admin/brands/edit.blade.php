@extends('layouts.admin')

@section('title', __('common.edit') . ': ' . e($brand->name_en))

@push('styles')
    @vite(['resources/js/components/slug-input.js', 'resources/js/admin/brands.js'])
@endpush

@section('content')
    <form id="brand-form" method="POST" action="{{ route('admin.brands.update', $brand->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.brands._form', ['mode' => 'edit', 'brand' => $brand])
    </form>
@endsection