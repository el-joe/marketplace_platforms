@extends('layouts.admin')

@section('title', __('docs/panels/admin.title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.docs.index') }}" class="text-sm text-primary-600 hover:underline">&larr; {{ __('admin.nav.documentation') }}</a>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-3xl">🏛</span>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('docs/panels/admin.title') }}</h1>
        </div>
    </div>

    <div class="space-y-8">

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-1">{{ __('docs/panels/admin.dashboard.title') }}</h2>
            <p class="text-sm text-gray-500 mb-3">{{ __('docs/panels/admin.dashboard.purpose') }}</p>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin</code> — {{ __('docs/panels/admin.dashboard.main') }}</li>
                <li><code>/admin/analytics</code> — {{ __('docs/panels/admin.dashboard.analytics') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.catalog.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/products</code> — {{ __('docs/panels/admin.catalog.products') }}</li>
                <li><code>/admin/categories</code> — {{ __('docs/panels/admin.catalog.categories') }}</li>
                <li><code>/admin/brands</code> — {{ __('docs/panels/admin.catalog.brands') }}</li>
                <li><code>/admin/attributes</code> — {{ __('docs/panels/admin.catalog.attributes') }}</li>
                <li><code>/admin/product-highlights</code> — {{ __('docs/panels/admin.catalog.highlights') }}</li>
                <li><code>/admin/bestsellers</code> — {{ __('docs/panels/admin.catalog.bestsellers') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.vendors.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/vendor-applications</code> — {{ __('docs/panels/admin.vendors.applications') }}</li>
                <li><code>/admin/vendors</code> — {{ __('docs/panels/admin.vendors.vendors') }}</li>
                <li><code>/admin/vendor-change-requests</code> — {{ __('docs/panels/admin.vendors.change_requests') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.orders.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/orders</code> — {{ __('docs/panels/admin.orders.all') }}</li>
            </ul>
            <p class="text-sm text-gray-500 mt-3 mb-1">{{ __('docs/panels/admin.orders.actions_intro') }}</p>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li>{!! __('docs/panels/admin.orders.confirmed_processing') !!}</li>
                <li>{!! __('docs/panels/admin.orders.processing_shipped') !!}</li>
                <li>{!! __('docs/panels/admin.orders.shipped_delivered') !!}</li>
                <li>{!! __('docs/panels/admin.orders.any_cancelled') !!}</li>
                <li>{!! __('docs/panels/admin.orders.any_refund') !!}</li>
            </ul>
            <p class="text-sm text-gray-500 mt-3">{{ __('docs/panels/admin.orders.sub_order_note') }} <code>/sub-orders/{id}/next-statuses</code></p>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.finance.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/payouts</code> — {{ __('docs/panels/admin.finance.payouts') }}</li>
                <li><code>/admin/transactions</code> — {{ __('docs/panels/admin.finance.transactions') }}</li>
                <li><code>/admin/ledger</code> — {{ __('docs/panels/admin.finance.ledger') }}</li>
                <li><code>/admin/delivery/cod-settlements</code> — {{ __('docs/panels/admin.finance.cod_settlements') }}</li>
                <li><code>/admin/reports/financial</code> — {{ __('docs/panels/admin.finance.financial_reports') }}</li>
                <li><code>/admin/warranty-purchases</code> — {{ __('docs/panels/admin.finance.warranty_purchases') }}</li>
                <li><code>/admin/wallets</code> — {{ __('docs/panels/admin.finance.wallets') }}</li>
                <li><code>/admin/subscriptions</code> — {{ __('docs/panels/admin.finance.subscriptions') }}</li>
                <li><code>/admin/subscriptions/plans</code> — {{ __('docs/panels/admin.finance.subscription_plans') }}</li>
                <li><code>/admin/fbn/storage-fees</code> — {{ __('docs/panels/admin.finance.fbn_storage_fees') }}</li>
                <li><code>/admin/fbn/inbound</code> — {{ __('docs/panels/admin.finance.fbn_inbound') }}</li>
                <li><code>/admin/fbn/marketplace</code> — {{ __('docs/panels/admin.finance.fbn_marketplace') }}</li>
            </ul>
            <p class="text-sm text-gray-500 mt-3">
                {!! __('docs/panels/admin.finance.cod_lifecycle') !!}
            </p>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.customers.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/customers</code> — {{ __('docs/panels/admin.customers.customers') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.marketing.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/banners</code> — {{ __('docs/panels/admin.marketing.banners') }}</li>
                <li><code>/admin/ad-campaigns</code> — {{ __('docs/panels/admin.marketing.ad_campaigns') }}</li>
                <li><code>/admin/ad-slots</code> — {{ __('docs/panels/admin.marketing.ad_slots') }}</li>
                <li><code>/admin/paid-ad-bookings</code> — {{ __('docs/panels/admin.marketing.paid_ad_bookings') }}</li>
                <li><code>/admin/flash-sales</code> — {{ __('docs/panels/admin.marketing.flash_sales') }}</li>
                <li><code>/admin/marketers-secret-promotions</code> — {{ __('docs/panels/admin.marketing.secret_promotions') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.marketers.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/marketers</code> — {{ __('docs/panels/admin.marketers.marketers') }}</li>
                <li><code>/admin/marketer-campaigns</code> — {{ __('docs/panels/admin.marketers.marketer_campaigns') }}</li>
                <li><code>/admin/marketer-conversions</code> — {{ __('docs/panels/admin.marketers.marketer_conversions') }}</li>
                <li><code>/admin/marketer-payouts</code> — {{ __('docs/panels/admin.marketers.marketer_payouts') }}</li>
                <li><code>/admin/marketer-samples</code> — {{ __('docs/panels/admin.marketers.marketer_samples') }}</li>
                <li><code>/admin/affiliate-promo-codes</code> — {{ __('docs/panels/admin.marketers.affiliate_codes') }}</li>
                <li><code>/admin/influencer-deals</code> — {{ __('docs/panels/admin.marketers.influencer_deals') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.delivery.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/delivery/agents</code> — {{ __('docs/panels/admin.delivery.agents') }}</li>
                <li><code>/admin/delivery/zones</code> — {{ __('docs/panels/admin.delivery.zones') }}</li>
                <li><code>/admin/delivery/assignments</code> — {{ __('docs/panels/admin.delivery.assignments') }}</li>
                <li><code>/admin/delivery/payouts</code> — {{ __('docs/panels/admin.delivery.payouts') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.fulfillment.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/warehouses</code> — {{ __('docs/panels/admin.fulfillment.warehouses') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.shipping.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/shipping-zones</code> — {{ __('docs/panels/admin.shipping.zones') }}</li>
                <li><code>/admin/shipping-methods</code> — {{ __('docs/panels/admin.shipping.methods') }}</li>
                <li><code>/admin/shipping/weight-slabs</code> — {{ __('docs/panels/admin.shipping.weight_slabs') }}</li>
                <li><code>/admin/shipping-subsidies</code> — {{ __('docs/panels/admin.shipping.subsidies') }}</li>
                <li><code>/admin/warehouses/shipping-surcharges</code> — {{ __('docs/panels/admin.shipping.warehouse_surcharges') }}</li>
                <li><code>/admin/shipping-companies</code> — {{ __('docs/panels/admin.shipping.companies') }}</li>
                <li><code>/admin/carrier-claims</code> — {{ __('docs/panels/admin.shipping.carrier_claims') }}</li>
                <li><code>/admin/carrier-scorecard</code> — {{ __('docs/panels/admin.shipping.carrier_scorecard') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.support.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/support-tickets</code> — {{ __('docs/panels/admin.support.tickets') }}</li>
                <li><code>/admin/disputes</code> — {{ __('docs/panels/admin.support.disputes') }}</li>
                <li><code>/admin/warranty-claims</code> — {{ __('docs/panels/admin.support.warranty_claims') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.travel.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/travel/agencies</code> — {{ __('docs/panels/admin.travel.agencies') }}</li>
                <li><code>/admin/travel/packages</code> — {{ __('docs/panels/admin.travel.packages') }}</li>
                <li><code>/admin/travel/bookings</code> — {{ __('docs/panels/admin.travel.bookings') }}</li>
                <li><code>/admin/travel/countries</code> — {{ __('docs/panels/admin.travel.countries') }}</li>
                <li><code>/admin/travel/cities</code> — {{ __('docs/panels/admin.travel.cities') }}</li>
                <li><code>/admin/travel/inclusions</code> — {{ __('docs/panels/admin.travel.inclusions') }}</li>
                <li><code>/admin/travel/inquiries</code> — {{ __('docs/panels/admin.travel.inquiries') }}</li>
                <li><code>/admin/travel/change-requests</code> — {{ __('docs/panels/admin.travel.change_requests') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.classifieds.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/classifieds/categories</code> — {{ __('docs/panels/admin.classifieds.categories') }}</li>
                <li><code>/admin/classifieds/contract-templates</code> — {{ __('docs/panels/admin.classifieds.contract_templates') }}</li>
                <li><code>/admin/classifieds/listings</code> — {{ __('docs/panels/admin.classifieds.listings') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.content.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/page-builder</code> — {{ __('docs/panels/admin.content.page_builder') }}</li>
                <li><code>/admin/app-contexts</code> — {{ __('docs/panels/admin.content.app_contexts') }}</li>
                <li><code>/admin/reviews</code> — {{ __('docs/panels/admin.content.reviews') }}</li>
                <li><code>/admin/blog/categories</code> — {{ __('docs/panels/admin.content.blog_categories') }}</li>
                <li><code>/admin/blog/posts</code> — {{ __('docs/panels/admin.content.blog_posts') }}</li>
                <li><code>/admin/adsupport/collections</code> — {{ __('docs/panels/admin.content.adsupport_collections') }}</li>
                <li><code>/admin/adsupport/articles</code> — {{ __('docs/panels/admin.content.adsupport_articles') }}</li>
                <li><code>/admin/helpcenter/categories</code> — {{ __('docs/panels/admin.content.helpcenter_categories') }}</li>
                <li><code>/admin/helpcenter/articles</code> — {{ __('docs/panels/admin.content.helpcenter_articles') }}</li>
                <li><code>/admin/faqs</code> — {{ __('docs/panels/admin.content.faqs') }}</li>
                <li><code>/admin/portal-content</code> — {{ __('docs/panels/admin.content.portal_content') }}</li>
                <li><code>/admin/content-settings</code> — {{ __('docs/panels/admin.content.content_settings') }}</li>
                <li><code>/admin/radio</code> — {{ __('docs/panels/admin.content.radio') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.system.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/countries</code> — {{ __('docs/panels/admin.system.countries') }}</li>
                <li><code>/admin/cities</code> — {{ __('docs/panels/admin.system.cities') }}</li>
                <li><code>/admin/currencies</code> — {{ __('docs/panels/admin.system.currencies') }}</li>
                <li><code>/admin/shipping-zones</code> — {{ __('docs/panels/admin.system.shipping_zones_note') }}</li>
                <li><code>/admin/shipping-methods</code> — {{ __('docs/panels/admin.system.shipping_methods_note') }}</li>
                <li><code>/admin/vendor-document-types</code> — {{ __('docs/panels/admin.system.vendor_document_types') }}</li>
                <li><code>/admin/payment-gateways</code> — {{ __('docs/panels/admin.system.payment_gateways') }}</li>
                <li><code>/admin/payment-gateways</code> — {{ __('docs/panels/admin.system.payment_gateways') }}</li>
                <li><code>/admin/shipping/weight-slabs</code> — {{ __('docs/panels/admin.system.weight_slabs_note') }}</li>
                <li><code>/admin/shipping-subsidies</code> — {{ __('docs/panels/admin.system.shipping_subsidies_note') }}</li>
                <li><code>/admin/warehouses</code> — {{ __('docs/panels/admin.system.warehouses_note') }}</li>
                <li><code>/admin/settings</code> — {{ __('docs/panels/admin.system.settings') }}</li>
                <li><code>/admin/activity-log</code> — {{ __('docs/panels/admin.system.activity_log') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/admin.administration.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/admin/admins</code> — {{ __('docs/panels/admin.administration.admins') }}</li>
                <li><code>/admin/roles</code> — {{ __('docs/panels/admin.administration.roles') }}</li>
            </ul>
        </section>

    </div>
@endsection
