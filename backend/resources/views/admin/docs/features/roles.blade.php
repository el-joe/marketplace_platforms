@component('admin.docs._layout', ['title' => __('docs/features/roles.title'), 'icon' => '🛡️', 'breadcrumb' => __('docs/features/roles.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/roles.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/roles.what_it_is.body') }}</p>
        </section>

        {{-- Architecture --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/roles.architecture.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{!! __('docs/features/roles.architecture.guards') !!}: <code>admin</code>, <code>vendor</code>, <code>travel_agency</code>, <code>marketer</code>, <code>delivery_agent</code>, <code>shipping_company_supervisor</code></li>
                <li>{{ __('docs/features/roles.architecture.per_guard') }} &mdash; <code>guard_name</code> {{ __('docs/features/roles.architecture.must_match') }}</li>
                <li><code>model_has_roles</code> {{ __('docs/features/roles.architecture.model_uuid') }} <code>model_uuid</code> (char 36), {{ __('docs/features/roles.architecture.not_default') }} <code>model_id</code></li>
            </ul>
        </section>

        {{-- Admin roles --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/roles.admin_roles.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/roles.admin_roles.managed_at') }} <a href="{{ route('admin.roles.index') }}" class="text-primary-600 hover:underline">admin/roles</a>.</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/roles.admin_roles.system_roles') }} (<code>super_admin</code>, <code>operations_admin</code>, {{ __('docs/features/roles.admin_roles.etc') }})</li>
                <li>{{ __('docs/features/roles.admin_roles.custom_roles') }}</li>
                <li>{{ __('docs/features/roles.admin_roles.permission_format') }}: <code>resource.action</code> ({{ __('docs/features/roles.admin_roles.eg') }} <code>orders.view</code>, <code>vendors.approve</code>)</li>
            </ul>
        </section>

        {{-- Vendor roles --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/roles.vendor_roles.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/roles.vendor_roles.scoped') }} <code>"{vendor_id}::role-name"</code></li>
                <li>{{ __('docs/features/roles.vendor_roles.system_roles') }}: <code>vendor_owner</code>, <code>vendor_manager</code>, <code>vendor_staff</code></li>
                <li>{{ __('docs/features/roles.vendor_roles.custom_roles') }} (<code>/roles</code>)</li>
                <li>{{ __('docs/features/roles.vendor_roles.applied_via') }} <code>vendor.can</code> {{ __('docs/features/roles.vendor_roles.middleware') }}</li>
            </ul>
        </section>

        {{-- Travel agency roles --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/roles.agency_roles.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/roles.agency_roles.scoped') }} <code>"{travel_agency_id}::role-name"</code></li>
                <li>{{ __('docs/features/roles.agency_roles.system_roles') }}: <code>agency_owner</code>, <code>agency_manager</code>, <code>agency_staff</code></li>
                <li>{{ __('docs/features/roles.agency_roles.owner_bypass') }} (<code>TravelAgency</code> {{ __('docs/features/roles.agency_roles.model') }})</li>
                <li>{{ __('docs/features/roles.agency_roles.members') }} (<code>TravelAgencyMember</code>) {{ __('docs/features/roles.agency_roles.checked_via') }}</li>
            </ul>
        </section>

        {{-- Restriction permission --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/roles.restriction.heading') }}: <code>vendors.assigned_only</code></h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/roles.restriction.description') }}</li>
                <li>{{ __('docs/features/roles.restriction.assigned_manually') }}</li>
                <li>{{ __('docs/features/roles.restriction.hides_financial') }}</li>
            </ul>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2 text-amber-800 text-sm">
                {{ __('docs/features/roles.restriction.never_granted') }} <code>super_admin</code> &mdash; {{ __('docs/features/roles.restriction.privilege_note') }}
            </div>
        </section>

        {{-- Who uses it / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/roles.rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/roles.rules.every_panel') }}</li>
                <li>{{ __('docs/features/roles.rules.protected') }}</li>
                <li>{{ __('docs/features/roles.rules.isolation') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
