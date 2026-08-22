@extends('layouts.admin')

@section('title', __('admin.warehouses_section.add_warehouse'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.warehouses.store') }}" novalidate>
        @csrf
        @include('admin.warehouses._form', ['mode' => 'create'])
    </form>
@endsection
