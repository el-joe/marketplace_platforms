@extends('layouts.travel-agency')

@section('title', __('travel.roles.edit_role_title', ['name' => $role->label ?? $role->name]))
@section('page-title', __('travel.roles.edit_role_title', ['name' => $role->label ?? $role->name]))

@push('scripts')
    @vite('resources/js/travel_agency/roles.js')
@endpush

@section('content')
    <form id="role-form" method="POST" action="{{ route('travel-agency.roles.update', $role->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('travel-agency.roles._form', ['mode' => 'edit'])
    </form>
@endsection
