@extends('layouts.admin')

@section('title', __('admin.vendor_listings.edit_title'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
<div class="p-6">
    <form method="POST" action="{{ route('admin.vendor-listings.update', $listing) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.vendor-listings._form', ['listing' => $listing])
    </form>
</div>
@endsection
