@component('admin.docs._layout', ['title' => __('docs/features/marketer-campaigns.title'), 'icon' => '📣'])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. What It Is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/marketer-campaigns.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/marketer-campaigns.what_it_is.body') }}</p>
        </section>

        {{-- 2. Commission Types --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/marketer-campaigns.commission_types.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/marketer-campaigns.commission_types.percentage') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.commission_types.flat_per_conversion') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.commission_types.flat_per_click') }}</li>
            </ul>
        </section>

        {{-- 3. Attribution Models --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/marketer-campaigns.attribution.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/marketer-campaigns.attribution.last_click') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.attribution.first_click') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.attribution.linear') }}</li>
            </ul>
        </section>

        {{-- 4. Conversion Lifecycle --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/marketer-campaigns.conversion_lifecycle.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/marketer-campaigns.conversion_lifecycle.placed') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.conversion_lifecycle.approved') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.conversion_lifecycle.payout') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.conversion_lifecycle.reversed') }}</li>
            </ol>
        </section>

        {{-- 5. Budget Exhaustion Guard --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/marketer-campaigns.budget_guard.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/marketer-campaigns.budget_guard.body') }}</p>
            <p class="text-gray-600">{{ __('docs/features/marketer-campaigns.budget_guard.note') }}</p>
        </section>

        {{-- 6. Campaign Types Available --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/marketer-campaigns.campaign_types.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/marketer-campaigns.campaign_types.product_label') }}</strong> {{ __('docs/features/marketer-campaigns.campaign_types.product') }}</li>
                <li><strong>{{ __('docs/features/marketer-campaigns.campaign_types.classified_label') }}</strong> {{ __('docs/features/marketer-campaigns.campaign_types.classified') }}</li>
                <li><strong>{{ __('docs/features/marketer-campaigns.campaign_types.travel_label') }}</strong> {{ __('docs/features/marketer-campaigns.campaign_types.travel') }}</li>
            </ul>
        </section>

        {{-- 7. Admin Actions --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/marketer-campaigns.admin_actions.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/marketer-campaigns.admin_actions.approve_label') }}</strong> {{ __('docs/features/marketer-campaigns.admin_actions.approve') }}</li>
                <li><strong>{{ __('docs/features/marketer-campaigns.admin_actions.reject_label') }}</strong> {{ __('docs/features/marketer-campaigns.admin_actions.reject') }}</li>
                <li><strong>{{ __('docs/features/marketer-campaigns.admin_actions.approve_pause_label') }}</strong> {{ __('docs/features/marketer-campaigns.admin_actions.approve_pause') }}</li>
                <li><strong>{{ __('docs/features/marketer-campaigns.admin_actions.dismiss_pause_label') }}</strong> {{ __('docs/features/marketer-campaigns.admin_actions.dismiss_pause') }}</li>
            </ul>
        </section>

        {{-- 8. Payout Calculation --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/marketer-campaigns.payout_calc.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/marketer-campaigns.payout_calc.gross') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.payout_calc.net') }}</li>
                <li>{{ __('docs/features/marketer-campaigns.payout_calc.grouping') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
