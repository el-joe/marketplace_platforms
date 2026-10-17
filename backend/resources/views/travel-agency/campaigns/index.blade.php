@extends('layouts.travel-agency')

@section('title', __('travel.campaigns.title'))
@section('page-title', __('travel.campaigns.title'))

@section('content')
@php
    $statusClasses = [
        'pending_admin' => 'bg-yellow-100 text-yellow-700',
        'active' => 'bg-green-100 text-green-700',
        'auto_approved' => 'bg-blue-100 text-blue-700',
        'rejected' => 'bg-red-100 text-red-700',
        'done' => 'bg-gray-100 text-gray-600',
        'cancelled' => 'bg-gray-100 text-gray-400',
        'paused' => 'bg-orange-100 text-orange-700',
    ];
    $canCreate = auth()->guard('travel_agency')->user()?->can('campaigns.create');
@endphp
<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-gray-900">{{ __('travel.campaigns.title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('travel.campaigns.subtitle') }}</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('travel-agency.campaigns.create') }}" class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                {{ __('travel.campaigns.new_campaign') }}
            </a>
        @endif
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            'total' => $stats['total'],
            'active' => $stats['active'],
            'conversions' => $stats['conversions'],
            'spent' => \App\Support\Money::format($stats['spent']),
        ] as $key => $value)
            <div class="rounded-2xl border border-gray-200 bg-white p-4">
                <div class="text-xs text-gray-500">{{ __('travel.campaigns.stats.'.$key) }}</div>
                <div class="mt-1 text-xl font-bold text-gray-900">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800">{{ __('travel.campaigns.how_it_works_body') }}</div>

    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('travel.campaigns.filter.search') }}"
               class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 outline-none focus:ring-2 focus:ring-blue-300">
        <select name="status" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700">
            <option value="">{{ __('travel.campaigns.filter.all_statuses') }}</option>
            @foreach (array_keys($statusClasses) as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('partner.marketer_campaigns_my.status.'.$status) }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700">{{ __('travel.campaigns.filter.apply') }}</button>
        <span class="ms-auto flex gap-3 text-xs">
            <a class="text-blue-600 hover:underline" href="{{ route('travel-agency.campaigns.export', request()->query()) }}">{{ __('travel.reports.export_csv') }}</a>
            <a class="text-blue-600 hover:underline" href="{{ route('travel-agency.campaigns.export', array_merge(request()->query(), ['format' => 'excel'])) }}">{{ __('common.export_excel') }}</a>
        </span>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white">
        @if ($campaigns->isEmpty())
            <div class="p-10 text-center text-sm text-gray-500">
                {{ __('travel.campaigns.no_campaigns') }}
                @if ($canCreate)
                    <div class="mt-3"><a href="{{ route('travel-agency.campaigns.create') }}" class="font-semibold text-blue-600 hover:underline">{{ __('travel.campaigns.create_first') }}</a></div>
                @endif
            </div>
        @else
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        @foreach (['campaign', 'trip', 'budget', 'used', 'marketers', 'status'] as $col)
                            <th class="px-4 py-3 text-start font-medium">{{ __('travel.campaigns.table.'.$col) }}</th>
                        @endforeach
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($campaigns as $campaign)
                        @php $tripTitle = $campaign->travelPackage?->title_ar ?: $campaign->travelPackage?->title_en; @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $campaign->title ?: __('travel.campaigns.untitled', ['trip' => $tripTitle]) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $tripTitle ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $campaign->budgetLabel() }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ \App\Support\Money::format($campaign->budgetUsed()) }} {{ $campaign->currency }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $campaign->invitations_count }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClasses[$campaign->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ __('partner.marketer_campaigns_my.status.'.$campaign->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end"><a href="{{ route('travel-agency.campaigns.show', $campaign->id) }}" class="font-semibold text-blue-600 hover:underline">{{ __('travel.campaigns.view') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{ $campaigns->links() }}
</div>
@endsection
