@extends('layouts.partner')

@section('title', __('partner.roles.new_role'))
@section('page-title', __('partner.roles.new_role'))

@push('scripts')
    @vite('resources/js/partner/roles.js')
@endpush

@section('content')
    <form id="role-form" method="POST" action="{{ route('partner.roles.store') }}" novalidate>
        @csrf
        @include('partner.roles._form', ['mode' => 'create'])
    </form>
@endsection
