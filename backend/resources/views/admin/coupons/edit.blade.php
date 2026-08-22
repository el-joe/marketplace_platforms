@extends('layouts.admin')

@section('title', __('admin.coupons_section.edit_coupon') . ': ' . e($coupon->code))

@push('styles')
    @vite(['resources/js/components/flatpickr.js', 'resources/js/components/select2.js', 'resources/js/admin/coupons.js'])
@endpush

@section('content')
    <form id="coupon-form" method="POST" action="{{ route('admin.coupons.update', $coupon->id) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.coupons._form', ['mode' => 'edit', 'coupon' => $coupon])
    </form>
@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            generateCodeFailed: @json(__('admin.coupons_section.generate_code_failed')),
            saving: @json(__('admin.coupons_section.saving')),
            couponSaved: @json(__('admin.coupons_section.coupon_saved')),
            saveFailed: @json(__('admin.coupons_section.save_failed')),
            networkErrorRetry: @json(__('admin.coupons_section.network_error_retry')),
            editValueWarningTitle: @json(__('admin.coupons_section.edit_value_warning_title')),
            editValueWarningBody: @json(__('admin.coupons_section.edit_value_warning_body', ['count' => ':count'])),
            proceedAnyway: @json(__('admin.coupons_section.proceed_anyway')),
        });
    </script>
@endpush