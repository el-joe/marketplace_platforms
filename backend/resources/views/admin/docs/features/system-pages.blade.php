@component('admin.docs._layout', ['title' => __('docs/features/system-pages.title'), 'icon' => '⚙️', 'breadcrumb' => __('docs/features/system-pages.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/system-pages.what_it_is.body_prefix') }} <strong>{{ __('docs/features/system-pages.what_it_is.system_group') }}</strong> {{ __('docs/features/system-pages.what_it_is.body_suffix') }}</p>
        </section>

        {{-- Countries --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.countries.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.countries.index') }}" class="text-primary-600 hover:underline">admin/countries</a></p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/system-pages.countries.launching') }}</li>
                <li>{{ __('docs/features/system-pages.countries.per_country') }}</li>
                <li>{{ __('docs/features/system-pages.countries.deactivating') }}</li>
            </ul>
        </section>

        {{-- Cities --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.cities.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.cities.index') }}" class="text-primary-600 hover:underline">admin/cities</a>: {{ __('docs/features/system-pages.cities.body') }}</p>
        </section>

        {{-- Currencies --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.currencies.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.currencies.index') }}" class="text-primary-600 hover:underline">admin/currencies</a>: {{ __('docs/features/system-pages.currencies.body') }} (SAR, AED, OMR, KWD, QAR, BHD, EGP, JOD).</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/system-pages.currencies.override') }}</li>
            </ul>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-2 text-gray-700 text-sm">
                {{ __('docs/features/system-pages.currencies.bigint_note') }}
            </div>
        </section>

        {{-- Shipping Zones / Methods --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.shipping_zones.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/system-pages.shipping_zones.see') }} <a href="{{ route('admin.docs.features.shipping') }}" class="text-primary-600 hover:underline">{{ __('docs/features/system-pages.shipping_zones.link') }}</a> {{ __('docs/features/system-pages.shipping_zones.documentation') }}</p>
        </section>

        {{-- Document Types --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.document_types.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.vendor-document-types.index') }}" class="text-primary-600 hover:underline">admin/vendor-document-types</a>: {{ __('docs/features/system-pages.document_types.body') }}</p>
        </section>

        {{-- Payment Methods --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.payment_gateways.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.payment-gateways.index') }}" class="text-primary-600 hover:underline">admin/payment-gateways</a>: {{ __('docs/features/system-pages.payment_gateways.body') }}</p>
        </section>

        {{-- Payment Gateways --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.payment_gateways.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.payment-gateways.index') }}" class="text-primary-600 hover:underline">admin/payment-gateways</a>: {{ __('docs/features/system-pages.payment_gateways.body') }} (Thawani, Stripe, {{ __('docs/features/system-pages.payment_gateways.etc') }}) {{ __('docs/features/system-pages.payment_gateways.strategy') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/system-pages.payment_gateways.test_connection') }}</li>
                <li>{{ __('docs/features/system-pages.payment_gateways.webhook_logs') }}</li>
            </ul>
        </section>

        {{-- Weight Slabs --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.weight_slabs.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/system-pages.weight_slabs.body') }} &mdash; {{ __('docs/features/system-pages.weight_slabs.see') }} <a href="{{ route('admin.docs.features.shipping') }}" class="text-primary-600 hover:underline">{{ __('docs/features/system-pages.weight_slabs.link') }}</a> {{ __('docs/features/system-pages.weight_slabs.docs') }}</p>
        </section>

        {{-- Shipping Subsidies --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.subsidies.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/system-pages.subsidies.body') }} &mdash; {{ __('docs/features/system-pages.subsidies.see') }} <a href="{{ route('admin.docs.features.subsidy') }}" class="text-primary-600 hover:underline">{{ __('docs/features/system-pages.subsidies.link') }}</a> {{ __('docs/features/system-pages.subsidies.docs') }}</p>
        </section>

        {{-- Warehouses --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.warehouses.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/system-pages.warehouses.see') }} <a href="{{ route('admin.docs.features.warehouses') }}" class="text-primary-600 hover:underline">{{ __('docs/features/system-pages.warehouses.link') }}</a> {{ __('docs/features/system-pages.warehouses.documentation') }}</p>
        </section>

        {{-- Inventory Transfers --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.transfers.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.warehouses.transfers.index') }}" class="text-primary-600 hover:underline">admin/warehouses/transfers</a>: {{ __('docs/features/system-pages.transfers.body') }}</p>
            <p class="text-gray-600 font-mono text-sm bg-gray-50 border border-gray-200 rounded-lg p-3">pending &rarr; in_transit &rarr; received | cancelled</p>
        </section>

        {{-- Shipping Surcharges --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.surcharges.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.warehouses.shipping-surcharges.index') }}" class="text-primary-600 hover:underline">admin/warehouses/shipping-surcharges</a>: {{ __('docs/features/system-pages.surcharges.body') }}</p>
        </section>

        {{-- Activity Log --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.activity_log.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.activity-log.index') }}" class="text-primary-600 hover:underline">admin/activity-log</a>: {{ __('docs/features/system-pages.activity_log.body') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/system-pages.activity_log.filter') }}</li>
                <li>{{ __('docs/features/system-pages.activity_log.shows') }}</li>
            </ul>
        </section>

        {{-- Who uses it / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/system-pages.rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/system-pages.rules.admin_only') }}</strong> &mdash; {{ __('docs/features/system-pages.rules.not_exposed') }}</li>
                <li>{{ __('docs/features/system-pages.rules.no_retroactive') }}</li>
                <li>{{ __('docs/features/system-pages.rules.immutable') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
