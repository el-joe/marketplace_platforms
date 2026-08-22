@extends('layouts.admin')

@section('title', __('admin.admin_listings.edit_listing_title'))

@push('styles')
    @vite(['resources/js/components/select2.js', 'resources/js/components/flatpickr.js'])
@endpush

@section('content')
<div class="p-6">
    <form method="POST" action="{{ route('admin.admin-listings.update', $listing) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.admin-listings._form', ['listing' => $listing])
    </form>
</div>
@endsection
