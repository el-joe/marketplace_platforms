@component('admin.docs._layout', ['title' => __('docs/features/affiliate-codes.title'), 'icon' => '🔗', 'breadcrumb' => __('docs/features/affiliate-codes.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/affiliate-codes.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/affiliate-codes.what_it_is.p1') }}</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/affiliate-codes.difference.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/affiliate-codes.difference.coupons_label') }}</strong>: {{ __('docs/features/affiliate-codes.difference.coupons_desc') }}</li>
                <li><strong>{{ __('docs/features/affiliate-codes.difference.codes_label') }}</strong>: {{ __('docs/features/affiliate-codes.difference.codes_desc') }}</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/affiliate-codes.how_it_works.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/affiliate-codes.how_it_works.step1') }}</li>
                <li>{{ __('docs/features/affiliate-codes.how_it_works.step2') }}</li>
                <li>{{ __('docs/features/affiliate-codes.how_it_works.step3') }}</li>
                <li>{{ __('docs/features/affiliate-codes.how_it_works.step4') }}</li>
                <li>{{ __('docs/features/affiliate-codes.how_it_works.step5') }}</li>
            </ol>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/affiliate-codes.admin_controls.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/affiliate-codes.admin_controls.item1') }}</li>
                <li>{{ __('docs/features/affiliate-codes.admin_controls.item2') }}</li>
                <li>{{ __('docs/features/affiliate-codes.admin_controls.item3') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
