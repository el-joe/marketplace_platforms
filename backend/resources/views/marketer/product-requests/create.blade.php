@extends('layouts.marketer')

@section('title', __('product_requests.panel.create_title'))
@section('page-title', __('product_requests.panel.create_title'))

@push('scripts')
    @vite('resources/js/components/select2.js')
@endpush

@section('content')
    @include('shared.product-requests.form', ['panel' => 'marketer'])
@endsection
