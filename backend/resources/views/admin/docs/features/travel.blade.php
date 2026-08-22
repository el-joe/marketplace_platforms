@component('admin.docs._layout', ['title' => __('docs/features/travel.title'), 'icon' => '✈️', 'breadcrumb' => __('docs/features/travel.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- Overview --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/travel.overview.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/travel.overview.body') }} <code>{country}.domain/travel</code>.</p>
        </section>

        {{-- Package flow --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/travel.package_flow.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/travel.package_flow.step1') }}</li>
                <li>{{ __('docs/features/travel.package_flow.step2') }}</li>
                <li>{{ __('docs/features/travel.package_flow.step3') }}</li>
                <li>{{ __('docs/features/travel.package_flow.step4') }}</li>
            </ol>
        </section>

        {{-- Booking statuses --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/travel.booking_statuses.heading') }}</h2>
            <p class="text-gray-600 font-mono text-sm bg-gray-50 border border-gray-200 rounded-lg p-3">
                pending_documents &rarr; confirmed &rarr; completed | cancelled
            </p>
        </section>

        {{-- Admin controls --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/travel.admin_controls.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><a href="{{ route('admin.travel.agencies.index') }}" class="text-primary-600 hover:underline">admin/travel/agencies</a>: {{ __('docs/features/travel.admin_controls.agencies') }}</li>
                <li><a href="{{ route('admin.travel.packages.index') }}" class="text-primary-600 hover:underline">admin/travel/packages</a>: {{ __('docs/features/travel.admin_controls.packages') }}</li>
                <li><a href="{{ route('admin.travel.bookings.index') }}" class="text-primary-600 hover:underline">admin/travel/bookings</a>: {{ __('docs/features/travel.admin_controls.bookings') }}</li>
                <li>
                    <a href="{{ route('admin.travel.countries.index') }}" class="text-primary-600 hover:underline">admin/travel/countries</a>
                    + <a href="{{ route('admin.travel.cities.index') }}" class="text-primary-600 hover:underline">/cities</a>
                    + <a href="{{ route('admin.travel.inclusions.index') }}" class="text-primary-600 hover:underline">/inclusions</a>: {{ __('docs/features/travel.admin_controls.catalog') }}
                </li>
                <li><a href="{{ route('admin.travel.change-requests.index') }}" class="text-primary-600 hover:underline">admin/travel/change-requests</a>: {{ __('docs/features/travel.admin_controls.change_requests') }}</li>
            </ul>
        </section>

        {{-- Who uses it / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/travel.rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/travel.rules.agencies_label') }}</strong> {{ __('docs/features/travel.rules.agencies_create') }} <strong>{{ __('docs/features/travel.rules.admin_label') }}</strong> {{ __('docs/features/travel.rules.sole_approver') }}</li>
                <li>{{ __('docs/features/travel.rules.locked_sections') }}</li>
                <li>{{ __('docs/features/travel.rules.booking_completed') }} <code>completed</code> {{ __('docs/features/travel.rules.without_first') }} <code>confirmed</code>.</li>
            </ul>
        </section>

    </div>

@endcomponent
