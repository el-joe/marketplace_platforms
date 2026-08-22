@extends('layouts.admin')

@section('title', __('docs/panels/travel.title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.docs.index') }}" class="text-sm text-primary-600 hover:underline">&larr; {{ __('admin.nav.documentation') }}</a>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-3xl">✈️</span>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('docs/panels/travel.title') }}</h1>
        </div>
        <p class="text-sm text-gray-500 mt-2">
            {!! __('docs/panels/travel.meta_guard') !!}
        </p>
        <p class="text-sm text-gray-500 mt-1">{!! __('docs/panels/travel.meta_team') !!}</p>
    </div>

    <div class="space-y-8">

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.dashboard.title') }}</h2>
            <p class="text-sm text-gray-700">{{ __('docs/panels/travel.dashboard.summary') }}</p>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.packages.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/packages</code> — {{ __('docs/panels/travel.packages.crud') }}</li>
                <li>{{ __('docs/panels/travel.packages.create') }}</li>
                <li>{!! __('docs/panels/travel.packages.submit') !!}</li>
                <li>{{ __('docs/panels/travel.packages.withdraw') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.bookings.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/bookings</code> — {{ __('docs/panels/travel.bookings.all') }}</li>
                <li>{!! __('docs/panels/travel.bookings.status') !!}</li>
                <li>{{ __('docs/panels/travel.bookings.manual') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.inquiries.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/inquiries</code> — {{ __('docs/panels/travel.inquiries.inquiries') }}</li>
                <li>{{ __('docs/panels/travel.inquiries.actions') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.marketer_campaigns.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/campaigns</code> — {{ __('docs/panels/travel.marketer_campaigns.campaigns') }}</li>
                <li>{{ __('docs/panels/travel.marketer_campaigns.invite') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.reports.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/reports/revenue</code> — {{ __('docs/panels/travel.reports.revenue') }}</li>
                <li><code>/reports/bookings</code> — {{ __('docs/panels/travel.reports.bookings') }}</li>
                <li><code>/reports/packages</code> — {{ __('docs/panels/travel.reports.packages') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.team.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/team</code> — {{ __('docs/panels/travel.team.team') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.roles.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/roles</code> — {{ __('docs/panels/travel.roles.roles') }}</li>
                <li>{!! __('docs/panels/travel.roles.system_roles') !!}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.bank_accounts.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/bank-accounts</code> — {{ __('docs/panels/travel.bank_accounts.flagged') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/travel.profile.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/profile</code> — {{ __('docs/panels/travel.profile.profile') }}</li>
            </ul>
        </section>

    </div>
@endsection
