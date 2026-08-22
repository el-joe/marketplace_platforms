@component('admin.docs._layout', ['title' => __('docs/features/packaging.title'), 'icon' => '📦', 'breadcrumb' => __('docs/features/packaging.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/packaging.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/packaging.what_it_is.p1') }}</p>
        </section>

        {{-- How it works: Admin --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/packaging.admin_side.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.packaging.catalog') }}" class="text-primary-600 hover:underline">admin/packaging</a></p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/packaging.admin_side.catalog_label') }}:</strong> {{ __('docs/features/packaging.admin_side.catalog_desc') }}</li>
                <li><strong>{{ __('docs/features/packaging.admin_side.orders_label') }}:</strong> {{ __('docs/features/packaging.admin_side.orders_desc') }}</li>
            </ul>
        </section>

        {{-- How it works: Vendor --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/packaging.vendor_side.heading') }}</h2>
            <p class="text-gray-600"><code>partner.domain/packaging</code></p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/packaging.vendor_side.catalog_label') }}: {{ __('docs/features/packaging.vendor_side.catalog_desc') }}</li>
                <li>{{ __('docs/features/packaging.vendor_side.cart_label') }}: {{ __('docs/features/packaging.vendor_side.cart_desc') }}</li>
                <li>{{ __('docs/features/packaging.vendor_side.order_flow') }}: <code>pending &rarr; approved &rarr; shipped &rarr; delivered</code></li>
            </ul>
        </section>

        {{-- Delivery fee --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/packaging.delivery_fee.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/packaging.delivery_fee.p1') }} <code>'packaging_delivery_fee_{country_code}'</code> ({{ __('docs/features/packaging.delivery_fee.p2') }}). {{ __('docs/features/packaging.delivery_fee.p3') }} <a href="{{ route('admin.content-settings.index') }}" class="text-primary-600 hover:underline">admin/content-settings</a>.</p>
        </section>

        {{-- Stock snapshot --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/packaging.stock_snapshot.heading') }}</h2>
            <p class="text-gray-600"><code>unit_cost</code> {{ __('docs/features/packaging.stock_snapshot.p1') }} <code>request_items.unit_cost</code>. {{ __('docs/features/packaging.stock_snapshot.p2') }}</p>
        </section>

        {{-- Who uses it / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/packaging.who_rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/packaging.who_rules.admin_label') }}</strong> {{ __('docs/features/packaging.who_rules.admin_desc') }}</li>
                <li><strong>{{ __('docs/features/packaging.who_rules.vendors_label') }}</strong> {{ __('docs/features/packaging.who_rules.vendors_desc') }}</li>
                <li>{{ __('docs/features/packaging.who_rules.rule3') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
