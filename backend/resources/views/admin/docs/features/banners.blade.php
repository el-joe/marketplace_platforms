@component('admin.docs._layout', ['title' => __('docs/features/banners.title'), 'icon' => '🖼️', 'breadcrumb' => __('admin.features')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. What Banners Are --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/banners.what_they_are.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/banners.what_they_are.body1') }}</p>
            <p class="text-gray-600">{{ __('docs/features/banners.what_they_are.body2') }}</p>
        </section>

        {{-- 2. Placements --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/banners.placements.heading') }}</h2>
            <p class="text-gray-600"><code>/admin/banners/placements</code> &mdash; {{ __('docs/features/banners.placements.body') }}</p>
            <p class="text-gray-700 font-medium mb-2 mt-3">{{ __('docs/features/banners.placements.examples_label') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['home_top', 'category_sidebar', 'checkout_top', 'partner_login_hero'] as $placement)
                    <span class="px-3 py-1.5 rounded-full bg-primary-50 text-primary-700 text-xs font-medium border border-primary-200">{{ $placement }}</span>
                @endforeach
            </div>
        </section>

        {{-- 3. Banner Lifecycle --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/banners.lifecycle.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/banners.lifecycle.flow') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/banners.lifecycle.active_label') }}</strong> {{ __('docs/features/banners.lifecycle.active') }}</li>
                <li><strong>{{ __('docs/features/banners.lifecycle.expired_label') }}</strong> {{ __('docs/features/banners.lifecycle.expired') }}</li>
                <li>{{ __('docs/features/banners.lifecycle.rotation') }}</li>
            </ul>
        </section>

        {{-- 4. Admin Actions --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/banners.admin_actions.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/banners.admin_actions.duplicate_label') }}</strong> {{ __('docs/features/banners.admin_actions.duplicate') }}</li>
                <li><strong>{{ __('docs/features/banners.admin_actions.bulk_label') }}</strong> {{ __('docs/features/banners.admin_actions.bulk') }}</li>
                <li><strong>{{ __('docs/features/banners.admin_actions.upload_label') }}</strong> {{ __('docs/features/banners.admin_actions.upload') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
