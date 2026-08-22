@extends('layouts.admin')

@section('title', __('admin.vouchers_section.edit_voucher') . ': ' . e($voucher->code))

@push('styles')
    @vite(['resources/js/components/flatpickr.js', 'resources/js/components/select2.js'])
@endpush

@section('content')
    <form id="voucher-form" method="POST" action="{{ route('admin.vouchers.update', $voucher->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.vouchers._form', ['mode' => 'edit', 'voucher' => $voucher, 'countries' => $countries])
    </form>
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            saving: @json(__('admin.vouchers_section.saving')),
            voucherSaved: @json(__('admin.vouchers_section.updated')),
            saveFailed: @json(__('admin.vouchers_section.save_failed')),
            networkErrorRetry: @json(__('admin.vouchers_section.network_error_retry')),
        });
    </script>
@endpush
