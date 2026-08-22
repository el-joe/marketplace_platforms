@extends('layouts.admin')

@section('title', __('admin.roles_section.create_role'))

@push('styles')
    @vite(['resources/js/admin/admins.js'])
@endpush

@section('content')
    <form id="role-form" method="POST" action="{{ route('admin.roles.store') }}" novalidate>
        @csrf
        @include('admin.roles._form', ['mode' => 'create'])
    </form>
@endsection