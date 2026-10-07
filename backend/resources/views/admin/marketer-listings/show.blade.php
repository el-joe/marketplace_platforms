@extends('layouts.admin')
@section('title', __('admin.marketer_listings.detail_title'))
@section('page-title', __('admin.marketer_listings.detail_title'))

@php
    use App\Enums\MarketerListingStatus;

    $statusStyles = [
        'draft'          => 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200',
        'pending_review' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
        'active'         => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
        'paused'         => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
        'rejected'       => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
        'out_of_stock'   => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200',
        'archived'       => 'bg-gray-100 text-gray-400 ring-1 ring-inset ring-gray-200',
    ];
    $statusValue = $listing->status?->value;
@endphp

@section('content')
<div class="w-full space-y-6">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-500 flex items-center gap-1.5">
        <a href="{{ route('admin.marketer-listings.index') }}" class="hover:text-gray-900">{{ __('admin.marketer_listings.title') }}</a>
        <span>/</span>
        <span class="text-gray-900">{{ $listing->getDisplayTitle() }}</span>
    </nav>

    @if(session('success'))
        <div class="px-6 py-3 bg-emerald-50 text-emerald-700 text-sm rounded-lg border border-emerald-100">{{ session('success') }}</div>
    @endif

    {{-- Header card --}}
    <div class="bg-white rounded-xl border shadow-sm p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-bold text-gray-900">{{ $listing->getDisplayTitle() }}</h2>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusStyles[$statusValue] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusValue }}
                    </span>
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    {{ __('admin.marketer_listings.marketer') }}:
                    <a href="{{ route('admin.marketers.show', $listing->marketer_id) }}" class="font-semibold text-blue-600 hover:underline">
                        {{ $listing->marketer?->name ?? '-' }}
                    </a>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
                @if($listing->status === MarketerListingStatus::PendingReview)
                    @can('marketers.manage')
                    <form method="POST" action="{{ route('admin.marketer-listings.approve', $listing) }}">
                        @csrf
                        <button class="px-4 py-2 bg-emerald-500 text-white font-semibold rounded-lg text-sm shadow-sm hover:bg-emerald-600 transition-colors">
                            ✓ {{ __('admin.marketer_listings.approve') }}
                        </button>
                    </form>
                    @endcan
                @endif
            </div>
        </div>

        {{-- Meta grid --}}
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4 border-t border-gray-100 pt-5 text-sm">
            <div>
                <div class="text-gray-400 text-xs mb-0.5">{{ __('admin.marketer_listings.col_country') }}</div>
                <div class="font-semibold text-gray-800">{{ $listing->country?->name_ar ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-400 text-xs mb-0.5">{{ __('admin.marketer_listings.col_price') }}</div>
                <div class="font-semibold text-gray-800">{{ number_format($listing->price) }} {{ $listing->currency }}</div>
            </div>
            <div>
                <div class="text-gray-400 text-xs mb-0.5">{{ __('admin.marketer_listings.category') }}</div>
                <div class="font-semibold text-gray-800">{{ $listing->listing_category ?? 'product' }}</div>
            </div>
            <div>
                <div class="text-gray-400 text-xs mb-0.5">{{ __('admin.marketer_listings.col_created') }}</div>
                <div class="font-semibold text-gray-800">{{ $listing->created_at->format('Y-m-d') }}</div>
            </div>
            @if($listing->approved_at)
            <div>
                <div class="text-gray-400 text-xs mb-0.5">{{ __('admin.marketer_listings.approved_at') }}</div>
                <div class="font-semibold text-gray-800">{{ $listing->approved_at->format('Y-m-d') }}
                    <span class="text-gray-400 font-normal">— {{ $listing->approvedBy?->name ?? '-' }}</span>
                </div>
            </div>
            @endif
            @if($listing->invitation_id)
            <div>
                <div class="text-gray-400 text-xs mb-0.5">{{ __('admin.marketer_listings.campaign') }}</div>
                <div class="font-semibold text-gray-800">{{ $listing->invitation?->campaign?->title ?? substr($listing->invitation_id, 0, 8) }}</div>
            </div>
            @endif
        </div>

        @if($listing->rejection_reason)
        <div class="mt-5 flex items-start gap-2 p-3 bg-red-50 text-red-700 rounded-lg text-sm">
            <strong class="shrink-0">{{ __('admin.marketer_listings.rejection_reason') }}:</strong>
            <span>{{ $listing->rejection_reason }}</span>
        </div>
        @endif
        @if($listing->paused_reason)
        <div class="mt-5 flex items-start gap-2 p-3 bg-blue-50 text-blue-700 rounded-lg text-sm">
            <strong class="shrink-0">{{ __('admin.marketer_listings.paused_reason') }}:</strong>
            <span>{{ $listing->paused_reason }}</span>
        </div>
        @endif
    </div>

    {{-- Approve / Reject forms (only when pending_review) --}}
    @if($listing->status === MarketerListingStatus::PendingReview)
    @can('marketers.manage')
    <div class="bg-white rounded-xl border shadow-sm p-6 space-y-6">
        <h3 class="font-bold text-gray-800">{{ __('admin.marketer_listings.review_decision') }}</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Approve --}}
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                <h4 class="font-semibold text-emerald-800 mb-2">{{ __('admin.marketer_listings.approve') }}</h4>
                <p class="text-sm text-emerald-700 mb-4">{{ __('admin.marketer_listings.approve_hint') }}</p>
                <form method="POST" action="{{ route('admin.marketer-listings.approve', $listing) }}">
                    @csrf
                    <button class="px-5 py-2 bg-emerald-500 text-white font-bold rounded-lg text-sm hover:bg-emerald-600 transition-colors">
                        ✓ {{ __('admin.marketer_listings.approve') }}
                    </button>
                </form>
            </div>

            {{-- Reject --}}
            <div class="rounded-lg border border-red-200 bg-red-50 p-5">
                <h4 class="font-semibold text-red-800 mb-2">{{ __('admin.marketer_listings.reject') }}</h4>
                <form method="POST" action="{{ route('admin.marketer-listings.reject', $listing) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold text-red-700 mb-1">{{ __('admin.marketer_listings.rejection_reason') }} *</label>
                        <textarea name="rejection_reason" rows="3" required
                                  class="w-full border border-red-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 @error('rejection_reason') border-red-400 @enderror"
                                  placeholder="{{ __('admin.marketer_listings.rejection_reason_placeholder') }}">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button class="px-5 py-2 bg-red-500 text-white font-bold rounded-lg text-sm hover:bg-red-600 transition-colors">
                        ✕ {{ __('admin.marketer_listings.reject') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endcan
    @endif

</div>
@endsection
