@extends('layouts.partner')

@section('title', __('partner.team.page_title'))
@section('page-title', __('partner.team.title'))

@push('scripts')
    @vite('resources/js/partner/team.js')
    <script>
        window.TEAM = {
            csrf: '{{ csrf_token() }}',
            storeUrl: '{{ route('partner.team.store') }}',
            updateRoleUrl: '{{ route('partner.team.update-role', ':id') }}',
            toggleActiveUrl: '{{ route('partner.team.toggle-active', ':id') }}',
            destroyUrl: '{{ route('partner.team.destroy', ':id') }}',
            isOwner:         {{ $admin->isOwner() ? 'true' : 'false' }},
        };
    </script>
@endpush

@section('content')
    @php
        $roleColors = [
            'vendor_owner' => 'bg-purple-100 text-purple-700',
            'vendor_manager' => 'bg-blue-100 text-blue-700',
            'vendor_staff' => 'bg-gray-100 text-gray-600',
        ];
        $roleLabels = [
            'vendor_owner' => __('partner.team.role_owner'),
            'vendor_manager' => __('partner.team.role_manager'),
            'vendor_staff' => __('partner.team.role_staff'),
        ];
    @endphp
    <div class="px-4 py-6 sm:px-6 lg:px-8" x-data="{ showInviteModal: false }">

        {{-- Page header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.team.manage_team') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('partner.team.manage_team_subtitle') }}</p>
            </div>
            @if ($admin->isOwner())
                <button type="button" @click="showInviteModal = true"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('partner.team.invite_member') }}
                </button>
            @endif
        </div>

        {{-- Owner-only notice --}}
        @if (!$admin->isOwner())
            <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700">
                {{ __('partner.team.owner_only_notice') }}
            </div>
        @endif

        {{-- Team members table --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('partner.team.member') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('partner.team.role') }}
                        </th>
                        <th
                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                            {{ __('partner.team.last_login') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('partner.team.status') }}
                        </th>
                        @if ($admin->isOwner())
                            <th class="px-6 py-3"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="team-tbody">
                    @forelse ($members as $member)
                        <tr class="hover:bg-gray-50 transition-colors" id="member-row-{{ $member->id }}">
                            {{-- Name / Email --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex-shrink-0 h-9 w-9 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-semibold text-sm select-none">
                                        {{ mb_substr($member->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $member->name }}
                                            @if ($member->id === $admin->id)
                                                <span class="text-xs text-gray-400">{{ __('partner.team.you_suffix') }}</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500">{{ $member->email }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Role --}}
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $roleColors[$member->role] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $roleLabels[$member->role] ?? $member->role }}
                                </span>
                            </td>

                            {{-- Last login --}}
                            <td class="px-6 py-4 hidden sm:table-cell">
                                <span class="text-sm text-gray-500">
                                    {{ $member->last_login_at ? $member->last_login_at->diffForHumans() : '—' }}
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium member-status-badge
                                        {{ $member->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                    {{ $member->is_active ? __('partner.team.active') : __('partner.team.inactive') }}
                                </span>
                            </td>

                            {{-- Actions (owner only, not self, not another owner) --}}
                            @if ($admin->isOwner())
                                <td class="px-6 py-4 text-left">
                                    @if (!$member->isOwner())
                                                <div class="flex items-center justify-end gap-2">
                                                    {{-- Change role --}}
                                                    @unless ($member->id === $admin->id)
                                                        <select class="select-change-role text-xs font-medium rounded-lg border border-gray-300 px-2 py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                                            data-id="{{ $member->id }}">
                                                            @foreach ($assignableRoles as $roleName)
                                                                <option value="{{ $roleName }}" @selected($member->role === $roleName)>
                                                                    {{ $roleLabels[$roleName] ?? $roleName }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @endunless

                                                    {{-- Toggle active --}}
                                                    <button type="button" class="btn-toggle-active text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors
                                                                        {{ $member->is_active
                                        ? 'border-amber-300 text-amber-700 hover:bg-amber-50'
                                        : 'border-green-300 text-green-700 hover:bg-green-50' }}"
                                                        data-id="{{ $member->id }}" data-active="{{ $member->is_active ? '1' : '0' }}">
                                                        {{ $member->is_active ? __('partner.team.deactivate') : __('partner.team.activate') }}
                                                    </button>

                                                    {{-- Delete --}}
                                                    <button type="button"
                                                        class="btn-delete-member text-xs font-medium px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 transition-colors"
                                                        data-id="{{ $member->id }}" data-name="{{ $member->name }}">
                                                        {{ __('partner.team.delete') }}
                                                    </button>
                                                </div>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $admin->isOwner() ? 5 : 4 }}" class="px-6 py-10 text-center text-sm text-gray-400">
                                {{ __('partner.team.no_members_yet') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- Invite Member Modal --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        <div x-show="showInviteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown.escape.window="showInviteModal = false">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showInviteModal = false"></div>

            {{-- Modal panel --}}
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white shadow-xl p-6" @click.stop>
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('partner.team.invite_new_member') }}</h3>
                    <button type="button" @click="showInviteModal = false"
                        class="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form id="form-invite" novalidate>
                    <div class="space-y-4">
                        {{-- Name --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.team.full_name') }} <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="name" autocomplete="off"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        </div>

                        {{-- Email --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.team.email') }} <span
                                    class="text-red-500">*</span></label>
                            <input type="email" name="email" autocomplete="off"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        </div>

                        {{-- Role --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.team.role') }} <span
                                    class="text-red-500">*</span></label>
                            <select name="role"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                @foreach ($assignableRoles as $roleName)
                                    <option value="{{ $roleName }}">
                                        {{ $roleLabels[$roleName] ?? $roleName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Error container --}}
                    <div id="invite-error"
                        class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="showInviteModal = false"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit" id="btn-send-invite"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            {{ __('partner.team.send_invite') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection