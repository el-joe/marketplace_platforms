@extends('layouts.admin')

@push('head')
    @vite('resources/js/admin/cities.js')
@endpush

@section('title', __('admin.geography.edit_city_form_title', ['name' => $city->name_en]))

@section('content')
    <form method="POST" action="{{ route('admin.cities.update', $city->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.cities._form', ['mode' => 'edit', 'city' => $city])
    </form>
@endsection