@extends('layouts.partner')

@section('title', __('partner.roles.edit_role_title', ['name' => $role->name]))
@section('page-title', __('partner.roles.edit_role_title', ['name' => $role->name]))

@push('scripts')
    @vite('resources/js/partner/roles.js')
@endpush

@section('content')
    <form id="role-form" method="POST" action="{{ route('partner.roles.update', $role->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('partner.roles._form', ['mode' => 'edit'])
    </form>
@endsection
