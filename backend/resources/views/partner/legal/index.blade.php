@extends('layouts.partner')
@section('title', __('partner.legal.title'))
@section('page-title', __('partner.legal.title'))

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($country)
        <p class="text-xs text-gray-500">{{ __('contracts.partner.agreement_country', ['country' => app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en]) }}</p>
    @endif
    @if (! $agreementConfigured && $country && \App\Models\ClassifiedContractTemplate::query()->where('category_scope', 'platform')->where('is_published', true)->exists())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ __('contracts.api.not_configured', ['country' => app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en]) }}
        </div>
    @endif

    @if ($upcoming)
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            <p class="font-semibold">{{ __('partner.legal.upcoming_title', ['version' => $upcoming->version, 'date' => $upcoming->effective_at->format('d/m/Y')]) }}</p>
            <p class="mt-1">{{ __('partner.legal.upcoming_body') }}</p>
            @if ($upcoming->change_summary)
                <p class="mt-2 whitespace-pre-line text-xs">{{ $upcoming->change_summary }}</p>
            @endif
        </div>
    @endif

    @if ($pending)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ __('partner.legal.pending_notice') }}
            <a href="{{ route('partner.legal.agreement') }}" class="font-semibold underline">{{ __('partner.legal.review_and_accept') }}</a>
        </div>
    @endif

    <section class="space-y-4 rounded-2xl bg-white p-6 shadow-sm" x-data="{ lang: 'ar' }">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900">{{ __('partner.legal.agreement_in_force') }}</h2>
                @if ($inForce)
                    <p class="mt-1 text-xs text-gray-500">{{ $inForce->name }} · v{{ $inForce->version }}</p>
                @endif
            </div>
            @if ($inForceDoc)
                <div class="flex gap-2">
                    <button type="button" @click="lang = 'en'" :class="lang === 'en' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded px-3 py-1 text-xs font-medium">EN</button>
                    <button type="button" @click="lang = 'ar'" :class="lang === 'ar' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'" class="rounded px-3 py-1 text-xs font-medium">AR</button>
                </div>
            @endif
        </div>

        @if ($signature)
            <div class="grid gap-2 rounded-lg bg-gray-50 p-4 text-sm text-gray-700 sm:grid-cols-2">
                <p>{{ __('partner.legal.signed_version') }}: <strong>v{{ $signature->template_version }}</strong></p>
                <p>{{ __('partner.legal.signed_on') }}: <strong>{{ format_tz(vendor_tz($signature->signed_at)) }}</strong></p>
                <p>{{ __('partner.legal.signed_by') }}: <strong>{{ $signature->signer_name }}</strong></p>
                <p>{{ __('partner.legal.method') }}: <strong>{{ __('partner.legal.method_' . $signature->acceptance_method) }}</strong></p>
            </div>
            <div class="flex flex-wrap gap-3 text-sm">
                @if ($signature->hasSignedFile())
                    <a href="{{ route('partner.legal.signed-file', $signature) }}" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">{{ __('partner.legal.download_signed_copy') }}</a>
                @else
                    <a href="{{ route('partner.contracts.signed', $signature) }}" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">{{ __('partner.legal.view_signed_copy') }}</a>
                @endif
            </div>
        @elseif ($inForce)
            <p class="text-sm text-amber-700">{{ __('partner.legal.not_signed_yet') }}</p>
        @else
            <p class="text-sm text-gray-500">{{ __('partner.legal.none_published') }}</p>
        @endif

        @if ($inForceDoc)
            @include('partner.contracts.partials.document', ['doc' => $inForceDoc, 'height' => 'h-[28rem]'])
        @endif
    </section>

    <section class="space-y-3 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900">{{ __('partner.legal.history_title') }}</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="text-start text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ __('partner.contracts.col_version') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('partner.contracts.col_status') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('partner.legal.method') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('partner.contracts.col_accepted_at') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($history as $row)
                        <tr>
                            <td class="px-3 py-2">v{{ $row->template_version }}</td>
                            <td class="px-3 py-2">{{ __('partner.contracts.status_' . $row->status) }}</td>
                            <td class="px-3 py-2">{{ __('partner.legal.method_' . $row->acceptance_method) }}</td>
                            <td class="px-3 py-2">{{ format_tz(vendor_tz($row->signed_at)) }}</td>
                            <td class="px-3 py-2 text-end">
                                @if ($row->hasSignedFile())
                                    <a href="{{ route('partner.legal.signed-file', $row) }}" class="text-blue-600 hover:underline">{{ __('partner.legal.download_signed_copy') }}</a>
                                @else
                                    <a href="{{ route('partner.contracts.signed', $row) }}" class="text-blue-600 hover:underline">{{ __('partner.legal.view_signed_copy') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">{{ __('partner.legal.history_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">
            {{ __('partner.legal.category_contracts_hint') }}
            <a href="{{ route('partner.contracts.history') }}" class="text-blue-600 hover:underline">{{ __('partner.contracts.nav_history') }}</a>
            <span class="mx-2 text-gray-300">|</span>
            <a href="{{ route('partner.legal.collaborations') }}" class="text-blue-600 hover:underline">{{ __('contracts.marketer.partner_title') }}</a>
        </p>
    </section>
</div>
@endsection
