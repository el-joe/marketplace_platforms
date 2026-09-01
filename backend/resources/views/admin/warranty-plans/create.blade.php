@extends('layouts.admin')

@section('title', __('admin.warranty_plans.new_plan'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
    <form id="warranty-plan-form" method="POST" action="{{ route('admin.warranty-plans.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('admin.warranty-plans._form', ['mode' => 'create'])
    </form>
@endsection
