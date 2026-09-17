@component('admin.docs._layout', ['title' => __('docs/features/secret-promotions.title'), 'icon' => '🤫', 'breadcrumb' => __('docs/features/secret-promotions.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/secret-promotions.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/secret-promotions.what_it_is.p1') }}</p>
            <p class="text-gray-600">{{ __('docs/features/secret-promotions.what_it_is.p2') }}</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/secret-promotions.security_rule.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/secret-promotions.security_rule.p1') }}</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/secret-promotions.how_it_works.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/secret-promotions.how_it_works.step1') }} ({{ __('docs/features/secret-promotions.how_it_works.step1_fields') }})</li>
                <li>{{ __('docs/features/secret-promotions.how_it_works.step2') }}</li>
                <li>{{ __('docs/features/secret-promotions.how_it_works.step3') }}</li>
                <li>{{ __('docs/features/secret-promotions.how_it_works.step4') }}</li>
            </ol>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/secret-promotions.statuses.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/secret-promotions.statuses.p1') }}</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/secret-promotions.marketer_view.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/secret-promotions.marketer_view.p1') }}</p>
            <p class="text-gray-600">{{ __('docs/features/secret-promotions.marketer_view.p2') }}</p>
            <p class="text-gray-600">{{ __('docs/features/secret-promotions.marketer_view.p3') }}</p>
        </section>

    </div>

@endcomponent
