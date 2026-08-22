@extends('layouts.partner')

@section('title', __('partner.marketer_invitations.title'))
@section('page-title', __('partner.marketer_invitations.title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_invitations.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_invitations.subtitle') }}</p>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @php
        $statusMap = [
            'pending'   => ['label' => __('partner.marketer_invitations.status.pending'),   'cls' => 'bg-yellow-100 text-yellow-700'],
            'accepted'  => ['label' => __('partner.marketer_invitations.status.accepted'),  'cls' => 'bg-green-100 text-green-700'],
            'rejected'  => ['label' => __('partner.marketer_invitations.status.rejected'),  'cls' => 'bg-red-100 text-red-700'],
            'timed_out' => ['label' => __('partner.marketer_invitations.status.timed_out'), 'cls' => 'bg-gray-100 text-gray-500'],
        ];
    @endphp

    @if ($invitations->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-200 py-16 text-center">
            <p class="text-sm text-gray-500">{{ __('partner.marketer_invitations.no_invitations') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.campaign') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.vendor') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.product') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.commission_type') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.status') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.referral_link') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.qr_code') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.expires_at') }}</th>
                        <th class="px-4 py-3 text-start font-medium text-gray-500">{{ __('partner.marketer_invitations.table.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($invitations as $invitation)
                        @php
                            $campaign = $invitation->campaign;
                            $status   = $statusMap[$invitation->status] ?? ['label' => $invitation->status, 'cls' => 'bg-gray-100 text-gray-500'];
                            $typeLabels = [
                                'fixed'      => 'ثابت',
                                'tiered'     => 'متدرج',
                                'last_click' => 'Last Click',
                            ];
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ $campaign?->title
                                   ?? $campaign?->vendorListing?->productVariant?->product?->name_ar
                                   ?? $campaign?->vendorListing?->productVariant?->product?->name_en
                                   ?? $campaign?->adminListing?->productVariant?->product?->name_ar
                                   ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $campaign?->vendor?->store_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $campaign?->vendorListing?->productVariant?->product?->name_ar
                                   ?? $campaign?->vendorListing?->productVariant?->product?->name_en
                                   ?? $campaign?->adminListing?->productVariant?->product?->name_ar
                                   ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $typeLabels[$campaign?->commission_type] ?? $campaign?->commission_type }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $status['cls'] }}">
                                    {{ $status['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($invitation->referral_link && $invitation->status === 'accepted')
                                    <button type="button"
                                            onclick="copyToClipboard('{{ $invitation->referral_link }}')"
                                            class="text-xs bg-blue-50 border border-blue-200 text-blue-700 px-2 py-1 rounded hover:bg-blue-100">
                                        <i class="fas fa-copy mr-1"></i> نسخ الرابط
                                    </button>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($invitation->qr_code_path)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($invitation->qr_code_path) }}" target="_blank">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($invitation->qr_code_path) }}"
                                             class="w-10 h-10 rounded border" alt="QR">
                                    </a>
                                @elseif ($invitation->status === 'accepted')
                                    <span class="text-xs text-gray-400">قيد الإنشاء</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ $invitation->expires_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($invitation->isPending())
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('partner.marketer.invitations.accept', $invitation) }}">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">
                                                {{ __('partner.marketer_invitations.accept') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('partner.marketer.invitations.reject', $invitation) }}"
                                              onsubmit="return confirm('{{ __('partner.marketer_invitations.confirm_reject') }}')">
                                            @csrf
                                            <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">
                                                {{ __('partner.marketer_invitations.reject') }}
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>{{ $invitations->links() }}</div>
    @endif
</div>
@endsection
