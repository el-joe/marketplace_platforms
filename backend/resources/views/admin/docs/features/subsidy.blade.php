@component('admin.docs._layout', ['title' => __('docs/features/subsidy.title'), 'icon' => '🎯', 'breadcrumb' => __('docs/features/subsidy.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/subsidy.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/subsidy.what_it_is.p1') }} <a href="{{ route('admin.docs.features.shipping') }}" class="text-primary-600 hover:underline">{{ __('docs/features/subsidy.what_it_is.shipping_link') }}</a> {{ __('docs/features/subsidy.what_it_is.p2') }}</p>
        </section>

        {{-- How it works --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/subsidy.how_it_works.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/subsidy.how_it_works.step1') }} <a href="{{ route('admin.shipping-zones.index') }}" class="text-primary-600 hover:underline">admin/shipping-zones</a></li>
                <li>{{ __('docs/features/subsidy.how_it_works.step2') }} <a href="{{ route('admin.shipping-subsidies.index') }}" class="text-primary-600 hover:underline">admin/shipping-subsidies</a>: <code>vendor_share</code> + <code>admin_support</code> {{ __('docs/features/subsidy.how_it_works.step2_suffix') }}</li>
                <li>{{ __('docs/features/subsidy.how_it_works.step3') }} <code>partner.domain/exceptional-zones</code></li>
                <li>{{ __('docs/features/subsidy.how_it_works.step4') }}</li>
                <li><code>vendor_share</code> {{ __('docs/features/subsidy.how_it_works.step5_a') }} <code>admin_support</code> {{ __('docs/features/subsidy.how_it_works.step5_b') }}</li>
            </ol>
        </section>

        {{-- Who uses it --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/subsidy.who_uses_it.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/subsidy.who_uses_it.admin_label') }}</strong> {{ __('docs/features/subsidy.who_uses_it.admin_desc') }}</li>
                <li><strong>{{ __('docs/features/subsidy.who_uses_it.vendor_label') }}</strong> {{ __('docs/features/subsidy.who_uses_it.vendor_desc') }}</li>
                <li><strong>{{ __('docs/features/subsidy.who_uses_it.customer_label') }}</strong> {{ __('docs/features/subsidy.who_uses_it.customer_desc') }}</li>
            </ul>
        </section>

        {{-- Key rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/subsidy.key_rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/subsidy.key_rules.rule1') }}</li>
                <li>{{ __('docs/features/subsidy.key_rules.rule2') }}</li>
                <li><code>admin_support</code> {{ __('docs/features/subsidy.key_rules.rule3') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
