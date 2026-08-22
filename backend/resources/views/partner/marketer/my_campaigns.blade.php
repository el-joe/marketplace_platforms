@extends('layouts.partner')

@section('title', __('partner.marketer_my_campaigns.title'))
@section('page-title', __('partner.marketer_my_campaigns.title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.marketer_my_campaigns.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('partner.marketer_my_campaigns.subtitle') }}</p>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($acceptedInvitations->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-gray-200 py-16 text-center">
            <i class="fas fa-bullhorn text-4xl text-gray-300 mb-3 block"></i>
            <p class="text-sm font-medium text-gray-500">{{ __('partner.marketer_my_campaigns.no_campaigns') }}</p>
            <p class="text-sm text-gray-400 mt-1">{{ __('partner.marketer_my_campaigns.no_campaigns_hint') }}</p>
        </div>
    @else
        @php
            $typeLabels = [
                'fixed'      => 'ثابت',
                'tiered'     => 'متدرج',
                'last_click' => 'Last Click',
            ];
            $statusMap = [
                'active'         => ['label' => 'نشطة', 'cls' => 'bg-green-100 text-green-700'],
                'auto_approved'  => ['label' => 'مقبولة تلقائياً', 'cls' => 'bg-blue-100 text-blue-700'],
                'done'           => ['label' => 'منتهية', 'cls' => 'bg-gray-100 text-gray-600'],
                'pending_admin'  => ['label' => 'قيد المراجعة', 'cls' => 'bg-yellow-100 text-yellow-700'],
            ];
        @endphp

        <div class="space-y-4">
            @foreach ($acceptedInvitations as $invitation)
                @php
                    $campaign = $invitation->campaign;
                    $status   = $statusMap[$campaign?->status] ?? ['label' => $campaign?->status, 'cls' => 'bg-gray-100 text-gray-500'];
                    $productName = $campaign?->title
                        ?? $campaign?->vendorListing?->productVariant?->product?->name_ar
                        ?? $campaign?->vendorListing?->productVariant?->product?->name_en
                        ?? $campaign?->adminListing?->productVariant?->product?->name_ar
                        ?? '—';
                @endphp
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

                    <div class="flex items-center justify-between p-4 border-b border-gray-50 flex-wrap gap-2">
                        <div>
                            <div class="font-semibold text-gray-900">{{ $productName }}</div>
                            <div class="text-sm text-gray-500">
                                {{ $campaign?->vendor?->store_name ?? '—' }}
                                · {{ $campaign?->country?->name_ar ?? '' }}
                                · {{ $typeLabels[$campaign?->commission_type] ?? $campaign?->commission_type }}
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $status['cls'] }}">
                            {{ $status['label'] }}
                        </span>
                    </div>

                    <div class="grid grid-cols-3 divide-x divide-x-reverse divide-gray-100 text-center py-3">
                        <div>
                            <div class="text-lg font-bold text-gray-900">{{ $invitation->conversions_count }}</div>
                            <div class="text-xs text-gray-500">{{ __('partner.marketer_my_campaigns.conversions') }}</div>
                        </div>
                        <div>
                            <div class="text-lg font-bold text-green-700">
                                {{ number_format($invitation->conversions_sum_commission_amount ?? 0) }}
                                {{ $campaign?->currency }}
                            </div>
                            <div class="text-xs text-gray-500">{{ __('partner.marketer_my_campaigns.earnings') }}</div>
                        </div>
                        <div>
                            <div class="text-lg font-bold text-purple-700">{{ $invitation->samples->sum('quantity') }}</div>
                            <div class="text-xs text-gray-500">{{ __('partner.marketer_my_campaigns.samples') }}</div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 border-t border-gray-100">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <i class="fas fa-link text-gray-400 text-sm flex-shrink-0"></i>
                                <code class="text-xs bg-white border border-gray-200 rounded px-2 py-1 truncate flex-1">
                                    {{ $invitation->referral_link ?? __('partner.marketer_my_campaigns.link_pending') }}
                                </code>
                                @if ($invitation->referral_link)
                                    <button type="button"
                                            onclick="copyToClipboard('{{ $invitation->referral_link }}')"
                                            class="flex-shrink-0 text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">
                                        <i class="fas fa-copy mr-1"></i> {{ __('partner.marketer_my_campaigns.copy_link') }}
                                    </button>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if ($invitation->qr_code_path)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($invitation->qr_code_path) }}"
                                       target="_blank" download
                                       class="flex items-center gap-1 text-xs bg-purple-600 text-white px-3 py-1 rounded hover:bg-purple-700">
                                        <i class="fas fa-qrcode"></i> {{ __('partner.marketer_my_campaigns.download_qr') }}
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">
                                        <i class="fas fa-qrcode mr-1"></i> {{ __('partner.marketer_my_campaigns.qr_pending') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="text-xs text-gray-400 mt-2">
                            {{ __('partner.marketer_my_campaigns.accepted_at') }}: {{ $invitation->responded_at?->format('Y-m-d H:i') ?? '—' }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div>{{ $acceptedInvitations->links() }}</div>
    @endif
</div>
@endsection
