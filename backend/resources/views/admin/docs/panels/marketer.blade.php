@extends('layouts.admin')

@section('title', 'Marketer Panel')

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.docs.index') }}" class="text-sm text-primary-600 hover:underline">&larr; {{ __('admin.nav.documentation') }}</a>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-3xl">📣</span>
            <h1 class="text-2xl font-bold text-gray-900">Marketer Panel</h1>
        </div>
        <p class="text-sm text-gray-500 mt-2">How the marketer (influencer) panel works: campaigns, conversions and payouts.</p>
    </div>

    <div class="space-y-8">
        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Campaigns</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li><code>/campaigns</code> — marketer campaign invitations and applications.</li>
                <li>Marketers accept invitations from admins and pick vendor listings to promote.</li>
            </ul>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Conversions &amp; payouts</h2>
            <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700">
                <li>Conversion tracking and commission accrual are reviewed by admins under <code>marketer-campaigns</code>.</li>
                <li>See the finance doc's marketer payouts section for the admin-side flow.</li>
            </ul>
        </section>
    </div>
@endsection
