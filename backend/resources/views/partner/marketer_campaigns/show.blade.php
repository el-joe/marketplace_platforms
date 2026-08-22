@extends('layouts.partner')

@php
    $product = $marketerCampaign->vendorListing?->productVariant?->product
        ?? $marketerCampaign->adminListing?->productVariant?->product;

    $statusMap = [
        'pending_admin' => ['label' => __('partner.marketer_campaigns_my.status.pending_admin'), 'cls' => 'bg-yellow-100 text-yellow-700'],
        'active'        => ['label' => __('partner.marketer_campaigns_my.status.active'),        'cls' => 'bg-green-100 text-green-700'],
        'auto_approved' => ['label' => __('partner.marketer_campaigns_my.status.auto_approved'),  'cls' => 'bg-blue-100 text-blue-700'],
        'rejected'      => ['label' => __('partner.marketer_campaigns_my.status.rejected'),       'cls' => 'bg-red-100 text-red-700'],
        'done'          => ['label' => __('partner.marketer_campaigns_my.status.done'),           'cls' => 'bg-gray-100 text-gray-500'],
        'cancelled'     => ['label' => __('partner.marketer_campaigns_my.status.cancelled'),      'cls' => 'bg-gray-100 text-gray-400'],
        'paused'        => ['label' => __('partner.marketer_campaigns_my.status.paused'),         'cls' => 'bg-gray-100 text-gray-600'],
    ];
    $st = $statusMap[$marketerCampaign->status] ?? ['label' => $marketerCampaign->status, 'cls' => 'bg-gray-100 text-gray-600'];

    $invStatusMap = [
        'pending'   => ['label' => __('partner.marketer_campaigns_my.invitation_status.pending'),   'cls' => 'bg-yellow-100 text-yellow-700'],
        'accepted'  => ['label' => __('partner.marketer_campaigns_my.invitation_status.accepted'),  'cls' => 'bg-green-100 text-green-700'],
        'declined'  => ['label' => __('partner.marketer_campaigns_my.invitation_status.declined'),  'cls' => 'bg-red-100 text-red-700'],
        'timed_out' => ['label' => __('partner.marketer_campaigns_my.invitation_status.timed_out'), 'cls' => 'bg-gray-100 text-gray-500'],
    ];

    $isPendingCancellable = $marketerCampaign->status === 'pending_admin';
@endphp

@section('title', $marketerCampaign->title ?: __('partner.marketer_campaigns_my.title'))
@section('page-title', __('partner.marketer_campaigns_my.title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6" x-data="{ tab: 'info' }">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('partner.marketer-campaigns.index') }}" class="hover:text-primary-600">{{ __('partner.marketer_campaigns_my.title') }}</a>
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
        <span class="text-gray-800 font-medium truncate max-w-xs">{{ $marketerCampaign->title ?: ($product?->name_ar ?? $product?->name_en) }}</span>
    </nav>

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ $marketerCampaign->title ?: ($product?->name_ar ?? $product?->name_en ?? '—') }}</h1>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $st['cls'] }}">
                {{ $st['label'] }}
            </span>
        </div>
        @if ($isPendingCancellable)
            <form method="POST" action="{{ route('partner.marketer-campaigns.cancel', $marketerCampaign) }}"
                  onsubmit="return confirm('{{ __('partner.marketer_campaigns_my.confirm_cancel') }}')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors">
                    {{ __('partner.marketer_campaigns_my.cancel') }}
                </button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Tab nav --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-x-6 overflow-x-auto">
            @foreach ([
                'info'        => __('partner.marketer_campaigns_my.tab_info'),
                'marketers'   => __('partner.marketer_campaigns_my.tab_marketers'),
                'conversions' => __('partner.marketer_campaigns_my.tab_conversions'),
                'samples'     => __('partner.marketer_campaigns_my.tab_samples'),
            ] as $tabKey => $tabLabel)
                <button type="button"
                    @click="tab = '{{ $tabKey }}'"
                    :class="tab === '{{ $tabKey }}' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ═══ Tab 1: Campaign Info ═══ --}}
    <div x-show="tab === 'info'" x-cloak class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.tab_info') }}</h3>
            <div class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm md:grid-cols-3">
                @foreach ([
                    __('partner.marketer_campaigns_my.field.product') => $product?->name_ar ?? $product?->name_en ?? '—',
                    __('partner.marketer_campaigns_my.field.country') => $marketerCampaign->country?->name_ar ?? $marketerCampaign->country?->name_en ?? '—',
                    __('partner.marketer_campaigns_my.field.currency') => $marketerCampaign->currency ?? '—',
                    __('partner.marketer_campaigns_my.field.commission_type') => __('partner.marketer_campaigns_my.commission_type.' . $marketerCampaign->commission_type),
                    __('partner.marketer_campaigns_my.field.max_commission_budget') => $marketerCampaign->max_commission_budget !== null ? number_format($marketerCampaign->max_commission_budget) : '—',
                    __('partner.marketer_campaigns_my.field.platform_commission_amount') => $marketerCampaign->platform_commission_amount !== null ? number_format($marketerCampaign->platform_commission_amount) : '—',
                    __('partner.marketer_campaigns_my.field.marketer_commission_amount') => $marketerCampaign->marketer_commission_amount !== null ? number_format($marketerCampaign->marketer_commission_amount) : '—',
                    __('partner.marketer_campaigns_my.field.platform_sample_qty') => $marketerCampaign->platform_sample_qty_snapshot ?? 0,
                    __('partner.marketer_campaigns_my.field.per_marketer_sample_qty') => $marketerCampaign->per_marketer_sample_qty_snapshot ?? 0,
                    __('partner.marketer_campaigns_my.field.created_at') => $marketerCampaign->created_at->format('Y-m-d H:i'),
                    __('partner.marketer_campaigns_my.field.reviewed_at') => $marketerCampaign->reviewed_at?->format('Y-m-d H:i') ?? '—',
                    __('partner.marketer_campaigns_my.field.auto_approve_at') => $marketerCampaign->auto_approve_at?->format('Y-m-d H:i') ?? '—',
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $value }}</dd>
                    </div>
                @endforeach
                @if ($marketerCampaign->notes)
                    <div class="col-span-2 md:col-span-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('partner.marketer_campaigns_my.field.notes') }}</dt>
                        <dd class="mt-0.5 text-gray-700">{{ $marketerCampaign->notes }}</dd>
                    </div>
                @endif
                @if ($marketerCampaign->status === 'rejected' && $marketerCampaign->rejection_reason)
                    <div class="col-span-2 md:col-span-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('partner.marketer_campaigns_my.field.rejection_reason') }}</dt>
                        <dd class="mt-0.5 text-red-700">{{ $marketerCampaign->rejection_reason }}</dd>
                    </div>
                @endif
            </div>
        </div>

        @if ($marketerCampaign->commission_type === 'tiered' && $marketerCampaign->tieredRules->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <h3 class="px-5 pt-4 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.tiered_rules') }}</h3>
                <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.from_sale_number') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.platform_commission_amount') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.currency') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($marketerCampaign->tieredRules as $rule)
                            <tr>
                                <td class="px-4 py-2">{{ $rule->from_sale_number }}</td>
                                <td class="px-4 py-2">{{ number_format($rule->commission_amount) }}</td>
                                <td class="px-4 py-2">{{ $rule->currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Status timeline --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.status_timeline') }}</h3>
            <ol class="space-y-3 text-sm">
                <li class="flex items-center gap-3">
                    <span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span>
                    <span class="text-gray-700">{{ __('partner.marketer_campaigns_my.timeline.created') }}</span>
                    <span class="text-gray-400">{{ $marketerCampaign->created_at->format('Y-m-d H:i') }}</span>
                </li>
                @if ($marketerCampaign->reviewed_at)
                    <li class="flex items-center gap-3">
                        <span class="h-2.5 w-2.5 rounded-full {{ $marketerCampaign->status === 'rejected' ? 'bg-red-500' : 'bg-green-500' }}"></span>
                        <span class="text-gray-700">
                            {{ $marketerCampaign->status === 'rejected' ? __('partner.marketer_campaigns_my.timeline.rejected') : __('partner.marketer_campaigns_my.timeline.reviewed') }}
                        </span>
                        <span class="text-gray-400">{{ $marketerCampaign->reviewed_at->format('Y-m-d H:i') }}</span>
                    </li>
                @elseif ($marketerCampaign->auto_approved)
                    <li class="flex items-center gap-3">
                        <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                        <span class="text-gray-700">{{ __('partner.marketer_campaigns_my.timeline.auto_approved') }}</span>
                    </li>
                @endif
            </ol>
        </div>
    </div>

    {{-- ═══ Tab 2: Invited Marketers ═══ --}}
    <div x-show="tab === 'marketers'" x-cloak>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            @if ($marketerCampaign->invitations->isEmpty())
                <p class="p-6 text-center text-sm text-gray-400">{{ __('partner.marketer_campaigns_my.no_invitations') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.marketer') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.type') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.status') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.referral_link') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.qr_code') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.responded_at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($marketerCampaign->invitations as $invitation)
                                @php
                                    $ist = $invStatusMap[$invitation->status] ?? ['label' => $invitation->status, 'cls' => 'bg-gray-100 text-gray-600'];
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $invitation->marketer?->store_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $invitation->marketer?->marketer_type ? __('partner.marketer_types.' . $invitation->marketer->marketer_type) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $ist['cls'] }}">
                                            {{ $ist['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($invitation->referral_link)
                                            <div class="flex items-center gap-2">
                                                <input type="text" readonly value="{{ $invitation->referral_link }}"
                                                       class="w-40 truncate rounded border border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-600" />
                                                <button type="button"
                                                        class="text-xs font-medium text-primary-600 hover:underline"
                                                        onclick="copyToClipboard('{{ $invitation->referral_link }}'); const btn = this; btn.textContent='{{ __('partner.marketer_campaigns_my.copied') }}'; setTimeout(() => btn.textContent='{{ __('partner.marketer_campaigns_my.copy') }}', 1500)">
                                                    {{ __('partner.marketer_campaigns_my.copy') }}
                                                </button>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($invitation->qr_code_path)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($invitation->qr_code_path) }}" alt="QR" class="h-10 w-10 rounded border border-gray-200 object-contain">
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $invitation->responded_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ Tab 3: Conversions ═══ --}}
    <div x-show="tab === 'conversions'" x-cloak>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            @if ($marketerCampaign->conversions->isEmpty())
                <p class="p-6 text-center text-sm text-gray-400">{{ __('partner.marketer_campaigns_my.no_conversions') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.order_number') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.date') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.commission_earned') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.click_time') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($marketerCampaign->conversions as $conversion)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $conversion->order?->order_number ?? $conversion->order_id }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $conversion->created_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ number_format($conversion->commission_amount) }} {{ $conversion->currency }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $conversion->referral_clicked_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ Tab 4: Samples ═══ --}}
    <div x-show="tab === 'samples'" x-cloak class="space-y-6">
        @php
            $platformSamples = $marketerCampaign->samples->where('sample_owner', 'platform');
            $marketerSamples = $marketerCampaign->samples->where('sample_owner', '!=', 'platform');
        @endphp

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.platform_samples') }}</h3>
            @if ($platformSamples->isEmpty())
                <p class="text-sm text-gray-400">{{ __('partner.marketer_campaigns_my.no_samples') }}</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($platformSamples as $sample)
                        <li class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                            <span class="text-gray-700">{{ __('partner.marketer_campaigns_my.field.quantity') }}: {{ $sample->quantity }}</span>
                            <span class="text-gray-500">{{ $sample->status }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <h3 class="px-5 pt-4 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.per_marketer_samples') }}</h3>
            @if ($marketerSamples->isEmpty())
                <p class="p-6 text-center text-sm text-gray-400">{{ __('partner.marketer_campaigns_my.no_samples') }}</p>
            @else
                <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.marketer') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.quantity') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.status') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.dispatched_at') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.delivered_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($marketerSamples as $sample)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $sample->invitation?->marketer?->store_name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $sample->quantity }}</td>
                                <td class="px-4 py-2">{{ $sample->status }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $sample->dispatched_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $sample->delivered_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

</div>
@endsection
