@extends('layouts.admin')
@section('title', __('admin.marketer_listings.title'))
@section('page-title', __('admin.marketer_listings.title'))

@php
    $statusStyles = [
        'draft'          => 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200',
        'pending_review' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
        'active'         => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        'paused'         => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
        'rejected'       => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
        'out_of_stock'   => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200',
        'archived'       => 'bg-gray-100 text-gray-400 ring-1 ring-inset ring-gray-200',
    ];
@endphp

@section('content')
<div class="w-full space-y-6">

    {{-- Header --}}
    <div class="bg-white rounded-xl border shadow-sm p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('admin.marketer_listings.title') }}</h2>
                @if($pendingCount > 0)
                    <p class="mt-1 text-sm text-amber-700">
                        <span class="font-semibold">{{ $pendingCount }}</span> {{ __('admin.marketer_listings.pending_count') }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.marketer-listings.index') }}" class="mt-5 flex flex-wrap gap-3">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="{{ __('admin.marketer_listings.search_placeholder') }}"
                   class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400 w-60">

            <select name="status" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                <option value="">{{ __('admin.marketer_listings.all_statuses') }}</option>
                @foreach($statuses as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->value }}</option>
                @endforeach
            </select>

            <select name="country_id" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                <option value="">{{ __('admin.marketer_listings.all_countries') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" @selected(request('country_id') === $country->id)>{{ $country->name_ar }}</option>
                @endforeach
            </select>

            <button class="px-5 py-2 bg-yellow-400 text-gray-900 font-bold rounded-lg text-sm hover:bg-yellow-500 transition-colors">{{ __('admin.filter') }}</button>
            <a href="{{ route('admin.marketer-listings.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 transition-colors">{{ __('admin.reset') }}</a>
        </form>
    </div>

    {{-- Status tabs --}}
    <div class="flex gap-2 flex-wrap">
        @php $currentStatus = request('status'); @endphp
        <a href="{{ route('admin.marketer-listings.index', array_merge(request()->except('status', 'page'), [])) }}"
           class="px-4 py-2 rounded-full text-sm font-semibold transition-colors {{ ! $currentStatus ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">
            {{ __('admin.marketer_listings.all') }}
        </a>
        @foreach($statuses as $s)
        <a href="{{ route('admin.marketer-listings.index', array_merge(request()->except('status', 'page'), ['status' => $s->value])) }}"
           class="px-4 py-2 rounded-full text-sm font-semibold transition-colors {{ $currentStatus === $s->value ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50' }}">
            {{ $s->value }}
        </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        @if(session('success'))
            <div class="px-6 py-3 bg-emerald-50 text-emerald-700 text-sm border-b border-emerald-100">{{ session('success') }}</div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs">
                    <tr>
                        <th class="px-6 py-3 text-start">{{ __('admin.marketer_listings.col_marketer') }}</th>
                        <th class="px-6 py-3 text-start">{{ __('admin.marketer_listings.col_product') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('admin.marketer_listings.col_country') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('admin.marketer_listings.col_price') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('admin.marketer_listings.col_status') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('admin.marketer_listings.col_created') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($listings as $listing)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-6 py-3 font-medium">
                            <a href="{{ route('admin.marketers.show', $listing->marketer_id) }}" class="text-blue-600 hover:underline">
                                {{ $listing->marketer?->name ?? '-' }}
                            </a>
                        </td>
                        <td class="px-6 py-3 text-gray-700">{{ $listing->getDisplayTitle() }}</td>
                        <td class="px-6 py-3 text-center text-gray-500">{{ $listing->country?->name_ar ?? '-' }}</td>
                        <td class="px-6 py-3 text-center font-semibold text-gray-800">{{ number_format($listing->price) }} {{ $listing->currency }}</td>
                        <td class="px-6 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusStyles[$listing->status?->value] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $listing->status?->value }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-center text-gray-400 text-xs">{{ $listing->created_at->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-end">
                            <a href="{{ route('admin.marketer-listings.show', $listing) }}" class="text-xs font-semibold text-blue-600 hover:underline">{{ __('admin.view') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">{{ __('admin.marketer_listings.no_listings') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($listings->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $listings->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
