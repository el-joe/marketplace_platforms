@component('admin.docs._layout', ['title' => __('docs/features/classifieds.title'), 'icon' => '🏘️', 'breadcrumb' => __('docs/features/classifieds.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/classifieds.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/classifieds.what_it_is.body') }}</p>
        </section>

        {{-- How it works --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/classifieds.how_it_works.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/classifieds.how_it_works.step1') }}</li>
                <li>{{ __('docs/features/classifieds.how_it_works.step2') }}</li>
                <li>{{ __('docs/features/classifieds.how_it_works.step3') }} <code>{country}.domain/classifieds/{listingNumber}</code></li>
            </ol>
        </section>

        {{-- Admin management --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/classifieds.admin_management.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><a href="{{ route('admin.classifieds.categories.index') }}" class="text-primary-600 hover:underline">admin/classifieds/categories</a>: {{ __('docs/features/classifieds.admin_management.categories') }}</li>
                <li><a href="{{ route('admin.classifieds.contract-templates.index') }}" class="text-primary-600 hover:underline">admin/classifieds/contract-templates</a>: {{ __('docs/features/classifieds.admin_management.contract_templates') }}</li>
                <li><a href="{{ route('admin.classifieds.listings.index') }}" class="text-primary-600 hover:underline">admin/classifieds/listings</a>: {{ __('docs/features/classifieds.admin_management.listings') }}</li>
            </ul>
        </section>

        {{-- Map view --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/classifieds.map_view.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/classifieds.map_view.body') }} <code>/classifieds/map-data</code> {{ __('docs/features/classifieds.map_view.endpoint') }}</p>
        </section>

        {{-- Inquiries --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/classifieds.inquiries.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/classifieds.inquiries.body') }}</p>
        </section>

        {{-- Who uses it / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/classifieds.rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/classifieds.rules.customers_vendors_label') }}</strong> {{ __('docs/features/classifieds.rules.both_create') }} <strong>{{ __('docs/features/classifieds.rules.admin_label') }}</strong> {{ __('docs/features/classifieds.rules.sole_approver') }}</li>
                <li>{{ __('docs/features/classifieds.rules.never_visible') }}</li>
                <li>{{ __('docs/features/classifieds.rules.contract_before') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
