@extends('layouts.travel-agency')

@section('title', __('travel.roles.page_title'))
@section('page-title', __('travel.roles.title'))

@push('scripts')
    @vite('resources/js/travel_agency/roles.js')
    <script>
        window.ROLES = {
            csrf: '{{ csrf_token() }}',
            destroyUrl: '{{ route('travel-agency.roles.destroy', ':id') }}',
        };
    </script>
@endpush

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Page header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('travel.roles.manage_roles') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('travel.roles.manage_roles_subtitle') }}</p>
            </div>
            <a href="{{ route('travel-agency.roles.create') }}"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('travel.roles.create_role') }}
            </a>
        </div>

        {{-- Roles table --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('travel.roles.role_name') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('travel.roles.permissions_column') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('travel.roles.members_column') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($roles as $role)
                        @php $isSystem = in_array($role->name, $systemRoles, true); @endphp
                        <tr class="hover:bg-gray-50 transition-colors" id="role-row-{{ $role->id }}">
                            <td class="px-6 py-4">
                                <span class="text-sm font-semibold text-gray-900">{{ $role->label ?? $role->name }}</span>
                                @if ($isSystem)
                                    <span class="ms-2 inline-flex items-center rounded-full bg-purple-100 text-purple-700 px-2 py-0.5 text-xs font-medium">
                                        {{ __('travel.roles.default_badge') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ $role->permissions_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ (int) ($memberCounts[$role->id] ?? 0) }}</td>
                            <td class="px-6 py-4 text-left">
                                @unless ($isSystem)
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('travel-agency.roles.edit', $role->id) }}"
                                            class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                                            {{ __('common.edit') }}
                                        </a>
                                        <button type="button" class="btn-delete-role text-xs font-medium px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors"
                                            data-id="{{ $role->id }}" data-name="{{ $role->label ?? $role->name }}">
                                            {{ __('common.delete') }}
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-400">
                                {{ __('travel.roles.no_roles_yet') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
