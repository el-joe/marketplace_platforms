@extends('layouts.admin')

@section('title', __('admin.admins_section.add_administrator'))

@push('styles')
    @vite(['resources/js/components/select2.js', 'resources/js/admin/admins.js'])
@endpush

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            saving: @json(__('admin.admins_section.saving')),
            administratorCreated: @json(__('admin.admins_section.administrator_created')),
            administratorSaved: @json(__('admin.admins_section.administrator_saved')),
            saveFailed: @json(__('admin.admins_section.save_failed')),
            networkError: @json(__('admin.admins_section.network_error')),
            roleSaved: @json(__('admin.admins_section.role_saved')),
            roleCreated: @json(__('admin.admins_section.role_created')),
            resetPasswordConfirmGeneric: @json(__('admin.admins_section.reset_password_confirm_generic')),
            resetPasswordTitleGeneric: @json(__('admin.admins_section.reset_password_title_generic')),
            resetting: @json(__('admin.admins_section.resetting')),
            passwordResetSuccess: @json(__('admin.admins_section.password_reset_success')),
            resetPasswordBtnLabel: @json(__('admin.admins_section.reset_password_btn_label')),
        });
    </script>
@endpush

@section('content')
    <form id="admin-form" method="POST" action="{{ route('admin.admins.store') }}" novalidate>
        @csrf
        @include('admin.admins._form', ['mode' => 'create'])
    </form>
@endsection