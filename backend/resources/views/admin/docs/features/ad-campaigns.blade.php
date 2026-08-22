@component('admin.docs._layout', ['title' => __('docs/features/ad-campaigns.title'), 'icon' => '📊', 'breadcrumb' => __('admin.features')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. What It Is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/ad-campaigns.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/ad-campaigns.what_it_is.body') }}</p>
        </section>

        {{-- 2. How It Works --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/ad-campaigns.how_it_works.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/ad-campaigns.how_it_works.create') }}</li>
                <li>{{ __('docs/features/ad-campaigns.how_it_works.submitted') }}</li>
                <li>{{ __('docs/features/ad-campaigns.how_it_works.review') }}</li>
                <li>{{ __('docs/features/ad-campaigns.how_it_works.live') }}</li>
                <li>{{ __('docs/features/ad-campaigns.how_it_works.pause') }}</li>
                <li>{{ __('docs/features/ad-campaigns.how_it_works.exhausted') }}</li>
            </ol>
        </section>

        {{-- 3. Ad Slots --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/ad-campaigns.ad_slots.heading') }}</h2>
            <p class="text-gray-600"><code>/admin/ad-slots</code> &mdash; {{ __('docs/features/ad-campaigns.ad_slots.body1') }}</p>
            <p class="text-gray-600">{{ __('docs/features/ad-campaigns.ad_slots.body2') }}</p>
        </section>

        {{-- 4. Paid Ad Bookings --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/ad-campaigns.paid_bookings.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/ad-campaigns.paid_bookings.body1') }}</p>
            <p class="text-gray-600"><code>/admin/paid-ad-bookings</code> &mdash; {{ __('docs/features/ad-campaigns.paid_bookings.body2') }}</p>
            <p class="text-gray-600">{{ __('docs/features/ad-campaigns.paid_bookings.status') }} <code>pending</code> &rarr; <code>approved</code> | <code>rejected</code></p>
        </section>

        {{-- 5. Fraud Alerts --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/ad-campaigns.fraud_alerts.heading') }}</h2>
            <p class="text-gray-600"><code>/admin/ad-campaigns/fraud</code> &mdash; {{ __('docs/features/ad-campaigns.fraud_alerts.body') }}</p>
        </section>

    </div>

@endcomponent
