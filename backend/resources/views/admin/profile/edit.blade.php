@extends('layouts.admin')

@section('title', __('common.profile'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">{{ __('common.profile') }}</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm max-w-3xl">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.admins_section.admin_details') }}</h2>
        </div>
        <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="flex items-center gap-6 pb-2">
                <img src="{{ $admin->avatar_url }}" alt="{{ $admin->name }}" class="w-24 h-24 rounded-full object-cover border border-gray-200">
                <div class="flex-1">
                    <x-form-input
                        name="avatar"
                        label="{{ __('common.profile_image') ?? 'Profile Image' }}"
                        type="file"
                        accept="image/*"
                    />
                </div>
            </div>

            <x-form-input
                name="name"
                label="{{ __('admin.admins_section.full_name') ?? 'Full Name' }}"
                type="text"
                :value="old('name', $admin->name)"
                required
            />

            <x-form-input
                name="email"
                label="{{ __('common.email') ?? 'Email' }}"
                type="email"
                :value="old('email', $admin->email)"
                required
            />

            <x-form-input
                name="phone"
                label="{{ __('common.phone') ?? 'Phone' }}"
                type="text"
                :value="old('phone', $admin->phone)"
            />

            <hr class="border-gray-100">

            <div class="space-y-2">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.admins_section.password') ?? 'Password' }}</h3>
                <p class="text-xs text-gray-500">{{ __('admin.admins_section.reset_password_notice') ?? 'Leave blank if you do not want to change your password.' }}</p>
            </div>

            <x-form-input
                name="password"
                label="{{ __('common.password') ?? 'New Password' }}"
                type="password"
            />

            <x-form-input
                name="password_confirmation"
                label="{{ __('common.password_confirmation') ?? 'Confirm Password' }}"
                type="password"
            />

            <div class="flex justify-end gap-2 pt-4">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                    {{ __('common.cancel') ?? 'Cancel' }}
                </a>
                <button type="submit" class="btn btn-primary">
                    {{ __('common.save') ?? 'Save' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
