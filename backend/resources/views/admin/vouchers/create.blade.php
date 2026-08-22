@extends('layouts.admin')

@section('title', __('admin.vouchers_section.add_voucher'))

@push('styles')
    @vite(['resources/js/components/flatpickr.js', 'resources/js/components/select2.js'])
@endpush

@section('content')
    <form id="voucher-form" method="POST" action="{{ route('admin.vouchers.store') }}" novalidate>
        @csrf
        @include('admin.vouchers._form', ['mode' => 'create', 'countries' => $countries])
    </form>
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            saving: @json(__('admin.vouchers_section.saving')),
            voucherCreated: @json(__('admin.vouchers_section.created')),
            saveFailed: @json(__('admin.vouchers_section.save_failed')),
            networkErrorRetry: @json(__('admin.vouchers_section.network_error_retry')),
        });
    </script>
@endpush
