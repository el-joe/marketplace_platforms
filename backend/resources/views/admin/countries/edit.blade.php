@extends('layouts.admin')

@push('head')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js', 'resources/js/admin/countries.js'])
@endpush

@section('title', __('admin.geography.edit_country_title', ['name' => $country->name_en]))

@section('content')
    <form id="country-form" method="POST" action="{{ route('admin.countries.update', $country->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.countries._form', ['mode' => 'edit', 'country' => $country])
    </form>
@endsection