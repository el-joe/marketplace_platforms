@extends('layouts.admin')

@section('title', __('admin.brands.add_brand'))

@push('styles')
    @vite(['resources/js/components/slug-input.js', 'resources/js/admin/brands.js'])
@endpush

@section('content')
    <form id="brand-form" method="POST" action="{{ route('admin.brands.store') }}" novalidate>
        @csrf
        @include('admin.brands._form', ['mode' => 'create'])
    </form>
@endsection