@component('admin.docs._layout', ['title' => __('docs/features/content-pages.title'), 'icon' => '📝', 'breadcrumb' => __('docs/features/content-pages.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/content-pages.what_it_is.body_prefix') }} <strong>{{ __('docs/features/content-pages.what_it_is.content_group') }}</strong> {{ __('docs/features/content-pages.what_it_is.body_suffix') }}</p>
        </section>

        {{-- Pages --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.pages.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/content-pages.pages.see') }} <a href="{{ route('admin.docs.features.page-builder') }}" class="text-primary-600 hover:underline">{{ __('docs/features/content-pages.pages.page_builder_link') }}</a> {{ __('docs/features/content-pages.pages.documentation') }}</p>
        </section>

        {{-- App Contexts --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.app_contexts.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.app-contexts.index') }}" class="text-primary-600 hover:underline">admin/app-contexts</a>: {{ __('docs/features/content-pages.app_contexts.body') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/content-pages.app_contexts.contexts') }}</li>
                <li>{{ __('docs/features/content-pages.app_contexts.each_context') }}</li>
            </ul>
        </section>

        {{-- Reviews --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.reviews.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.reviews.index') }}" class="text-primary-600 hover:underline">admin/reviews</a>: {{ __('docs/features/content-pages.reviews.body') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/content-pages.reviews.moderation') }}</li>
                <li>{{ __('docs/features/content-pages.reviews.vendor_reply') }}</li>
                <li>{{ __('docs/features/content-pages.reviews.bulk_actions') }}</li>
            </ul>
        </section>

        {{-- Blog --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.blog.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/content-pages.blog.body') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><a href="{{ route('admin.blog.categories.index') }}" class="text-primary-600 hover:underline">admin/blog/categories</a>: {{ __('docs/features/content-pages.blog.categories') }}</li>
                <li><a href="{{ route('admin.blog.posts.index') }}" class="text-primary-600 hover:underline">admin/blog/posts</a>: {{ __('docs/features/content-pages.blog.posts') }}</li>
                <li>{{ __('docs/features/content-pages.blog.attachments') }}</li>
                <li>{{ __('docs/features/content-pages.blog.view_counter') }}</li>
            </ul>
        </section>

        {{-- Knowledge Hub --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.knowledge_hub.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><a href="{{ route('admin.adsupport.collections.index') }}" class="text-primary-600 hover:underline">admin/adsupport/collections</a>: {{ __('docs/features/content-pages.knowledge_hub.collections') }}</li>
                <li><a href="{{ route('admin.adsupport.articles.index') }}" class="text-primary-600 hover:underline">admin/adsupport/articles</a>: {{ __('docs/features/content-pages.knowledge_hub.articles') }}</li>
                <li>{{ __('docs/features/content-pages.knowledge_hub.pin') }}</li>
            </ul>
        </section>

        {{-- FAQs --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.faqs.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.faqs.index') }}" class="text-primary-600 hover:underline">admin/faqs</a>: {{ __('docs/features/content-pages.faqs.body') }}</p>
        </section>

        {{-- Portal Content --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.portal.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.portal-content.index') }}" class="text-primary-600 hover:underline">admin/portal-content</a>: {{ __('docs/features/content-pages.portal.body') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/content-pages.portal.pages') }}</li>
                <li>{{ __('docs/features/content-pages.portal.editor') }}</li>
            </ul>
        </section>

        {{-- Content Settings --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.settings.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.content-settings.index') }}" class="text-primary-600 hover:underline">admin/content-settings</a>: {{ __('docs/features/content-pages.settings.body') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/content-pages.settings.types') }}</li>
                <li>{{ __('docs/features/content-pages.settings.groups') }}</li>
                <li>{{ __('docs/features/content-pages.settings.cache') }}</li>
            </ul>
        </section>

        {{-- Radio --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.radio.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/content-pages.radio.body_prefix') }} &mdash; {{ __('docs/features/content-pages.radio.see') }} <a href="{{ route('admin.docs.features.radio') }}" class="text-primary-600 hover:underline">{{ __('docs/features/content-pages.radio.link') }}</a> {{ __('docs/features/content-pages.radio.doc') }} {{ __('docs/features/content-pages.radio.customer_route') }}: <code>{country}.domain/radio/{channel}</code>.</p>
        </section>

        {{-- Who uses it / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/content-pages.rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/content-pages.rules.admin_label') }}</strong> {{ __('docs/features/content-pages.rules.admin_owns') }}</li>
                <li>{{ __('docs/features/content-pages.rules.bilingual') }}</li>
                <li>{{ __('docs/features/content-pages.rules.cache_note') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
