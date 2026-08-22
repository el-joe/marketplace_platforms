@extends('layouts.carrier')

@section('title', __('carrier.supervisors.create.title'))

@section('content')

<div class="mb-6">
    <a href="{{ route('carrier.supervisors.index') }}" class="text-indigo-600 hover:underline text-sm">← {{ __('carrier.supervisors.create.back') }}</a>
    <h1 class="text-2xl font-black text-gray-900 mt-2">{{ __('carrier.supervisors.create.title') }}</h1>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-8 max-w-2xl">
    <form method="POST" action="{{ route('carrier.supervisors.store') }}" class="space-y-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.full_name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email') border-red-400 @enderror">
                @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.password') }}</label>
                <input type="password" name="password" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ __('carrier.common.confirm_password') }}</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('carrier.supervisors.permissions') }}</label>
            <div class="grid grid-cols-2 gap-2">
                @foreach($allPermissions as $perm)
                @php
                    $labels = [
                        'manage_agents'  => __('carrier.supervisors.permission_manage_agents'),
                        'view_orders'    => __('carrier.supervisors.permission_view_orders'),
                        'assign_orders'  => __('carrier.supervisors.permission_assign_orders'),
                        'view_reports'   => __('carrier.supervisors.permission_view_reports'),
                    ];
                @endphp
                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-indigo-50 transition">
                    <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                           class="rounded border-gray-300 text-indigo-600"
                           @checked(in_array($perm, old('permissions', [])))>
                    <span class="text-sm text-gray-700">{{ $labels[$perm] ?? $perm }}</span>
                </label>
                @endforeach
            </div>
            @error('permissions')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-lg transition text-sm">
                {{ __('carrier.supervisors.create.submit') }}
            </button>
            <a href="{{ route('carrier.supervisors.index') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-6 py-2.5 rounded-lg transition text-sm">
                {{ __('carrier.common.cancel') }}
            </a>
        </div>
    </form>
</div>

@endsection
