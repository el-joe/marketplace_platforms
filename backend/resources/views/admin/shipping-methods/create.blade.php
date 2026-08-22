@extends('layouts.admin')

@section('title', __('admin.shipping_section.add_shipping_method'))

@push('styles')
    @vite(['resources/js/components/flatpickr.js'])
@endpush

@section('content')
    <form method="POST" action="{{ route('admin.shipping-methods.store') }}" novalidate>
        @csrf
        @include('admin.shipping-methods._form', ['mode' => 'create', 'shippingMethod' => $shippingMethod])
    </form>
@endsection
