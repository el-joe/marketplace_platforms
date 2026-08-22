@extends('layouts.admin')

@section('title', __('admin.attributes_section.new_attribute'))

@push('styles')
    @vite(['resources/js/admin/attributes.js'])
@endpush

@section('content')
    <form id="attribute-form" method="POST" action="{{ route('admin.attributes.store') }}" novalidate>
        @csrf
        @include('admin.attributes._form', ['mode' => 'create'])
    </form>
@endsection