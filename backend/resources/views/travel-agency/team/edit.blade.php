@extends('layouts.travel-agency')

@section('title', __('travel.team.edit_member'))
@section('page-title', __('travel.team.edit_member'))

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-2xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('travel.team.edit_member') }}</h1>
        </div>

        <x-card>
            <form method="POST" action="{{ route('travel-agency.team.update', $member->id) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('travel.team.name') }}
                            <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $member->name) }}" autocomplete="off"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('travel.team.email') }}
                            <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $member->email) }}" autocomplete="off"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        @error('email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('travel.team.phone') }}</label>
                        <input type="text" name="phone" value="{{ old('phone', $member->phone) }}" autocomplete="off"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        @error('phone')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-form-select name="role_id" label="{{ __('travel.team.role') }}" :select2="true">
                        <option value="">{{ __('travel.team.select_role') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}"
                                @selected(old('role_id', $memberRole?->id) == $role->id)>
                                {{ $role->label ?: ucwords(str_replace(['travel_agency_', '_'], ['', ' '], $role->name)) }}
                            </option>
                        @endforeach
                    </x-form-select>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('travel.team.password') }}</label>
                        <input type="password" name="password" autocomplete="new-password"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        <p class="mt-1 text-xs text-gray-400">{{ __('travel.team.password_help') }}</p>
                        @error('password')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('travel.team.confirm_password') }}</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('travel-agency.team.index') }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        {{ __('travel.team.cancel') }}
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                        {{ __('travel.team.save') }}
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/components/select2.js'])
@endpush
