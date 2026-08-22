@extends('layouts.admin')

@section('title', __('docs/panels/delivery.title'))

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.docs.index') }}" class="text-sm text-primary-600 hover:underline">&larr; {{ __('admin.nav.documentation') }}</a>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-3xl">🚴</span>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('docs/panels/delivery.title') }}</h1>
        </div>
        <p class="text-sm text-gray-500 mt-2">
            {!! __('docs/panels/delivery.meta') !!}
        </p>
    </div>

    <div class="space-y-8">

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/delivery.dashboard.title') }}</h2>
            <p class="text-sm text-gray-700">{{ __('docs/panels/delivery.dashboard.summary') }}</p>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/delivery.assignments.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/assignments</code> — {{ __('docs/panels/delivery.assignments.assignments') }}</li>
                <li>{!! __('docs/panels/delivery.assignments.actions') !!}</li>
                <li>{{ __('docs/panels/delivery.assignments.otp') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{!! __('docs/panels/delivery.location.title') !!}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/location</code> — {{ __('docs/panels/delivery.location.location') }}</li>
                <li><code>/availability</code> — {{ __('docs/panels/delivery.location.availability') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/delivery.earnings.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/earnings</code> — {{ __('docs/panels/delivery.earnings.history') }}</li>
                <li><code>/earnings/summary</code> — {{ __('docs/panels/delivery.earnings.summary') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/delivery.wallet.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/wallet</code> — {{ __('docs/panels/delivery.wallet.balance') }}</li>
                <li>{{ __('docs/panels/delivery.wallet.withdraw') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/delivery.cod_settlements.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/cod-settlements</code> — {{ __('docs/panels/delivery.cod_settlements.settlements') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">{{ __('docs/panels/delivery.profile.title') }}</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/profile</code> — {{ __('docs/panels/delivery.profile.profile') }}</li>
            </ul>
        </section>

    </div>
@endsection
