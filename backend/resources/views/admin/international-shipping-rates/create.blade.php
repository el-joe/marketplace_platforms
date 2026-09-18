@extends('layouts.admin')

@section('title', __('admin.international_shipping.add_rate'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
<div class="p-6">
    <form method="POST" action="{{ route('admin.international-shipping-rates.store') }}" novalidate>
        @csrf
        @include('admin.international-shipping-rates._form', ['mode' => 'create', 'rate' => $rate])
    </form>
</div>
@endsection
