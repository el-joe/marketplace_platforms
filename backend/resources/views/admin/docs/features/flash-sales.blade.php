@component('admin.docs._layout', ['title' => __('docs/features/flash-sales.title'), 'icon' => '⚡', 'breadcrumb' => __('docs/features/flash-sales.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. What It Is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/flash-sales.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/flash-sales.what_it_is.p1') }}</p>
        </section>

        {{-- 2. Lifecycle --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/flash-sales.lifecycle.heading') }}</h2>
            <div class="flex flex-wrap items-center gap-2 mb-4">
                @foreach (['draft', 'scheduled', 'active'] as $status)
                    <span class="px-3 py-1.5 rounded-full bg-primary-50 text-primary-700 text-xs font-medium border border-primary-200">{{ $status }}</span>
                    @if (!$loop->last)
                        <span class="text-gray-400">&rarr;</span>
                    @endif
                @endforeach
                <span class="text-gray-400">&rarr;</span>
                <span class="px-3 py-1.5 rounded-full bg-green-50 text-green-700 text-xs font-medium border border-green-200">ended</span>
                <span class="text-gray-300">|</span>
                <span class="px-3 py-1.5 rounded-full bg-red-50 text-red-700 text-xs font-medium border border-red-200">cancelled</span>
            </div>
            <p class="text-gray-600">{{ __('docs/features/flash-sales.lifecycle.p1') }}</p>
        </section>

        {{-- 3. Vendor Participation Flow --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/flash-sales.vendor_flow.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/flash-sales.vendor_flow.step1') }} (<code>flash_sale_vendor_invititions</code> &mdash; {{ __('docs/features/flash-sales.vendor_flow.step1_note') }})</li>
                <li>{{ __('docs/features/flash-sales.vendor_flow.step2') }} (<code>/flash-sales</code>)</li>
                <li>{{ __('docs/features/flash-sales.vendor_flow.step3') }}</li>
                <li>{{ __('docs/features/flash-sales.vendor_flow.step4') }}</li>
                <li>{{ __('docs/features/flash-sales.vendor_flow.step5') }}</li>
                <li>{{ __('docs/features/flash-sales.vendor_flow.step6') }}</li>
            </ol>
        </section>

        {{-- 4. Admin Tools --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/flash-sales.admin_tools.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/flash-sales.admin_tools.stats_label') }}:</strong> {{ __('docs/features/flash-sales.admin_tools.stats_desc') }}</li>
                <li><strong>{{ __('docs/features/flash-sales.admin_tools.monitor_label') }}:</strong> {{ __('docs/features/flash-sales.admin_tools.monitor_desc') }} (<code>/live-data</code>)</li>
                <li><strong>{{ __('docs/features/flash-sales.admin_tools.analytics_label') }}:</strong> {{ __('docs/features/flash-sales.admin_tools.analytics_desc') }} (<code>/analytics-data</code>)</li>
                <li><strong>{{ __('docs/features/flash-sales.admin_tools.bulk_label') }}:</strong> {{ __('docs/features/flash-sales.admin_tools.bulk_desc') }}</li>
                <li><strong>{{ __('docs/features/flash-sales.admin_tools.history_label') }}:</strong> {{ __('docs/features/flash-sales.admin_tools.history_desc') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
