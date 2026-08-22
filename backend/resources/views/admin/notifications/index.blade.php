@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.notifications_section.title'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.notifications_section.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.notifications_section.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.notification-management.send') }}" class="btn btn-primary btn-sm">
                {{ __('admin.notifications_section.send_notification') }}
            </a>
        </div>
    </div>

    {{-- ─── Stats row ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-card title="{{ __('common.total') }}" :value="number_format($stats['total'])" icon="bell" />
        <x-stat-card title="{{ __('admin.notifications_section.channel_database') }}" :value="number_format($stats['database'])" icon="circle-stack" />
        <x-stat-card title="{{ __('admin.notifications_section.channel_push') }}" :value="number_format($stats['push'])" icon="device-phone-mobile" />
        <x-stat-card title="{{ __('admin.notifications_section.channel_email') }}" :value="number_format($stats['email'])" icon="envelope" />
    </div>

    {{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
    <x-card class="mb-5">
        <form id="filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.notifications_section.channel_column') }}</label>
                <select id="filter-channel" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.notifications_section.all_channels') }}</option>
                    <option value="database">{{ __('admin.notifications_section.channel_database') }}</option>
                    <option value="push">{{ __('admin.notifications_section.channel_push') }}</option>
                    <option value="email">{{ __('admin.notifications_section.channel_email') }}</option>
                    <option value="sms">{{ __('admin.notifications_section.channel_sms') }}</option>
                    <option value="whatsapp">{{ __('admin.notifications_section.channel_whatsapp') }}</option>
                </select>
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.notifications_section.type_column') }}</label>
                <input type="text" id="filter-type" class="form-input w-full text-sm" placeholder="e.g. admin_broadcast">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.notifications_section.from_label') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.notifications_section.to_label') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('common.reset') }}</button>
        </form>
    </x-card>

    {{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="notifications-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.type_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.channel_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.recipient_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.title_column') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.read_column') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase">{{ __('admin.notifications_section.sent_at_column') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            searchPlaceholder: @json(__('common.search')),
        });
    </script>
    @vite(['resources/js/admin/notifications.js'])
    <script>
        window.notificationsTableUrl = '{{ route('admin.notification-management.datatable') }}';
    </script>
@endpush
