@extends('layouts.admin')

@section('title', e($plan->name_en))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
    <form id="warranty-plan-form" method="POST" action="{{ route('admin.warranty-plans.update', $plan->id) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PATCH')
        @include('admin.warranty-plans._form', ['mode' => 'edit', 'plan' => $plan])
    </form>
@endsection
