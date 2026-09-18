@extends('layouts.admin')

@section('title', __('admin.international_shipping.edit_rate'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
<div class="p-6">
    <form method="POST" action="{{ route('admin.international-shipping-rates.update', $rate) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.international-shipping-rates._form', ['mode' => 'edit', 'rate' => $rate])
    </form>
</div>
@endsection
