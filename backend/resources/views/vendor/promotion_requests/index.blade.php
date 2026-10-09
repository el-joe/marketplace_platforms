@extends('layouts.partner')

@section('title', 'طلبات ترويج المؤثرين')
@section('page-title', 'طلبات ترويج المؤثرين')

@php
    $statusBadge = fn (string $status) => match ($status) {
        'pending' => 'bg-yellow-100 text-yellow-800',
        'partially_accepted' => 'bg-blue-100 text-blue-800',
        'fully_accepted' => 'bg-green-100 text-green-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('partner.static_text.vendor_promotion_requests_index.influencer_promotion_requests') }}</h2>
            <a href="{{ route('partner.promotion-requests.create') }}"
               class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold text-sm rounded-lg px-4 py-2">
                {{ __('partner.static_text.vendor_promotion_requests_index.new_promotion_request') }}
            </a>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-right">{{ __('partner.static_text.vendor_promotion_requests_index.listing') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.static_text.vendor_promotion_requests_index.number_of_influencers') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.static_text.vendor_promotion_requests_index.total_fees') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.static_text.vendor_promotion_requests_index.status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('partner.static_text.vendor_promotion_requests_index.created_at') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $req)
                        <tr>
                            <td class="px-4 py-3">{{ $req->vendorListing?->vendor_sku ?? $req->vendor_listing_id }}</td>
                            <td class="px-4 py-3">{{ $req->num_celebrities_requested }}</td>
                            <td class="px-4 py-3">{{ $req->currency }} {{ number_format($req->total_promotion_fee) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium {{ $statusBadge($req->status) }}">
                                    {{ $req->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $req->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('partner.promotion-requests.show', $req->id) }}" class="text-yellow-600 hover:text-yellow-700 font-medium">{{ __('partner.static_text.vendor_promotion_requests_index.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('partner.static_text.vendor_promotion_requests_index.no_promotion_requests_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    </div>
@endsection
