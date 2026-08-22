@extends('layouts.admin')

@section('title', $shippingMethod->name)

@push('styles')
    @vite(['resources/js/components/flatpickr.js'])
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.shipping-methods.update', $shippingMethod->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.shipping-methods._form', ['mode' => 'edit', 'shippingMethod' => $shippingMethod])
    </form>
@endsection
