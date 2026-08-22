@extends('layouts.admin')

@push('head')
    @vite('resources/js/admin/countries.js')
@endpush

@section('title', __('admin.geography.add_country_title'))

@section('content')
    <form id="country-form" method="POST" action="{{ route('admin.countries.store') }}" novalidate>
        @csrf
        @include('admin.countries._form', ['mode' => 'create'])
    </form>
@endsection