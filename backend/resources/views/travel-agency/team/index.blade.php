@extends('layouts.travel-agency')

@section('title', __('travel.team.page_title'))
@section('page-title', __('travel.team.title'))

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8">

        {{-- Page header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('travel.team.manage_team') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('travel.team.manage_team_subtitle') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <input type="text" id="team-search" placeholder="{{ __('common.search') }}"
                    class="rounded-lg border-gray-300 text-sm">
                <select id="team-is-active" class="rounded-lg border-gray-300 text-sm">
                    <option value="">{{ __('travel.team.status') }}</option>
                    <option value="1">{{ __('travel.team.active') }}</option>
                    <option value="0">{{ __('travel.team.inactive') }}</option>
                </select>
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 select-none">
                    <input type="checkbox" id="team-show-deleted"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    {{ __('travel.team.show_deleted') }}
                </label>
                <a id="team-export" href="#" class="text-xs text-blue-600 hover:underline">{{ __('travel.reports.export_csv') }}</a>
                @if ($canInvite)
                    <a href="{{ route('travel-agency.team.create') }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('travel.team.add_member') }}
                    </a>
                @endif
            </div>
        </div>

        <x-card padding="none">
            <div class="overflow-x-auto">
                <table id="team-table" class="table-base w-full" style="width:100%">
                    <thead>
                        <tr>
                            <th>{{ __('travel.team.name') }}</th>
                            <th>{{ __('travel.team.email') }}</th>
                            <th>{{ __('travel.team.phone') }}</th>
                            <th>{{ __('travel.team.role') }}</th>
                            <th class="text-center">{{ __('travel.team.status') }}</th>
                            <th>{{ __('travel.team.last_login') }}</th>
                            <th class="text-center">{{ __('travel.team.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection

@push('scripts')
    <script>
        window.TEAM_ROUTES = {
            datatable: '{{ route('travel-agency.team.datatable') }}',
            export: '{{ route('travel-agency.team.export') }}',
        };
        window.TEAM_CAN_MANAGE = @json($canManage);
        window.TEAM_LABELS = {
            restore: @json(__('travel.team.restore')),
            edit: @json(__('travel.team.edit')),
            deactivate: @json(__('travel.team.deactivate')),
            activate: @json(__('travel.team.activate')),
            delete: @json(__('travel.team.delete')),
            forcePasswordReset: @json(__('travel.team.force_password_reset')),
            confirmForcePasswordReset: @json(__('travel.team.confirm_force_password_reset')),
            confirmDeleteMember: @json(__('travel.team.confirm_delete_member')),
            confirmRestoreMember: @json(__('travel.team.confirm_restore_member')),
        };
    </script>
    @vite(['resources/js/components/datatable.js', 'resources/js/travel_agency/team.js'])
@endpush
