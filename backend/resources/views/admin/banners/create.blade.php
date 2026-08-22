@extends('layouts.admin')

@section('title', __('admin.banners.new_banner_title'))

@push('styles')
    @vite(['resources/js/admin/banners.js'])
@endpush

@section('content')
    <form id="banner-form" method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data"
        novalidate>
        @csrf
        @include('admin.banners._form', ['mode' => 'create'])
    </form>
@endsection