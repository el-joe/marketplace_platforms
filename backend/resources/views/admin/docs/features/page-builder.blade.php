@component('admin.docs._layout', ['title' => __('docs/features/page-builder.title'), 'icon' => '🏗️', 'breadcrumb' => __('admin.features')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. What It Is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/page-builder.what_it_is.body1') }}</p>
            <p class="text-gray-600">{{ __('docs/features/page-builder.what_it_is.body2') }}</p>
        </section>

        {{-- 2. How It Works --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.how_it_works.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/page-builder.how_it_works.open') }}</li>
                <li>{{ __('docs/features/page-builder.how_it_works.select') }}</li>
                <li>{{ __('docs/features/page-builder.how_it_works.add_blocks') }}</li>
                <li>{{ __('docs/features/page-builder.how_it_works.configure') }}</li>
                <li>{{ __('docs/features/page-builder.how_it_works.publish') }}</li>
            </ol>
        </section>

        {{-- 3. Block Types --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.block_types.heading') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left text-gray-600 border border-gray-200 rounded-lg overflow-hidden">
                    <thead class="bg-gray-50 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('docs/features/page-builder.block_types.block_type') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('docs/features/page-builder.block_types.purpose') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('docs/features/page-builder.block_types.content_sources') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr><td class="px-4 py-2"><code>hero_slider</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.hero_slider_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.hero_slider_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>ad_images</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.ad_images_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.ad_images_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>category_pills</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.category_pills_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.category_pills_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>brand_strip</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.brand_strip_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.brand_strip_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>product_grid</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.product_grid_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.product_grid_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>product_carousel</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.product_carousel_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.product_carousel_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>flash_sale_timer</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.flash_sale_timer_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.flash_sale_timer_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>featured_vendor</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.featured_vendor_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.featured_vendor_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>custom_html</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.custom_html_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.custom_html_sources') }}</td></tr>
                        <tr><td class="px-4 py-2"><code>banner_single</code></td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.banner_single_purpose') }}</td><td class="px-4 py-2">{{ __('docs/features/page-builder.block_types.banner_single_sources') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 4. Slides Management --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.slides.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/page-builder.slides.body1') }}</p>
            <p class="text-gray-600">{{ __('docs/features/page-builder.slides.body2') }}</p>
        </section>

        {{-- 5. Ad Images --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.ad_images.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/page-builder.ad_images.body') }}</p>
        </section>

        {{-- 6. Product Pickers --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.product_pickers.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/page-builder.product_pickers.body') }}</p>
        </section>

        {{-- 7. Revisions & Restore --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.revisions.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/page-builder.revisions.body') }}</p>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2 text-amber-800 text-sm">
                {{ __('docs/features/page-builder.revisions.note') }}
            </div>
        </section>

        {{-- 8. Block Visibility --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.visibility.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/page-builder.visibility.body') }}</p>
        </section>

        {{-- 9. Block Analytics --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/page-builder.analytics.heading') }}</h2>
            <p class="text-gray-600"><code>/admin/page-builder/blocks/{block}/analytics</code> &mdash; {{ __('docs/features/page-builder.analytics.body') }}</p>
        </section>

    </div>

@endcomponent
