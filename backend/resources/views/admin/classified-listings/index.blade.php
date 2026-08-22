@extends('layouts.admin')

@section('title', __('admin.classifieds.listings_title'))

@section('content')
<div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.classifieds.listings_title') }}</h1>
            @if($pendingCount > 0)
            <p class="text-sm text-amber-600 font-medium mt-0.5">
                {{ __('admin.classifieds.pending_review_count', ['count' => $pendingCount]) }}
            </p>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('admin.classifieds.search_number_title') }}"
               class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-64">
        <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">{{ __('admin.classifieds.all_statuses') }}</option>
            @foreach(['draft','pending_contract','pending_review','active','paused','sold','expired','rejected'] as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ __('admin.classifieds.status_' . $s) }}</option>
            @endforeach
        </select>
        <select name="category" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">{{ __('admin.classifieds.all_categories') }}</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                {{ $cat->name_en }}
            </option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">{{ __('admin.filter') }}</button>
        <a href="{{ route('admin.classifieds.listings.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('common.reset') }}</a>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.number') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.listing_title') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.seller') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.category') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.price') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.status') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.classifieds.created') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($listings as $listing)
                    @php
                    $statusColors = [
                        'draft'            => 'bg-gray-100 text-gray-700',
                        'pending_contract' => 'bg-yellow-100 text-yellow-700',
                        'pending_review'   => 'bg-amber-100 text-amber-700',
                        'active'           => 'bg-emerald-100 text-emerald-700',
                        'paused'           => 'bg-blue-100 text-blue-700',
                        'sold'             => 'bg-purple-100 text-purple-700',
                        'expired'          => 'bg-gray-100 text-gray-500',
                        'rejected'         => 'bg-red-100 text-red-700',
                    ];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $listing->listing_number }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $listing->title_en }}</p>
                            <p class="text-xs text-gray-400">{{ $listing->title_ar }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $listing->seller?->name ?? $listing->seller?->store_name }}
                            @if($listing->is_vendor_listing)
                                <span class="ms-1 inline-flex items-center rounded-full bg-violet-50 px-1.5 py-0.5 text-[10px] font-medium text-violet-700">{{ __('admin.classifieds.vendor') }}</span>
                            @else
                                <span class="ms-1 inline-flex items-center rounded-full bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium text-sky-700">{{ __('admin.classifieds.customer') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $listing->classifiedCategory?->name_en }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $listing->price_formatted }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$listing->status->value] ?? '' }}">
                                {{ __('admin.classifieds.status_' . $listing->status->value) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $listing->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.classifieds.listings.show', $listing) }}"
                               class="text-xs text-primary-600 font-medium hover:underline">{{ __('common.view') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-400">{{ __('admin.classifieds.no_listings') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $listings->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
