@component('admin.docs._layout', ['title' => __('docs/features/warranties.title'), 'icon' => '🛡️', 'breadcrumb' => __('docs/features/warranties.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warranties.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/warranties.what_it_is.body') }}</p>
        </section>

        {{-- Standard warranty --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warranties.standard.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/warranties.standard.provided_by') }} <code>warranty_months</code> {{ __('docs/features/warranties.standard.on_product') }}</li>
                <li>{{ __('docs/features/warranties.standard.claims_by_prefix') }} <strong>{{ __('docs/features/warranties.standard.customer') }}</strong> {{ __('docs/features/warranties.standard.claims_by_suffix') }}</li>
                <li>{{ __('docs/features/warranties.standard.vendors_prefix') }} <strong>{{ __('docs/features/warranties.standard.cannot') }}</strong> {{ __('docs/features/warranties.standard.vendors_suffix') }}</li>
            </ul>
        </section>

        {{-- Extended warranty plans --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warranties.extended.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/warranties.extended.admin_creates') }} <a href="{{ route('admin.warranty-plans.index') }}" class="text-primary-600 hover:underline">admin/warranty-plans</a>, {{ __('docs/features/warranties.extended.scoped') }}</li>
                <li>{{ __('docs/features/warranties.extended.displayed') }}</li>
                <li>{{ __('docs/features/warranties.extended.payment_goes') }}</li>
                <li>{{ __('docs/features/warranties.extended.after_delivery') }}: <code>warranty_purchases.status = 'active'</code></li>
            </ul>
        </section>

        {{-- Claim flow --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warranties.claim_flow.heading') }}</h2>
            <p class="text-gray-600 font-mono text-sm bg-gray-50 border border-gray-200 rounded-lg p-3">
                submitted &rarr; under_review &rarr; approved | rejected &rarr; resolved
            </p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/warranties.claim_flow.resolution_prefix') }} <strong>{{ __('docs/features/warranties.claim_flow.repair') }}</strong>, <strong>{{ __('docs/features/warranties.claim_flow.replace') }}</strong>, <strong>{{ __('docs/features/warranties.claim_flow.refund') }}</strong></li>
                <li>{{ __('docs/features/warranties.claim_flow.message_thread') }}</li>
            </ul>
        </section>

        {{-- Admin view --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warranties.admin_view.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><a href="{{ route('admin.warranty-claims.index') }}" class="text-primary-600 hover:underline">admin/warranty-claims</a>: {{ __('docs/features/warranties.admin_view.claims') }}</li>
                <li><a href="{{ route('admin.warranty-purchases.index') }}" class="text-primary-600 hover:underline">admin/warranty-purchases</a>: {{ __('docs/features/warranties.admin_view.purchases') }}</li>
            </ul>
        </section>

        {{-- Who uses it / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warranties.rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/warranties.rules.customers_label') }}</strong> {{ __('docs/features/warranties.rules.customers') }}</li>
                <li><strong>{{ __('docs/features/warranties.rules.vendors_label') }}</strong> {{ __('docs/features/warranties.rules.vendors') }}</li>
                <li><strong>{{ __('docs/features/warranties.rules.admin_label') }}</strong> {{ __('docs/features/warranties.rules.admin') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
