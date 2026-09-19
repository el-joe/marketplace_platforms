@extends('layouts.admin')

@section('title', __('admin.admin_listings.new_listing_title'))

@push('styles')
    @vite(['resources/js/components/select2.js', 'resources/js/components/flatpickr.js'])
@endpush

@section('content')
<div class="p-6">
    <form method="POST" action="{{ route('admin.admin-listings.store') }}" novalidate>
        @csrf
        @include('admin.admin-listings._form')
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 mt-6">
            @include('shared.promo-badges-editor', [
                'badges' => [],
                'title' => __('admin.products.tab_promo_badges'),
                'hint' => __('admin.promo_badges.listing_hint'),
            ])
        </div>
    </form>
</div>
@endsection
