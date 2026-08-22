@extends('layouts.admin')

@section('title', __('admin.nav.documentation'))

@section('content')
    <div class="mb-8 bg-gradient-to-br from-primary-600 to-primary-800 rounded-xl px-6 py-8 text-white">
        <h1 class="text-2xl font-bold">{{ __('docs/index.hero.title') }}</h1>
        <p class="text-primary-100 mt-1">{{ __('docs/index.hero.subtitle') }}</p>
    </div>

    {{-- Panels --}}
    <div class="mb-10">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('docs/index.panels_heading') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('admin.docs.panels.admin') }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-sm transition">
                <div class="text-2xl mb-2">🏛</div>
                <div class="font-semibold text-gray-900">{{ __('docs/index.panels.admin.title') }}</div>
                <p class="text-sm text-gray-500 mt-1">{{ __('docs/index.panels.admin.description') }}</p>
            </a>
            <a href="{{ route('admin.docs.panels.partner') }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-sm transition">
                <div class="text-2xl mb-2">🤝</div>
                <div class="font-semibold text-gray-900">{{ __('docs/index.panels.partner.title') }}</div>
                <p class="text-sm text-gray-500 mt-1">{{ __('docs/index.panels.partner.description') }}</p>
            </a>
            <a href="{{ route('admin.docs.panels.marketer') }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-sm transition">
                <div class="text-2xl mb-2">📣</div>
                <div class="font-semibold text-gray-900">{{ __('docs/index.panels.marketer.title') }}</div>
                <p class="text-sm text-gray-500 mt-1">{{ __('docs/index.panels.marketer.description') }}</p>
            </a>
            <a href="{{ route('admin.docs.panels.travel') }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-sm transition">
                <div class="text-2xl mb-2">✈️</div>
                <div class="font-semibold text-gray-900">{{ __('docs/index.panels.travel.title') }}</div>
                <p class="text-sm text-gray-500 mt-1">{{ __('docs/index.panels.travel.description') }}</p>
            </a>
            <a href="{{ route('admin.docs.panels.delivery') }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-sm transition">
                <div class="text-2xl mb-2">🚴</div>
                <div class="font-semibold text-gray-900">{{ __('docs/index.panels.delivery.title') }}</div>
                <p class="text-sm text-gray-500 mt-1">{{ __('docs/index.panels.delivery.description') }}</p>
            </a>
            <a href="{{ route('admin.docs.panels.carrier') }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-sm transition">
                <div class="text-2xl mb-2">🚚</div>
                <div class="font-semibold text-gray-900">{{ __('docs/index.panels.carrier.title') }}</div>
                <p class="text-sm text-gray-500 mt-1">{{ __('docs/index.panels.carrier.description') }}</p>
            </a>
        </div>
    </div>

    {{-- Features --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('docs/index.features_heading') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('docs/index.features.orders_fulfillment') }}</h3>
                <ul class="space-y-1.5 text-sm">
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.order-lifecycle') }}">{{ __('admin.nav.docs_order_lifecycle') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.shipping') }}">{{ __('admin.nav.docs_shipping') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.warehouses') }}">{{ __('admin.nav.docs_warehouses') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.packaging') }}">{{ __('admin.nav.docs_packaging') }}</a></li>
                </ul>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('docs/index.features.marketing') }}</h3>
                <ul class="space-y-1.5 text-sm">
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.banners') }}">{{ __('admin.nav.docs_banners') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.page-builder') }}">{{ __('admin.nav.docs_page_builder') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.ad-campaigns') }}">{{ __('admin.nav.docs_ad_campaigns') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.vendor-campaigns') }}">{{ __('admin.nav.docs_vendor_campaigns') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.marketer-campaigns') }}">{{ __('admin.nav.docs_marketer_campaigns') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.influencer-deals') }}">{{ __('admin.nav.docs_influencer_deals') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.secret-promotions') }}">{{ __('admin.nav.docs_secret_promotions') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.affiliate-codes') }}">{{ __('admin.nav.docs_affiliate_codes') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.flash-sales') }}">{{ __('admin.nav.docs_flash_sales') }}</a></li>
                </ul>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('docs/index.features.finance') }}</h3>
                <ul class="space-y-1.5 text-sm">
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.payments') }}">{{ __('admin.nav.docs_payments') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.finance') }}">{{ __('admin.nav.docs_finance') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.subsidy') }}">{{ __('admin.nav.docs_subsidy') }}</a></li>
                </ul>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('docs/index.features.content') }}</h3>
                <ul class="space-y-1.5 text-sm">
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.content-pages') }}">{{ __('admin.nav.docs_content_pages') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.system-pages') }}">{{ __('admin.nav.docs_system_pages') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.radio') }}">{{ __('admin.nav.docs_radio') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.classifieds') }}">{{ __('admin.nav.docs_classifieds') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.travel') }}">{{ __('admin.nav.docs_travel') }}</a></li>
                </ul>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('docs/index.features.platform') }}</h3>
                <ul class="space-y-1.5 text-sm">
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.roles') }}">{{ __('admin.nav.docs_roles') }}</a></li>
                    <li><a class="text-primary-600 hover:underline" href="{{ route('admin.docs.features.warranties') }}">{{ __('admin.nav.docs_warranties') }}</a></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
