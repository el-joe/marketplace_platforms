@extends('layouts.marketer')
@section('title', __('marketer.invitations.title'))
@section('page-title', __('marketer.invitations.title'))

@section('content')
<div class="space-y-4">

    @if($invitations->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <div class="text-5xl mb-3">📭</div>
            <h3 class="font-bold text-gray-700">{{ __('marketer.invitations.no_invitations') }}</h3>
            <p class="text-gray-400 text-sm mt-1">{{ __('marketer.invitations.no_invitations_hint') }}</p>
        </div>
    @else
        @foreach($invitations as $invitation)
        @php
            $product = $invitation->campaign->vendorListing?->productVariant?->product
                     ?? $invitation->campaign->adminListing?->productVariant?->product;
            $statusMap = [
                'pending'   => ['label' => __('marketer.invitations.status_pending'),   'cls' => 'bg-yellow-100 text-yellow-700'],
                'accepted'  => ['label' => __('marketer.invitations.status_accepted'),  'cls' => 'bg-green-100 text-green-700'],
                'rejected'  => ['label' => __('marketer.invitations.status_rejected'),  'cls' => 'bg-red-100 text-red-700'],
                'timed_out' => ['label' => __('marketer.invitations.status_timed_out'), 'cls' => 'bg-gray-100 text-gray-500'],
                'cancelled' => ['label' => __('marketer.invitations.status_cancelled'), 'cls' => 'bg-gray-100 text-gray-500'],
            ];
            $status = $statusMap[$invitation->status] ?? ['label' => $invitation->status, 'cls' => 'bg-gray-100 text-gray-500'];
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ $status['cls'] }}">{{ $status['label'] }}</span>
                        <span class="text-xs text-gray-400">{{ $invitation->created_at->format('Y-m-d') }}</span>
                    </div>
                    <h4 class="font-bold text-gray-900">{{ $product?->name_ar ?? $invitation->campaign->title ?? __('marketer.invitations.campaign_default_label') }}</h4>
                    <div class="text-sm text-gray-500 mt-1">
                        {{ $invitation->campaign->vendor->name ?? 'نون' }} •
                        {{ $invitation->campaign->country->name_ar ?? '' }} •
                        {{ __('marketer.invitations.commission_type_label') }} {{ [
                            'fixed'      => __('marketer.invitations.commission_type_fixed'),
                            'tiered'     => __('marketer.invitations.commission_type_tiered'),
                            'last_click' => __('marketer.invitations.commission_type_last_click'),
                        ][$invitation->campaign->commission_type] ?? '' }}
                    </div>
                    <div class="text-sm font-semibold text-green-700 mt-1">
                        {{ __('marketer.invitations.commission_label') }} {{ number_format($invitation->campaign->marketer_commission_amount) }} {{ $invitation->campaign->currency }}
                    </div>
                    @if($invitation->isPending() && $invitation->expires_at)
                    <div class="text-xs text-red-500 mt-1">{{ __('marketer.invitations.expires_label') }} {{ $invitation->expires_at->diffForHumans() }}</div>
                    @endif

                    {{-- Referral info after acceptance --}}
                    @if($invitation->isAccepted() && $invitation->referral_code)
                    <div class="mt-3 p-3 bg-green-50 rounded-lg border border-green-100">
                        <div class="text-xs text-green-700 font-semibold mb-1">{{ __('marketer.invitations.your_referral_link') }}</div>
                        <div class="flex items-center gap-2">
                            <code class="text-xs bg-white px-2 py-1 rounded border text-gray-700 flex-1 overflow-auto">{{ $invitation->referral_link }}</code>
                            <button onclick="navigator.clipboard.writeText('{{ $invitation->referral_link }}')" class="text-xs px-2 py-1 bg-green-500 text-white rounded hover:bg-green-600">{{ __('marketer.invitations.copy') }}</button>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ __('marketer.invitations.code_label') }} <strong>{{ $invitation->referral_code }}</strong></div>
                    </div>
                    @endif
                </div>

                {{-- Actions --}}
                @if($invitation->isPending())
                <div class="flex flex-col gap-2 shrink-0">
                    <form method="POST" action="{{ route('marketer.invitations.accept', $invitation) }}">
                        @csrf
                        <button class="w-full px-4 py-2 bg-green-500 text-white text-sm font-semibold rounded-lg hover:bg-green-600">{{ __('marketer.invitations.accept') }}</button>
                    </form>
                    <form method="POST" action="{{ route('marketer.invitations.reject', $invitation) }}">
                        @csrf
                        <button class="w-full px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200">{{ __('marketer.invitations.reject') }}</button>
                    </form>
                </div>
                @endif
            </div>
        </div>
        @endforeach

        <div>{{ $invitations->links() }}</div>
    @endif
</div>
@endsection
