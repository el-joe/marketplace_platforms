@component('admin.docs._layout', ['title' => __('docs/features/radio.title'), 'icon' => '📻', 'breadcrumb' => __('docs/features/radio.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/radio.what_it_is.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/radio.what_it_is.embedded') }}</li>
                <li>{{ __('docs/features/radio.what_it_is.stream_url') }}</li>
                <li>{{ __('docs/features/radio.what_it_is.admin_schedules') }}</li>
            </ul>
        </section>

        {{-- How it works --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/radio.how_it_works.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/radio.how_it_works.step1') }}</li>
                <li>{{ __('docs/features/radio.how_it_works.step2') }}</li>
                <li>{{ __('docs/features/radio.how_it_works.step3') }} <code>{country}.domain/radio/{channel}</code></li>
                <li>{{ __('docs/features/radio.how_it_works.step4') }}</li>
            </ol>
        </section>

        {{-- Admin management --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/radio.admin_management.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><a href="{{ route('admin.radio.channels.index') }}" class="text-primary-600 hover:underline">admin/radio/channels</a>: {{ __('docs/features/radio.admin_management.channel_crud') }}</li>
                <li>admin/radio/channels/{id}/schedule: {{ __('docs/features/radio.admin_management.view_schedule') }}</li>
                <li>admin/radio/channels/{id}/slots: {{ __('docs/features/radio.admin_management.edit_slots') }}</li>
                <li>{{ __('docs/features/radio.admin_management.calendar_json') }}</li>
            </ul>
        </section>

        {{-- Who uses it / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/radio.rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/radio.rules.admin_label') }}</strong> {{ __('docs/features/radio.rules.only_manager') }}</li>
                <li>{{ __('docs/features/radio.rules.per_country') }} (<code>{country}.domain</code>), {{ __('docs/features/radio.rules.not_global') }}</li>
                <li>{{ __('docs/features/radio.rules.slot_scheduling') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
