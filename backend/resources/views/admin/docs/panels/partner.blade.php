@extends('layouts.admin')

@section('title', __('docs/panels/partner.title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.docs.index') }}" class="text-sm text-primary-600 hover:underline">&larr; {{ __('admin.nav.documentation') }}</a>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-3xl">🤝</span>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('docs/panels/partner.title') }}</h1>
        </div>
        <p class="text-sm text-gray-500 mt-2">
            {!! __('docs/panels/partner.meta') !!}
        </p>
    </div>

    <div class="space-y-8">

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.auth.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li>{{ __('docs/panels/partner.auth.login') }} <code>partner.domain/login</code></li>
                <li>{{ __('docs/panels/partner.auth.approval') }} <code>/suspended</code> {{ __('docs/panels/partner.auth.suspended_page') }}</li>
                <li>{{ __('docs/panels/partner.auth.password_reset') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.dashboard.title') }}</h2>
            <p class="text-sm text-gray-700">{{ __('docs/panels/partner.dashboard.summary') }}</p>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.orders.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/orders</code> — {{ __('docs/panels/partner.orders.orders') }}</li>
                <li>{!! __('docs/panels/partner.orders.actions') !!}</li>
                <li>{{ __('docs/panels/partner.orders.scope') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.listings.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/listings</code> — {{ __('docs/panels/partner.listings.listings') }}</li>
                <li>{!! __('docs/panels/partner.listings.create') !!}</li>
                <li>{{ __('docs/panels/partner.listings.edit') }}</li>
                <li>{{ __('docs/panels/partner.listings.shipping_preview') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.inventory.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/inventory</code> — {{ __('docs/panels/partner.inventory.inventory') }}</li>
                <li>{{ __('docs/panels/partner.inventory.filters') }}</li>
                <li>{{ __('docs/panels/partner.inventory.movement') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.payouts.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/payouts</code> — {{ __('docs/panels/partner.payouts.payouts') }}</li>
                <li>{{ __('docs/panels/partner.payouts.summary') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.coupons.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/coupons</code> — {{ __('docs/panels/partner.coupons.coupons') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.flash_sales.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/flash-sales</code> — {{ __('docs/panels/partner.flash_sales.events') }}</li>
                <li>{{ __('docs/panels/partner.flash_sales.submit') }}</li>
                <li>{{ __('docs/panels/partner.flash_sales.stats') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.bank_accounts.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/bank-accounts</code> — {{ __('docs/panels/partner.bank_accounts.accounts') }}</li>
                <li>{{ __('docs/panels/partner.bank_accounts.set_primary') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.fbn.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/fbn/inbound</code> — {{ __('docs/panels/partner.fbn.inbound') }}</li>
                <li><code>/fbn/storage-fees</code> — {{ __('docs/panels/partner.fbn.storage_fees') }}</li>
                <li><code>/fbn/warehouses</code> — {{ __('docs/panels/partner.fbn.warehouses') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.performance.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/performance</code> — {{ __('docs/panels/partner.performance.scorecard') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.exceptional_zones.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/exceptional-zones</code> — {{ __('docs/panels/partner.exceptional_zones.opt_in') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.team.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/team</code> — {{ __('docs/panels/partner.team.team') }}</li>
                <li><code>/roles</code> — {{ __('docs/panels/partner.team.roles') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{!! __('docs/panels/partner.profile.title') !!}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/profile</code> — {{ __('docs/panels/partner.profile.profile') }}</li>
                <li><code>/bank-accounts</code> — {{ __('docs/panels/partner.profile.bank_accounts') }}</li>
                <li><code>/change-requests</code> — {{ __('docs/panels/partner.profile.change_requests') }}</li>
                <li><code>/warehouse</code> — {{ __('docs/panels/partner.profile.warehouse') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/partner.packaging.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/packaging/catalog</code> — {{ __('docs/panels/partner.packaging.catalog') }}</li>
                <li><code>/packaging/order</code> — {{ __('docs/panels/partner.packaging.order') }}</li>
                <li><code>/packaging/requests</code> — {{ __('docs/panels/partner.packaging.requests') }}</li>
            </ul>
        </section>

    </div>
@endsection
