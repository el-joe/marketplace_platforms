@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/select2.js'])
@endpush

@section('title', ($marketerCampaign->title ?: __('admin.marketer_campaigns.title')) . ' — ' . __('admin.marketer_campaigns.title'))

@section('content')

@php
    $statusColors = [
        'pending_admin'  => 'warning',
        'active'         => 'success',
        'rejected'       => 'danger',
        'auto_approved'  => 'primary',
        'done'           => 'gray',
    ];
    $product = $marketerCampaign->vendorListing?->productVariant?->product
        ?? $marketerCampaign->adminListing?->productVariant?->product;
    $promotedLabel = match ($marketerCampaign->campaign_category ?? 'product') {
        'travel'     => $marketerCampaign->travelPackage?->title_ar,
        'classified' => $marketerCampaign->classifiedListing?->title_ar,
        default      => $product?->name,
    };
    $isPending = $marketerCampaign->status === 'pending_admin';
    $acceptedInvitations = $marketerCampaign->invitations->where('status', 'accepted');
    $totalFeeExpected = $acceptedInvitations->sum('platform_fee_amount');
    $totalFeePaid     = $acceptedInvitations->where('platform_fee_status', 'paid')->sum('platform_fee_amount');
    $totalFeePending  = $acceptedInvitations->where('platform_fee_status', 'pending')->sum('platform_fee_amount');
    $influencerAccepted = $acceptedInvitations->filter(fn ($i) => $i->marketer?->isInfluencer())->count();
    $affiliateAccepted  = $acceptedInvitations->filter(fn ($i) => $i->marketer?->isAffiliate())->count();
@endphp

{{-- ─── Page header ─────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold text-gray-900 truncate">{{ $marketerCampaign->title ?: __('admin.marketer_campaigns.title') }}</h1>
        <div class="flex items-center gap-2 mt-1 flex-wrap">
            <x-badge :color="$statusColors[$marketerCampaign->status] ?? 'gray'">
                {{ __('admin.marketer_campaigns.status_' . $marketerCampaign->status) }}
            </x-badge>
            <span class="text-sm text-gray-500">{{ $marketerCampaign->vendor?->store_name }}</span>
        </div>
    </div>
    <a href="{{ route('admin.marketer-campaigns.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex-shrink-0 mt-1">
        {{ __('admin.marketer_campaigns.back') }}
    </a>
</div>

{{-- Financial Summary Bar --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">

    <div class="bg-white rounded-xl border p-4 text-center">
        <div class="text-xs text-gray-500 mb-1">رسوم المنصة</div>
        <div class="text-lg font-bold
            {{ $totalFeePending > 0 ? 'text-orange-600' : ($totalFeeExpected > 0 ? 'text-green-700' : 'text-gray-400') }}">
            {{ number_format($totalFeeExpected) }}
            {{ $marketerCampaign->currency }}
        </div>
        <div class="text-xs mt-1">
            @if($totalFeePending > 0)
                <span class="text-orange-500">انتظار: {{ number_format($totalFeePending) }}</span>
            @elseif($totalFeeExpected > 0)
                <span class="text-green-600">مدفوعة بالكامل</span>
            @else
                <span class="text-gray-400">لا توجد رسوم</span>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl border p-4 text-center">
        <div class="text-xs text-gray-500 mb-1">ماركترز قبلوا</div>
        <div class="text-sm font-semibold text-gray-800">
            @if($influencerAccepted > 0)
                <span class="text-purple-700">{{ $influencerAccepted }} إنفلوينسر</span>
            @endif
            @if($affiliateAccepted > 0)
                @if($influencerAccepted > 0) + @endif
                <span class="text-blue-700">{{ $affiliateAccepted }} أفيلييت</span>
            @endif
            @if($influencerAccepted === 0 && $affiliateAccepted === 0)
                <span class="text-gray-400">لا يوجد بعد</span>
            @endif
        </div>
        <div class="text-xs text-gray-400 mt-1">أفيلييت مجاني</div>
    </div>

    <div class="bg-white rounded-xl border p-4 text-center">
        <div class="text-xs text-gray-500 mb-1">إجمالي البيعات</div>
        <div class="text-lg font-bold text-gray-900">
            {{ $marketerCampaign->conversions()->count() }}
        </div>
        <div class="text-xs text-gray-400 mt-1">تحويل ناجح</div>
    </div>

    <div class="bg-white rounded-xl border p-4 text-center">
        <div class="text-xs text-gray-500 mb-1">كوميشن الماركترز (مستحق)</div>
        <div class="text-lg font-bold text-red-600">
            {{ number_format($marketerCampaign->total_commission_owed) }}
            {{ $marketerCampaign->currency }}
        </div>
        <div class="text-xs text-gray-400 mt-1">لم يُدفع بعد</div>
    </div>

    <div class="bg-white rounded-xl border p-4 text-center">
        <div class="text-xs text-gray-500 mb-1">ربح المنصة</div>
        <div class="text-lg font-bold text-green-700">
            {{ number_format($marketerCampaign->net_platform_profit) }}
            {{ $marketerCampaign->currency }}
        </div>
        <div class="text-xs text-gray-400 mt-1">من الرسوم</div>
    </div>

</div>

@if($totalFeePending > 0)
<div class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-4">
    <span class="font-semibold text-orange-800">
        يوجد رسوم منصة في انتظار التحصيل
    </span>
    <span class="text-sm text-orange-600 ml-2">
        المبلغ: {{ number_format($totalFeePending) }} {{ $marketerCampaign->currency }} — راجع تبويب "الدعوات" لتسجيل كل رسوم على حدة.
    </span>
</div>
@endif

<div x-data="{ tab: 'details' }">
    {{-- Tab bar --}}
    <div class="flex gap-1 border-b border-gray-200 overflow-x-auto pb-px mb-6">
        @foreach([
            'details'     => __('admin.marketer_campaigns.tab_details'),
            'commission'  => __('admin.marketer_campaigns.tab_commission'),
            'marketers'   => __('admin.marketer_campaigns.tab_marketers'),
            'tiered'      => __('admin.marketer_campaigns.tab_tiered'),
            'invitations' => __('admin.marketer_campaigns.tab_invitations'),
            'conversions' => __('admin.marketer_campaigns.tab_conversions'),
            'samples'     => __('admin.marketer_campaigns.tab_samples'),
        ] as $key => $label)
            <button type="button"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-b-2 border-primary-600 text-primary-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm whitespace-nowrap transition-colors focus:outline-none">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ── Details ──────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'details'">
        <x-card title="{{ __('admin.marketer_campaigns.tab_details') }}">
            <div class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                @foreach([
                    __('admin.marketer_campaigns.vendor')                     => $marketerCampaign->vendor?->store_name ?? '—',
                    __('admin.marketer_campaigns.product')                    => $promotedLabel ?? '—',
                    __('admin.marketer_campaigns.country')                    => $marketerCampaign->country?->name_en ?? '—',
                    __('admin.marketer_campaigns.currency')                   => $marketerCampaign->currency ?? '—',
                    __('admin.marketer_campaigns.commission_type')            => $marketerCampaign->commission_type ? __('admin.marketer_campaigns.commission_type_' . $marketerCampaign->commission_type) : '—',
                    __('admin.marketer_campaigns.max_commission_budget')      => $marketerCampaign->max_commission_budget !== null ? number_format($marketerCampaign->max_commission_budget) : '—',
                    __('admin.marketer_campaigns.platform_commission_amount') => $marketerCampaign->platform_commission_amount !== null ? number_format($marketerCampaign->platform_commission_amount) : '—',
                    __('admin.marketer_campaigns.marketer_commission_amount') => $marketerCampaign->marketer_commission_amount !== null ? number_format($marketerCampaign->marketer_commission_amount) : '—',
                    __('admin.marketer_campaigns.platform_sample_qty')        => $marketerCampaign->platform_sample_qty_snapshot ?? 0,
                    __('admin.marketer_campaigns.per_marketer_sample_qty')    => $marketerCampaign->per_marketer_sample_qty_snapshot ?? 0,
                    __('admin.marketer_campaigns.auto_approve_at')            => $marketerCampaign->auto_approve_at?->format('d M Y H:i') ?? '—',
                    __('admin.marketer_campaigns.reviewed_by')                => $marketerCampaign->reviewedBy?->name ?? '—',
                    __('admin.marketer_campaigns.reviewed_at')                => $marketerCampaign->reviewed_at?->format('d M Y H:i') ?? '—',
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $label }}</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $value }}</dd>
                    </div>
                @endforeach
                @if($marketerCampaign->notes)
                    <div class="col-span-2">
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.marketer_campaigns.notes') }}</dt>
                        <dd class="mt-0.5 text-gray-700">{{ $marketerCampaign->notes }}</dd>
                    </div>
                @endif
                @if($marketerCampaign->status === 'rejected' && $marketerCampaign->rejection_reason)
                    <div class="col-span-2">
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ __('admin.marketer_campaigns.rejection_reason') }}</dt>
                        <dd class="mt-0.5 text-danger-700">{{ $marketerCampaign->rejection_reason }}</dd>
                    </div>
                @endif
            </div>
        </x-card>

        <x-card title="نطاق الأقسام (منتجات / سوق مفتوح)">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @foreach([['product', 'category', 'أقسام المنتجات', $categories], ['classified', 'classifiedCategory', 'أقسام السوق المفتوح', $classifiedCategories]] as [$type, $relation, $label, $options])
                <form method="POST" action="{{ route('admin.marketer-campaigns.category-rules.sync', $marketerCampaign) }}" class="space-y-2 border rounded-lg p-3">
                    @csrf
                    <input type="hidden" name="category_type" value="{{ $type }}">
                    <div class="text-sm font-semibold text-gray-700">{{ $label }}</div>
                    <select name="selection_mode" class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                        @php $currentMode = $type === 'product' ? $marketerCampaign->product_category_selection_mode : $marketerCampaign->classified_category_selection_mode; @endphp
                        <option value="all" @selected($currentMode === 'all')>كل الأقسام</option>
                        <option value="include" @selected($currentMode === 'include')>أقسام محددة (تضمين)</option>
                        <option value="exclude" @selected($currentMode === 'exclude')>كل الأقسام باستثناء</option>
                    </select>
                    <select name="category_ids[]" multiple data-select2-init class="block w-full rounded-lg border border-gray-300 py-2 px-3 text-sm">
                        @php $selectedIds = $marketerCampaign->categoryRules->pluck($relation)->filter()->pluck('id')->all(); @endphp
                        @foreach($options as $option)
                            <option value="{{ $option->id }}" @selected(in_array($option->id, $selectedIds, true))>{{ $option->name_ar }}</option>
                        @endforeach
                    </select>
                    <button class="px-4 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-lg hover:bg-gray-900">حفظ</button>
                </form>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- ── Approval ──────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'commission'">
        <x-card title="{{ __('admin.marketer_campaigns.approve_form_title') }}">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.commission_type') }}</div>
                    <div class="font-semibold text-gray-800">{{ $marketerCampaign->commission_type ? __('admin.marketer_campaigns.commission_type_' . $marketerCampaign->commission_type) : '—' }}</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.max_commission_budget') }}</div>
                    <div class="font-semibold text-gray-800">
                        {{ $marketerCampaign->max_commission_budget !== null ? number_format($marketerCampaign->max_commission_budget) : '—' }}
                        {{ $marketerCampaign->currency }}
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.influencer_commission_label') }}</div>
                    <div class="font-semibold text-green-700">
                        {{ number_format($commissionSetting?->influencer_commission_amount ?? 0) }}
                        {{ $marketerCampaign->currency }}
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 text-center">
                    <div class="text-xs text-gray-500 mb-1">{{ __('admin.marketer_campaigns.affiliate_commission_label') }}</div>
                    <div class="font-semibold text-green-700">
                        {{ number_format($commissionSetting?->affiliate_commission_amount ?? 0) }}
                        {{ $marketerCampaign->currency }}
                    </div>
                </div>
            </div>

            @if($isPending)
                <form method="POST" action="{{ route('admin.marketer-campaigns.approve', $marketerCampaign) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.marketer_campaigns.approve_btn') }}</button>
                </form>
            @else
                <p class="text-sm text-gray-500">{{ __('admin.marketer_campaigns.not_pending_notice') }}</p>
            @endif
        </x-card>
    </div>

    {{-- ── Marketers (read-only — invitations already sent at campaign creation) ── --}}
    <div x-show="tab === 'marketers'">
        <x-card title="{{ __('admin.marketer_campaigns.tab_marketers') }} ({{ $marketerCampaign->invitations->count() }})">
            @forelse($marketerCampaign->invitations as $inv)
                <div class="flex items-center justify-between p-3 rounded-lg border mb-2
                    {{ $inv->status === 'accepted' ? 'bg-green-50 border-green-200' :
                       ($inv->status === 'rejected' ? 'bg-red-50 border-red-200' :
                       ($inv->status === 'timed_out' ? 'bg-gray-50 border-gray-200' : 'bg-yellow-50 border-yellow-200')) }}">
                    <div>
                        <span class="font-medium text-gray-800">{{ $inv->marketer?->name ?? '—' }}</span>
                        <span class="text-xs ml-2 px-2 py-0.5 rounded-full
                            {{ $inv->marketer?->isInfluencer() ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $inv->marketer?->isInfluencer() ? 'إنفلوينسر' : 'أفيلييت' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        @if($inv->referral_code)
                            <code class="bg-white px-2 py-1 rounded border text-xs">{{ $inv->referral_code }}</code>
                        @endif
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            {{ $inv->status === 'accepted' ? 'bg-green-100 text-green-700' :
                               ($inv->status === 'rejected' ? 'bg-red-100 text-red-700' :
                               ($inv->status === 'timed_out' ? 'bg-gray-100 text-gray-600' : 'bg-yellow-100 text-yellow-700')) }}">
                            {{ $inv->status }}
                        </span>
                        @if($inv->expires_at && $inv->status === 'pending')
                            <span class="text-xs text-gray-400">{{ $inv->expires_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('admin.marketer_campaigns.no_invitations') }}</p>
            @endforelse
        </x-card>
    </div>

    @if($isPending)
        <div x-show="tab === 'commission'" class="mt-4">
            <x-card title="{{ __('admin.marketer_campaigns.reject_form_title') }}">
                <form method="POST" action="{{ route('admin.marketer-campaigns.reject', $marketerCampaign) }}">
                    @csrf
                    <x-form.textarea name="rejection_reason" label="{{ __('admin.marketer_campaigns.rejection_reason_label') }}" rows="3" required />
                    <button type="submit" class="btn btn-danger btn-sm mt-3">{{ __('admin.marketer_campaigns.reject_btn') }}</button>
                </form>
            </x-card>
        </div>
    @endif

    {{-- ── Tiered Rules ─────────────────────────────────────────────────── --}}
    <div x-show="tab === 'tiered'">
        <x-card title="{{ __('admin.marketer_campaigns.tab_tiered') }}">
            @if($marketerCampaign->commission_type === 'tiered' && $marketerCampaign->tieredRules->isNotEmpty())
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.from_sale_number') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.platform_commission_amount') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.currency') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($marketerCampaign->tieredRules as $rule)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 pr-4">{{ $rule->from_sale_number }}</td>
                                <td class="py-2 pr-4">{{ number_format($rule->commission_amount) }}</td>
                                <td class="py-2 pr-4">{{ $rule->currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-gray-400">{{ __('admin.marketer_campaigns.no_tiered_rules') }}</p>
            @endif
        </x-card>
    </div>

    {{-- ── Invitations ──────────────────────────────────────────────────── --}}
    <div x-show="tab === 'invitations'">
        <x-card title="{{ __('admin.marketer_campaigns.tab_invitations') }}">
            @can('marketer_campaigns.create')
                @if(in_array($marketerCampaign->status, ['pending_admin', 'active']))
                    <x-slot name="actions">
                        <button type="button" @click="document.getElementById('invite-marketers-modal').classList.remove('hidden')"
                                class="btn btn-primary btn-sm">
                            <i class="fas fa-user-plus"></i> {{ __('admin.marketer_campaigns.invite_marketers') }}
                        </button>
                    </x-slot>
                @endif
            @endcan
            @if($marketerCampaign->invitations->isEmpty())
                <p class="text-sm text-gray-400">{{ __('admin.marketer_campaigns.no_invitations') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-100">
                                <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_marketer') }}</th>
                                <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_status') }}</th>
                                <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_referral_code') }}</th>
                                <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_referral_link') }}</th>
                                <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_qr_code') }}</th>
                                <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_sent_at') }}</th>
                                <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.invitation_responded_at') }}</th>
                                <th class="text-center px-4 py-2 font-medium text-gray-600">رسوم المنصة</th>
                                <th class="text-center px-4 py-2 font-medium text-gray-600">حالة الرسوم</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($marketerCampaign->invitations as $invitation)
                                <tr class="border-b border-gray-50">
                                    <td class="py-2 pr-4 font-medium text-gray-900">{{ $invitation->marketer?->name ?? '—' }}</td>
                                    <td class="py-2 pr-4"><x-badge color="gray">{{ $invitation->status }}</x-badge></td>
                                    <td class="py-2 pr-4 font-mono text-xs">{{ $invitation->referral_code ?? '—' }}</td>
                                    <td class="py-2 pr-4">
                                        @if($invitation->referral_link)
                                            <a href="{{ $invitation->referral_link }}" target="_blank" class="text-primary-600 hover:underline text-xs">{{ $invitation->referral_link }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4">
                                        @if($invitation->qr_code_path)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($invitation->qr_code_path) }}" class="w-10 h-10 object-contain" alt="QR">
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4">{{ $invitation->created_at?->format('d M Y H:i') }}</td>
                                    <td class="py-2 pr-4">{{ $invitation->responded_at?->format('d M Y H:i') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($invitation->platform_fee_status === 'not_applicable')
                                            <span class="text-xs text-green-600">مجاني (أفيلييت)</span>
                                        @else
                                            <span class="font-semibold text-gray-800">
                                                {{ number_format($invitation->platform_fee_amount) }} {{ $invitation->platform_fee_currency }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($invitation->platform_fee_status === 'not_applicable')
                                            <span class="text-xs text-gray-300">—</span>
                                        @elseif($invitation->platform_fee_status === 'paid')
                                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                                <i class="fas fa-check mr-1"></i>مدفوعة
                                            </span>
                                        @elseif($invitation->platform_fee_status === 'pending')
                                            <div class="flex items-center gap-1 justify-center">
                                                <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">
                                                    انتظار
                                                </span>
                                                @can('marketer_campaigns.approve')
                                                <form action="{{ route('admin.marketer-campaigns.invitations.mark-fee-paid', [$marketerCampaign, $invitation]) }}"
                                                      method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                            class="text-xs text-orange-600 hover:text-orange-800 underline">
                                                        تسجيل كمدفوعة
                                                    </button>
                                                </form>
                                                @endcan
                                            </div>
                                        @elseif($invitation->platform_fee_status === 'waived')
                                            <span class="text-xs text-gray-400">معفاة</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        @can('marketer_campaigns.create')
            @if(in_array($marketerCampaign->status, ['pending_admin', 'active']))
                @php
                    $alreadyInvitedIds = $marketerCampaign->invitations
                        ->whereIn('status', ['pending', 'accepted'])
                        ->pluck('marketer_id')
                        ->all();
                    $availableMarketers = \App\Models\Marketer::where('global_status', 'active')
                        ->where('country_id', $marketerCampaign->country_id)
                        ->whereNotIn('id', $alreadyInvitedIds)
                        ->orderBy('name')
                        ->get();
                @endphp
                <div id="invite-marketers-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                    <div class="bg-white rounded-2xl p-6 w-full max-w-lg">
                        <h3 class="text-lg font-semibold mb-4">{{ __('admin.marketer_campaigns.invite_marketers') }}</h3>
                        <form method="POST"
                              action="{{ route('admin.marketer-campaigns.invite-marketers', $marketerCampaign) }}">
                            @csrf

                            @if($availableMarketers->isEmpty())
                                <p class="text-sm text-gray-500">
                                    {{ __('admin.marketer_campaigns.no_marketers_available') }}
                                </p>
                            @else
                                <x-form.select
                                    name="marketer_ids"
                                    label="{{ __('admin.marketer_campaigns.select_marketers') }}"
                                    :multiple="true"
                                    :select2="true"
                                    :options="$availableMarketers->mapWithKeys(fn ($m) =>
                                        [$m->id => $m->name . ' (' . ucfirst($m->marketerJobs->first()?->key ?? '') . ')'])->toArray()"
                                />
                            @endif

                            <div class="flex gap-3 mt-6">
                                <button type="submit" class="btn btn-primary" @if($availableMarketers->isEmpty()) disabled @endif>
                                    {{ __('admin.marketer_campaigns.invite_marketers') }}
                                </button>
                                <button type="button"
                                        onclick="document.getElementById('invite-marketers-modal').classList.add('hidden')"
                                        class="btn btn-ghost">
                                    {{ __('admin.marketer_campaigns.cancel') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan
    </div>

    {{-- ── Conversions ──────────────────────────────────────────────────── --}}
    <div x-show="tab === 'conversions'">
        <x-card title="{{ __('admin.marketer_campaigns.tab_conversions') }}">
            @if($marketerCampaign->conversions->isEmpty())
                <p class="text-sm text-gray-400">{{ __('admin.marketer_campaigns.no_conversions') }}</p>
            @else
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.conversion_order') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.conversion_commission') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.conversion_clicked_at') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.conversion_paid_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($marketerCampaign->conversions as $conversion)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 pr-4">{{ $conversion->order?->order_number ?? $conversion->order_id }}</td>
                                <td class="py-2 pr-4">{{ number_format($conversion->commission_amount) }} {{ $conversion->currency }}</td>
                                <td class="py-2 pr-4">{{ $conversion->referral_clicked_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if($conversion->paid_at)
                                        {{ $conversion->paid_at->format('d M Y H:i') }}
                                    @else
                                        <x-badge color="warning">{{ __('admin.marketer_campaigns.conversion_not_paid') }}</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>

    {{-- ── Samples ───────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'samples'">
        <x-card title="{{ __('admin.marketer_campaigns.tab_samples') }}">
            @if($marketerCampaign->samples->isEmpty())
                <p class="text-sm text-gray-400">{{ __('admin.marketer_campaigns.no_samples') }}</p>
            @else
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.sample_owner') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.sample_marketer') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.sample_quantity') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.sample_status') }}</th>
                            <th class="py-2 pr-4">عنوان التوصيل</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.sample_dispatched_at') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.sample_delivered_at') }}</th>
                            <th class="py-2 pr-4">{{ __('admin.marketer_campaigns.sample_change_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($marketerCampaign->samples as $sample)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 pr-4">
                                    {{ $sample->sample_owner === 'platform' ? __('admin.marketer_campaigns.sample_owner_platform') : __('admin.marketer_campaigns.sample_owner_marketer') }}
                                </td>
                                <td class="py-2 pr-4">{{ $sample->invitation?->marketer?->name ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $sample->quantity }}</td>
                                <td class="py-2 pr-4">
                                    <x-badge color="{{ $sample->status === 'pending' ? 'warning' : ($sample->status === 'dispatched' ? 'primary' : ($sample->status === 'delivered' ? 'success' : 'danger')) }}">
                                        {{ match($sample->status) {
                                            'pending'    => __('admin.marketer_campaigns.sample_status_pending'),
                                            'dispatched' => __('admin.marketer_campaigns.sample_status_dispatched'),
                                            'delivered'  => __('admin.marketer_campaigns.sample_status_delivered'),
                                            'returned'   => __('admin.marketer_campaigns.sample_status_returned'),
                                            default      => $sample->status,
                                        } }}
                                    </x-badge>
                                </td>
                                <td class="py-2 pr-4 text-xs text-gray-600 max-w-xs">
                                    @if($sample->sample_owner === 'marketer' && $sample->delivery_address_snapshot)
                                        <div class="space-y-0.5">
                                            <div class="font-medium">{{ $sample->delivery_address_snapshot['address_line_1'] ?? '' }}</div>
                                            @if(!empty($sample->delivery_address_snapshot['address_line_2']))
                                                <div>{{ $sample->delivery_address_snapshot['address_line_2'] }}</div>
                                            @endif
                                            <div>{{ $sample->delivery_address_snapshot['city'] ?? '' }}, {{ $sample->delivery_address_snapshot['country'] ?? '' }}</div>
                                            <div class="text-blue-600">{{ $sample->delivery_address_snapshot['phone'] ?? '' }}</div>
                                            @if(!empty($sample->delivery_address_snapshot['notes']))
                                                <div class="text-gray-400 italic">{{ $sample->delivery_address_snapshot['notes'] }}</div>
                                            @endif
                                        </div>
                                    @elseif($sample->sample_owner === 'marketer' && !$sample->delivery_address_snapshot)
                                        <span class="text-amber-600 font-semibold">⚠ لم يُسجَّل عنوان بعد</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $sample->dispatched_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $sample->delivered_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if($sample->status !== 'delivered' && $sample->status !== 'returned')
                                        <form action="{{ route('admin.marketer-campaigns.samples.update', [$marketerCampaign, $sample]) }}"
                                              method="POST" class="inline-flex gap-1">
                                            @csrf
                                            @method('PATCH')

                                            @if($sample->status === 'pending')
                                                <button type="submit" name="status" value="dispatched"
                                                        class="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                                    {{ __('admin.marketer_campaigns.sample_action_dispatch') }}
                                                </button>
                                            @endif

                                            @if($sample->status === 'dispatched')
                                                <button type="submit" name="status" value="delivered"
                                                        class="text-xs bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                                                    {{ __('admin.marketer_campaigns.sample_action_deliver') }}
                                                </button>
                                            @endif

                                            <button type="submit" name="status" value="returned"
                                                    class="text-xs bg-red-100 text-red-600 px-3 py-1 rounded hover:bg-red-200"
                                                    onclick="return confirm('{{ __('admin.marketer_campaigns.sample_confirm_return') }}')">
                                                {{ __('admin.marketer_campaigns.sample_action_return') }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                            @php $sampleProduct = $sample->product(); @endphp
                            @if($sample->customAttributeValues->isNotEmpty())
                                <tr>
                                    <td colspan="8" class="px-4 py-2 bg-purple-50 text-xs">
                                        <strong class="text-purple-700">{{ __('admin.marketer_campaigns.sample_custom_details') }}:</strong>
                                        @foreach($sample->customAttributeValues as $val)
                                            <span class="me-3">{{ $val->label }}: <strong>{{ $val->value }}</strong> {{ $val->unit }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @elseif($sampleProduct?->has_custom_attributes)
                                <tr>
                                    <td colspan="8" class="px-4 py-2 bg-amber-50 text-xs text-amber-700">
                                        {{ __('admin.marketer_campaigns.sample_awaiting_vendor_details') }}
                                    </td>
                                </tr>
                            @endif
                            @php
                                $sampleMarketer = $sample->invitation?->marketer;
                                $sampleProfile  = $sampleMarketer?->marketerProfile;
                                $isInfluencer   = $sampleMarketer?->isInfluencer() ?? false;
                            @endphp
                            @if ($isInfluencer && $sampleProfile)
                                <tr>
                                    <td colspan="8" class="px-4 py-3 bg-blue-50 border-t border-blue-100">
                                        <div class="text-xs font-semibold text-blue-800 mb-2">
                                            {{ __('admin.marketer_campaigns.influencer_measurements') }}
                                        </div>
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-1 text-xs">
                                            @foreach([
                                                'clothing_size'   => $sampleProfile->clothing_size,
                                                'shirt_size'      => $sampleProfile->shirt_size,
                                                'pants_size'      => $sampleProfile->pants_size,
                                                'dress_size'      => $sampleProfile->dress_size,
                                                'abaya_size'      => $sampleProfile->abaya_size,
                                                'shoe_size'       => $sampleProfile->shoe_size
                                                                      ? "{$sampleProfile->shoe_size} ({$sampleProfile->shoe_size_system})"
                                                                      : null,
                                                'chest_cm'        => $sampleProfile->chest_cm,
                                                'waist_cm'        => $sampleProfile->waist_cm,
                                                'hip_cm'          => $sampleProfile->hip_cm,
                                                'height_cm'       => $sampleProfile->height_cm,
                                                'item_length_cm'          => $sampleProfile->item_length_cm,
                                                'sleeve_from_neck_cm'     => $sampleProfile->sleeve_from_neck_cm,
                                                'sleeve_from_shoulder_cm' => $sampleProfile->sleeve_from_shoulder_cm,
                                                'sleeve_width_cm'         => $sampleProfile->sleeve_width_cm,
                                            ] as $label => $value)
                                                @if(!is_null($value) && $value !== '')
                                                <div>
                                                    <span class="text-gray-500">{{ __('admin.marketer_campaigns.measurement_' . $label) }}:</span>
                                                    <span class="font-medium text-gray-900">{{ $value }}</span>
                                                </div>
                                                @endif
                                            @endforeach
                                        </div>
                                        @if ($sampleProfile->measurements_notes)
                                        <div class="text-xs text-gray-600 mt-2">
                                            <span class="font-semibold">{{ __('admin.marketer_campaigns.measurement_notes') }}:</span>
                                            {{ $sampleProfile->measurements_notes }}
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @elseif ($isInfluencer && !$sampleProfile)
                                <tr>
                                    <td colspan="8" class="px-4 py-2 bg-amber-50 text-xs text-amber-700">
                                        {{ __('admin.marketer_campaigns.no_measurements_on_file') }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-100">
                        <tr>
                            <td colspan="2" class="py-2 pr-4 font-semibold text-gray-700">
                                {{ __('admin.marketer_campaigns.sample_total') }}
                            </td>
                            <td class="py-2 pr-4 font-bold text-gray-900">
                                {{ $marketerCampaign->samples->sum('quantity') }}
                            </td>
                            <td colspan="4" class="py-2 pr-4 text-xs text-gray-500">
                                {{ $marketerCampaign->samples->where('status', 'delivered')->count() }}
                                / {{ $marketerCampaign->samples->count() }}
                                {{ __('admin.marketer_campaigns.sample_delivered_count') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </x-card>
    </div>
</div>

@endsection
