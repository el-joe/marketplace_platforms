@component('admin.docs._layout', ['title' => __('docs/features/vendor-campaigns.title'), 'icon' => '🤝', 'breadcrumb' => __('docs/features/vendor-campaigns.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. What It Is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/vendor-campaigns.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/vendor-campaigns.what_it_is.p1') }}</p>
            <p class="text-gray-600">{{ __('docs/features/vendor-campaigns.what_it_is.p2') }}</p>
        </section>

        {{-- 2. How It Works --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/vendor-campaigns.how_it_works.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/vendor-campaigns.how_it_works.step1') }}</li>
                <li>{{ __('docs/features/vendor-campaigns.how_it_works.step2') }}</li>
                <li>{{ __('docs/features/vendor-campaigns.how_it_works.step3') }}</li>
                <li>{{ __('docs/features/vendor-campaigns.how_it_works.step4') }}</li>
                <li>{{ __('docs/features/vendor-campaigns.how_it_works.step5') }}</li>
                <li>{{ __('docs/features/vendor-campaigns.how_it_works.step6') }}</li>
            </ol>
        </section>

        {{-- 3. Admin Oversight --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/vendor-campaigns.admin_oversight.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/vendor-campaigns.admin_oversight.p2') }}</p>
        </section>

        {{-- 4. Commission Resolution Priority --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/vendor-campaigns.commission_priority.heading') }}</h2>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ([__('docs/features/vendor-campaigns.commission_priority.tier1'), __('docs/features/vendor-campaigns.commission_priority.tier2'), __('docs/features/vendor-campaigns.commission_priority.tier3'), __('docs/features/vendor-campaigns.commission_priority.tier4')] as $tier)
                    <span class="px-3 py-1.5 rounded-full bg-primary-50 text-primary-700 text-xs font-medium border border-primary-200">{{ $tier }}</span>
                    @if (!$loop->last)
                        <span class="text-gray-400">&rarr;</span>
                    @endif
                @endforeach
            </div>
        </section>

    </div>

@endcomponent
