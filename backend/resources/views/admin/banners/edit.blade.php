@extends('layouts.admin')

@section('title', __('admin.banners.edit_banner_name_title', ['name' => $banner->name ?? '']))

@push('styles')
    @vite(['resources/js/admin/banners.js'])
@endpush

@section('content')
    <form id="banner-form" method="POST" action="{{ route('admin.banners.update', $banner->id) }}"
        enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('admin.banners._form', [
            'mode' => 'edit',
            'banner' => $banner,
            'desktopImage' => $desktopImage,
            'mobileImage' => $mobileImage,
        ])
        </form>
@endsection
