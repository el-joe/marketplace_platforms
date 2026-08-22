@extends('layouts.travel-agency')

@section('title', __('travel.campaigns.title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('travel.campaigns.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('travel.campaigns.subtitle') }}</p>
        </div>
        <a href="{{ route('travel-agency.campaigns.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            {{ __('travel.campaigns.new_offer') }}
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
        @foreach ([
            ['label' => __('travel.campaigns.stats.draft'),       'value' => $stats['draft'],       'cls' => 'text-gray-700'],
            ['label' => __('travel.campaigns.stats.active'),      'value' => $stats['active'],      'cls' => 'text-emerald-600'],
            ['label' => __('travel.campaigns.stats.invited'),     'value' => $stats['invited'],     'cls' => 'text-blue-600'],
            ['label' => __('travel.campaigns.stats.accepted'),    'value' => $stats['accepted'],    'cls' => 'text-indigo-600'],
            ['label' => __('travel.campaigns.stats.conversions'), 'value' => $stats['conversions'], 'cls' => 'text-amber-600'],
        ] as $s)
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 text-center shadow-sm">
                <p class="text-2xl font-bold {{ $s['cls'] }}">{{ number_format($s['value']) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $s['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('travel-agency.campaigns.index') }}" class="flex items-end gap-3 flex-wrap">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('common.search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.campaigns.table.status') }}</label>
            <select name="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('travel.bookings.all_statuses') }}</option>
                @foreach(['draft', 'pending_admin', 'active', 'paused', 'rejected', 'ended'] as $st)
                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.date_from') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('travel.bookings.date_to') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">
            {{ __('travel.bookings.filter') }}
        </button>
        <div class="ms-auto flex gap-2 text-xs">
            <a href="{{ route('travel-agency.campaigns.export', request()->query()) }}" class="text-blue-600 hover:underline">{{ __('travel.reports.export_csv') }}</a>
            <a href="{{ route('travel-agency.campaigns.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="text-blue-600 hover:underline">{{ __('common.export_excel') }}</a>
            <a href="{{ route('travel-agency.campaigns.export', array_merge(request()->query(), ['format' => 'word'])) }}" class="text-blue-600 hover:underline">{{ __('common.export_word') }}</a>
        </div>
    </form>

    {{-- Offers list --}}
    @if ($offers->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-200 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            <p class="mt-4 text-sm text-gray-500">{{ __('travel.campaigns.no_offers') }}</p>
            <a href="{{ route('travel-agency.campaigns.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                {{ __('travel.campaigns.create_first_offer') }}
            </a>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('travel.campaigns.table.offer') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('travel.campaigns.table.type') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('travel.campaigns.table.commission') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('travel.campaigns.table.period') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('travel.campaigns.table.status') }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('travel.campaigns.table.invites') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($offers as $offer)
                        @php
                            $statusMap = [
                                'draft'         => ['label' => __('travel.campaigns.status.draft'),         'cls' => 'bg-gray-100 text-gray-600'],
                                'pending_admin' => ['label' => __('travel.campaigns.status.pending_admin'), 'cls' => 'bg-amber-100 text-amber-700'],
                                'active'        => ['label' => __('travel.campaigns.status.active'),        'cls' => 'bg-emerald-100 text-emerald-700'],
                                'paused'        => ['label' => __('travel.campaigns.status.paused'),        'cls' => 'bg-blue-100 text-blue-700'],
                                'rejected'      => ['label' => __('travel.campaigns.status.rejected'),      'cls' => 'bg-red-100 text-red-700'],
                                'ended'         => ['label' => __('travel.campaigns.status.ended'),         'cls' => 'bg-gray-100 text-gray-400'],
                            ];
                            $st = $statusMap[$offer->status->value] ?? ['label' => $offer->status->value, 'cls' => 'bg-gray-100 text-gray-600'];
                            $typeMap = [
                                'product_promotion' => __('travel.campaigns.types.product_promotion'),
                                'store_promotion'   => __('travel.campaigns.types.store_promotion'),
                                'brand_deal'        => __('travel.campaigns.types.brand_deal'),
                                'product_specific'  => __('travel.campaigns.types.product_specific'),
                                'flash_sale'        => __('travel.campaigns.types.flash_sale'),
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('travel-agency.campaigns.show', $offer->id) }}"
                                   class="font-medium text-primary-600 hover:underline">{{ $offer->name }}</a>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $offer->created_at->format('d M Y') }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $typeMap[$offer->campaign_type->value] ?? $offer->campaign_type->value }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-900">{{ $offer->offered_commission_rate }}%</span>
                                <span class="text-xs text-gray-400 mr-1">{{ __('travel.campaigns.commission_suffix.' . $offer->commission_type->value) }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs">
                                {{ $offer->starts_at->format('d M') }} – {{ $offer->ends_at->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $st['cls'] }}">
                                    {{ $st['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $offer->invitations_count }}
                                @if ($offer->accepted_count > 0)
                                    <span class="text-xs text-emerald-600">({{ __('travel.campaigns.accepted_suffix', ['count' => $offer->accepted_count]) }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-left">
                                <a href="{{ route('travel-agency.campaigns.show', $offer->id) }}"
                                   class="inline-flex items-center px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                                    {{ __('travel.campaigns.view') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
@endsection
