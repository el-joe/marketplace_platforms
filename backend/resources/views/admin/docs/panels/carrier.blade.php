@extends('layouts.admin')

@section('title', __('docs/panels/carrier.title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.docs.index') }}" class="text-sm text-primary-600 hover:underline">&larr; {{ __('admin.nav.documentation') }}</a>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-3xl">🚚</span>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('docs/panels/carrier.title') }}</h1>
        </div>
        <p class="text-sm text-gray-500 mt-2">
            {!! __('docs/panels/carrier.meta') !!}
        </p>
    </div>

    <div class="space-y-8">

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/carrier.dashboard.title') }}</h2>
            <p class="text-sm text-gray-700">{{ __('docs/panels/carrier.dashboard.summary') }}</p>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/carrier.agents.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/agents</code> — {{ __('docs/panels/carrier.agents.agents') }}</li>
                <li>{{ __('docs/panels/carrier.agents.unlimited') }}</li>
                <li>{{ __('docs/panels/carrier.agents.toggle') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/carrier.supervisors.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/supervisors</code> — {{ __('docs/panels/carrier.supervisors.supervisors') }}</li>
                <li>{{ __('docs/panels/carrier.supervisors.owner_only') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/carrier.assignments.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/assignments/unassigned</code> — {{ __('docs/panels/carrier.assignments.unassigned') }}</li>
                <li>{{ __('docs/panels/carrier.assignments.assign') }}</li>
                <li><code>/assignments</code> — {{ __('docs/panels/carrier.assignments.all') }}</li>
                <li><code>/assignments/{id}</code> — {{ __('docs/panels/carrier.assignments.detail') }}</li>
            </ul>
        </section>

    </div>
@endsection
