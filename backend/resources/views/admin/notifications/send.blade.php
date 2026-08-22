@extends('layouts.admin')

@section('title', __('admin.notifications_section.send_notification'))

@section('content')

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.notifications_section.send_notification') }}</h1>
        </div>
        <a href="{{ route('admin.notification-management.index') }}" class="btn btn-secondary btn-sm">
            {{ __('admin.notifications_section.back_to_notifications') }}
        </a>
    </div>

    <x-card class="max-w-3xl">
        <form id="send-notification-form" class="space-y-5">
            @csrf

            {{-- Target ─────────────────────────────────────────────────────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.notifications_section.target_label') }}</label>
                <select id="target" name="target" class="form-input w-full">
                    <option value="all">{{ __('admin.notifications_section.target_all') }}</option>
                    <option value="country">{{ __('admin.notifications_section.target_country') }}</option>
                    <option value="specific">{{ __('admin.notifications_section.target_specific') }}</option>
                </select>
            </div>

            <div id="country-field" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.notifications_section.country_label') }}</label>
                <select id="country_id" name="country_id" class="form-input w-full">
                    <option value="">—</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name_en }}</option>
                    @endforeach
                </select>
            </div>

            <div id="customer-ids-field" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.notifications_section.customer_ids_label') }}</label>
                <textarea id="customer_ids" name="customer_ids" rows="4" class="form-input w-full resize-none font-mono text-xs"
                    placeholder="e.g.&#10;3f2504e0-4f89-11d3-9a0c-0305e82c3301"></textarea>
            </div>

            {{-- Channels ───────────────────────────────────────────────────────── --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.notifications_section.channels_label') }}</label>
                <div class="flex items-center gap-5">
                    <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" class="channel-checkbox rounded border-gray-300" value="database" checked>
                        {{ __('admin.notifications_section.channel_database') }}
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" class="channel-checkbox rounded border-gray-300" value="push">
                        {{ __('admin.notifications_section.channel_push') }}
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" class="channel-checkbox rounded border-gray-300" value="email">
                        {{ __('admin.notifications_section.channel_email') }}
                    </label>
                </div>
            </div>

            {{-- Bilingual title/body ───────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.notifications_section.title_en_label') }}</label>
                    <input type="text" id="title_en" maxlength="100" class="form-input w-full">
                    <p class="text-xs text-gray-400 mt-1"><span id="title-en-count">100</span> {{ __('admin.notifications_section.characters_remaining') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.notifications_section.title_ar_label') }}</label>
                    <input type="text" id="title_ar" maxlength="100" class="form-input w-full" dir="rtl">
                    <p class="text-xs text-gray-400 mt-1"><span id="title-ar-count">100</span> {{ __('admin.notifications_section.characters_remaining') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.notifications_section.body_en_label') }}</label>
                    <textarea id="body_en" maxlength="500" rows="4" class="form-input w-full resize-none"></textarea>
                    <p class="text-xs text-gray-400 mt-1"><span id="body-en-count">500</span> {{ __('admin.notifications_section.characters_remaining') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.notifications_section.body_ar_label') }}</label>
                    <textarea id="body_ar" maxlength="500" rows="4" class="form-input w-full resize-none" dir="rtl"></textarea>
                    <p class="text-xs text-gray-400 mt-1"><span id="body-ar-count">500</span> {{ __('admin.notifications_section.characters_remaining') }}</p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" id="send-broadcast-btn" class="btn btn-primary btn-sm"
                    data-url="{{ route('admin.notification-management.send.store') }}">
                    {{ __('admin.notifications_section.send_broadcast') }}
                </button>
            </div>
        </form>
    </x-card>

@endsection

@push('scripts')
    <script>
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            fillAllFields: @json(__('admin.notifications_section.fill_all_fields')),
            selectAtLeastOneChannel: @json(__('admin.notifications_section.select_at_least_one_channel')),
            broadcastQueued: @json(__('admin.notifications_section.broadcast_queued')),
            failedToSend: @json(__('admin.notifications_section.failed_to_send')),
            send: @json(__('admin.notifications_section.send_broadcast')),
            pleaseWait: @json(__('admin.customers_section.please_wait')),
            charactersRemaining: @json(__('admin.notifications_section.characters_remaining')),
        });
    </script>
    @vite(['resources/js/admin/notifications.js'])
@endpush
