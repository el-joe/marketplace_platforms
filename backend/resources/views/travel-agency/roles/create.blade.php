@extends('layouts.travel-agency')

@section('title', __('travel.roles.new_role'))
@section('page-title', __('travel.roles.new_role'))

@push('scripts')
    @vite('resources/js/travel_agency/roles.js')
@endpush

@section('content')
    <form id="role-form" method="POST" action="{{ route('travel-agency.roles.store') }}" novalidate>
        @csrf
        @include('travel-agency.roles._form', ['mode' => 'create'])
    </form>
@endsection
