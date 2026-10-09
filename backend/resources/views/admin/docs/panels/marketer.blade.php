@extends('layouts.admin')

@section('title', 'Marketer Panel')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.docs.index') }}" class="text-sm text-primary-600 hover:underline">&larr; {{ __('admin.nav.documentation') }}</a>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-3xl">📣</span>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.static_text.admin_docs_panels_marketer.marketer_panel') }}</h1>
        </div>
        <p class="text-sm text-gray-500 mt-2">{{ __('admin.static_text.admin_docs_panels_marketer.how_the_marketer_influencer_panel_works') }}</p>
    </div>

    <div class="space-y-8">
        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Campaigns</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/campaigns</code> {{ __('admin.static_text.admin_docs_panels_marketer.marketer_campaign_invitations_and_applic') }}</li>
                <li>{{ __('admin.static_text.admin_docs_panels_marketer.marketers_accept_invitations_from_admins') }}</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Conversions &amp; payouts</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li>{{ __('admin.static_text.admin_docs_panels_marketer.conversion_tracking_and_commission_accru') }} <code>marketer-campaigns</code>.</li>
                <li>{{ __('admin.static_text.admin_docs_panels_marketer.see_the_finance_doc_s_marketer') }}</li>
            </ul>
        </section>
    </div>
@endsection
