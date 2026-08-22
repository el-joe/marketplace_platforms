@extends('layouts.admin')

@section('title', __('admin.warehouses_section.edit_warehouse_title', ['name' => e($warehouse->name)]))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.warehouses.update', $warehouse->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.warehouses._form', ['mode' => 'edit'])
    </form>
@endsection
