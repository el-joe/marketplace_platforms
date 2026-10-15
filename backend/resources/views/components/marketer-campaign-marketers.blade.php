@props(['campaign', 'calculations'])

@php
    $invStatusMap = [
        'pending'   => ['label' => __('partner.marketer_campaigns_my.invitation_status.pending'),   'cls' => 'bg-yellow-100 text-yellow-700'],
        'accepted'  => ['label' => __('partner.marketer_campaigns_my.invitation_status.accepted'),  'cls' => 'bg-green-100 text-green-700'],
        'declined'  => ['label' => __('partner.marketer_campaigns_my.invitation_status.declined'),  'cls' => 'bg-red-100 text-red-700'],
        'timed_out' => ['label' => __('partner.marketer_campaigns_my.invitation_status.timed_out'), 'cls' => 'bg-gray-100 text-gray-500'],
    ];
    $marketerCampaign = $campaign;
@endphp

{{-- Invited / joined marketers of a campaign with how each one is paid and his referral link. Shared by the vendor and
     travel agency campaign pages. --}}
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            @if ($marketerCampaign->invitations->isEmpty())
                <p class="p-6 text-center text-sm text-gray-400">{{ __('partner.marketer_campaigns_my.no_invitations') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.marketer') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.type') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.status') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('marketer_campaigns.cost.paid_how_col') }} <span class="block text-[10px] font-normal text-gray-400">{{ __('partner.marketer_campaigns.set_by_platform_label') }}</span></th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.referral_link') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.qr_code') }}</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.responded_at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($marketerCampaign->invitations as $invitation)
                                @php
                                    $ist = $invStatusMap[$invitation->status] ?? ['label' => $invitation->status, 'cls' => 'bg-gray-100 text-gray-600'];
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $invitation->marketer?->name ?? '—' }}</td>
                                    @php
                                        $invitationMarketerType = $invitation->marketer?->marketerJobs->first()?->key;
                                    @endphp
                                    <td class="px-4 py-3 text-gray-600">{{ $invitationMarketerType ? __('partner.marketer_types.' . $invitationMarketerType) : '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $ist['cls'] }}">
                                            {{ $ist['label'] }}
                                        </span>
                                    </td>
                                    @php $calc = $calculations[$invitation->id] ?? null; @endphp
                                    <td class="px-4 py-3 text-gray-700">
                                        @if ($calc && $calc->perCampaign)
                                            {{ \App\Support\Money::format($invitation->influencer_fixed_amount ?? $calc->gross).' '.$marketerCampaign->currency }}
                                            <span class="block text-[11px] text-purple-600">{{ __('marketer_campaigns.calc.fixed_status.'.($invitation->influencer_fixed_status ?: 'none')) }}</span>
                                        @elseif ($calc && $calc->mode !== \App\Services\Marketer\CommissionResult::MODE_NONE)
                                            <span class="block">{{ __('marketer_campaigns.cost.per_sale_'.$calc->mode, ['rate' => rtrim(rtrim(number_format((float) $calc->rate, 2), '0'), '.'), 'fixed' => \App\Support\Money::format($calc->fixedAmount ?? 0).' '.$marketerCampaign->currency]) }}</span>
                                        @else
                                            <span class="text-xs text-gray-400">{{ __('partner.marketer_campaigns.commission_not_set') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($invitation->referral_link)
                                            <div class="flex items-center gap-2">
                                                <input type="text" readonly value="{{ $invitation->referral_link }}"
                                                       class="w-40 truncate rounded border border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-600" />
                                                <button type="button"
                                                        class="text-xs font-medium text-primary-600 hover:underline"
                                                        data-link="{{ $invitation->referral_link }}"
                                                        data-copy-label="{{ __('partner.marketer_campaigns_my.copy') }}"
                                                        data-copied-label="{{ __('partner.marketer_campaigns_my.copied') }}"
                                                        onclick="copyToClipboard(this.dataset.link); const btn = this; btn.textContent = btn.dataset.copiedLabel; setTimeout(() => btn.textContent = btn.dataset.copyLabel, 1500)">
                                                    {{ __('partner.marketer_campaigns_my.copy') }}
                                                </button>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($invitation->qr_code_path)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($invitation->qr_code_path) }}" alt="QR" class="h-10 w-10 rounded border border-gray-200 object-contain">
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $invitation->responded_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

