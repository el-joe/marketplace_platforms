@extends('layouts.admin')

@section('title', __('admin.cart_card_offers_section.new_offer'))

@push('styles')
    @vite(['resources/js/components/flatpickr.js', 'resources/js/components/select2.js', 'resources/js/components/file-upload.js', 'resources/js/admin/cart-card-offers.js'])
@endpush

@section('content')
    <form id="cart-card-offer-form" method="POST" action="{{ route('admin.cart-card-offers.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('admin.cart-card-offers._form', ['mode' => 'create'])
    </form>
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            saving: @json(__('admin.cart_card_offers_section.saving')),
            offerCreated: @json(__('admin.cart_card_offers_section.offer_created')),
            saveFailed: @json(__('admin.cart_card_offers_section.save_failed')),
            networkErrorRetry: @json(__('admin.cart_card_offers_section.network_error_retry')),
        });
    </script>
@endpush
