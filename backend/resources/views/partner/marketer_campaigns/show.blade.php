@extends('layouts.partner')

@php
    $product = $marketerCampaign->vendorListing?->productVariant?->product
        ?? $marketerCampaign->adminListing?->productVariant?->product;
    $campaignVertical = in_array($marketerCampaign->campaign_category, ['classified', 'travel'], true) ? $marketerCampaign->campaign_category : 'product';
    $isProductCampaign = $campaignVertical === 'product';
    $promotedTitle = $marketerCampaign->getPromotedTitle();

    $statusMap = [
        'pending_admin' => ['label' => __('partner.marketer_campaigns_my.status.pending_admin'), 'cls' => 'bg-yellow-100 text-yellow-700'],
        'active'        => ['label' => __('partner.marketer_campaigns_my.status.active'),        'cls' => 'bg-green-100 text-green-700'],
        'auto_approved' => ['label' => __('partner.marketer_campaigns_my.status.auto_approved'),  'cls' => 'bg-blue-100 text-blue-700'],
        'rejected'      => ['label' => __('partner.marketer_campaigns_my.status.rejected'),       'cls' => 'bg-red-100 text-red-700'],
        'done'          => ['label' => __('partner.marketer_campaigns_my.status.done'),           'cls' => 'bg-gray-100 text-gray-500'],
        'cancelled'     => ['label' => __('partner.marketer_campaigns_my.status.cancelled'),      'cls' => 'bg-gray-100 text-gray-400'],
        'paused'        => ['label' => __('partner.marketer_campaigns_my.status.paused'),         'cls' => 'bg-gray-100 text-gray-600'],
        'marketer_requested' => ['label' => __('partner.marketer_campaigns_my.status.marketer_requested'), 'cls' => 'bg-yellow-100 text-yellow-700'],
    ];
    $st = $statusMap[$marketerCampaign->status] ?? ['label' => $marketerCampaign->status, 'cls' => 'bg-gray-100 text-gray-600'];

    $invStatusMap = [
        'pending'   => ['label' => __('partner.marketer_campaigns_my.invitation_status.pending'),   'cls' => 'bg-yellow-100 text-yellow-700'],
        'accepted'  => ['label' => __('partner.marketer_campaigns_my.invitation_status.accepted'),  'cls' => 'bg-green-100 text-green-700'],
        'declined'  => ['label' => __('partner.marketer_campaigns_my.invitation_status.declined'),  'cls' => 'bg-red-100 text-red-700'],
        'timed_out' => ['label' => __('partner.marketer_campaigns_my.invitation_status.timed_out'), 'cls' => 'bg-gray-100 text-gray-500'],
    ];

    $isPendingCancellable = $marketerCampaign->status === 'pending_admin';
    $mcVendorUser = auth('vendor')->user();
    $canEditCampaign = in_array($marketerCampaign->status, ['pending_admin', 'marketer_requested'], true) && $mcVendorUser?->hasPermissionTo('marketer_campaigns.edit');
    $canDeleteCampaign = in_array($marketerCampaign->status, ['cancelled', 'rejected', 'done', 'completed', 'pending_admin'], true) && $mcVendorUser?->hasPermissionTo('marketer_campaigns.delete');
    $canInviteMore = in_array($marketerCampaign->status, ['active'], true) && $mcVendorUser?->hasPermissionTo('marketer_campaigns.create');
@endphp

@section('title', $marketerCampaign->title ?: __('partner.marketer_campaigns_my.title'))
@section('page-title', __('partner.marketer_campaigns_my.title'))

@section('content')
<div class="px-4 py-6 sm:px-6 lg:px-8 space-y-6" x-data="{ tab: 'info' }">

    @if($marketerCampaign->vendorListing && $marketerCampaign->vendorListing->fulfillment_model !== \App\Enums\FulfillmentModel::Fbn)
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
            {{ __('partner.marketer_campaigns.ship_to_warehouse_notice') }} {{ __('partner.marketer_campaigns.ship_to_warehouse_notice_extra') }}
        </div>
    @endif

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('partner.marketer-campaigns.index') }}" class="hover:text-primary-600">{{ __('partner.marketer_campaigns_my.title') }}</a>
        <svg class="h-4 w-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
        </svg>
        <span class="text-gray-800 font-medium truncate max-w-xs">{{ $marketerCampaign->title ?: $promotedTitle }}</span>
    </nav>

    {{-- Header --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ $marketerCampaign->title ?: $promotedTitle }}</h1>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $st['cls'] }}">
                {{ $st['label'] }}
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
        @if ($canEditCampaign)
            <a href="{{ route('partner.marketer-campaigns.edit', $marketerCampaign) }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                {{ __('partner.marketer_campaigns_my.edit') }}
            </a>
        @endif
        @if ($canInviteMore)
            <button type="button" @click="tab = 'marketers'; $nextTick(() => document.getElementById('invite-marketers-modal')?.classList.remove('hidden'))"
                    class="inline-flex items-center gap-2 rounded-lg border border-purple-300 bg-white px-4 py-2 text-sm font-semibold text-purple-700 hover:bg-purple-50 transition-colors">
                {{ __('partner.marketer_campaigns_my.invite_marketers') }}
            </button>
        @endif
        @if ($canDeleteCampaign)
            <form method="POST" action="{{ route('partner.marketer-campaigns.destroy', $marketerCampaign) }}"
                  onsubmit="return confirm(@js(__('partner.marketer_campaigns_my.confirm_delete')))">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors">
                    {{ __('partner.marketer_campaigns_my.delete') }}
                </button>
            </form>
        @endif
        @if ($isPendingCancellable)
            <form method="POST" action="{{ route('partner.marketer-campaigns.cancel', $marketerCampaign) }}"
                  onsubmit="return confirm('{{ __('partner.marketer_campaigns_my.confirm_cancel') }}')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors">
                    {{ __('partner.marketer_campaigns_my.cancel') }}
                </button>
            </form>
        @endif
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Tab nav --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-x-6 overflow-x-auto">
            @foreach (array_filter([
                'info'        => __('partner.marketer_campaigns_my.tab_info'),
                'marketers'   => __('partner.marketer_campaigns_my.tab_marketers'),
                'conversions' => __('partner.marketer_campaigns_my.tab_conversions'),
                'samples'     => $isProductCampaign ? __('partner.marketer_campaigns_my.tab_samples') : null,
            ]) as $tabKey => $tabLabel)
                <button type="button"
                    @click="tab = '{{ $tabKey }}'"
                    :class="tab === '{{ $tabKey }}' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ═══ Tab 1: Campaign Info ═══ --}}
    <div x-show="tab === 'info'" x-cloak class="space-y-6">
        <x-marketer-campaign-info :campaign="$marketerCampaign" :calculations="$calculations" :price="$examplePrice" />
    </div>

    {{-- ═══ Tab 2: Invited Marketers ═══ --}}
    <div x-show="tab === 'marketers'" x-cloak>
        @if (auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.create') && in_array($marketerCampaign->status, ['pending_admin', 'active']))
            <div class="flex justify-end mb-4">
                <button type="button" onclick="document.getElementById('invite-marketers-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors">
                    {{ __('partner.marketer_campaigns_my.invite_marketers') }}
                </button>
            </div>
        @endif
        <x-marketer-campaign-marketers :campaign="$marketerCampaign" :calculations="$calculations" />

        @if (auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.create') && in_array($marketerCampaign->status, ['pending_admin', 'active']))
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
                $availableMarketerOptions = $availableMarketers->mapWithKeys(fn ($m) => [$m->id => $m->name.' ('.__('partner.marketer_types.'.$m->marketerJobs->first()?->key).')'])->toArray();
            @endphp
            <div id="invite-marketers-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-2xl p-6 w-full max-w-lg">
                    <h3 class="text-lg font-semibold mb-4">{{ __('partner.marketer_campaigns_my.invite_marketers') }}</h3>
                    <form method="POST"
                          action="{{ route('partner.marketer-campaigns.invite-marketers', $marketerCampaign) }}">
                        @csrf

                        @if ($availableMarketers->isEmpty())
                            <p class="text-sm text-gray-500">
                                {{ __('partner.marketer_campaigns_my.no_marketers_available') }}
                            </p>
                        @else
                            <x-form.select
                                name="marketer_ids"
                                label="{{ __('partner.marketer_campaigns_my.select_marketers') }}"
                                :multiple="true"
                                :select2="true"
                                :options="$availableMarketerOptions"
                            />
                        @endif

                        <div class="flex gap-3 mt-6">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors"
                                    @if ($availableMarketers->isEmpty()) disabled @endif>
                                {{ __('partner.marketer_campaigns_my.invite_marketers') }}
                            </button>
                            <button type="button"
                                    onclick="document.getElementById('invite-marketers-modal').classList.add('hidden')"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                                {{ __('partner.marketer_campaigns_my.close_modal') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    {{-- ═══ Tab 3: Conversions ═══ --}}
    <div x-show="tab === 'conversions'" x-cloak>
        <x-marketer-campaign-conversions :campaign="$marketerCampaign" />
    </div>

    {{-- ═══ Tab 4: Samples (product campaigns only) ═══ --}}
    @if ($isProductCampaign)
    <div x-show="tab === 'samples'" x-cloak class="space-y-6" x-data="sampleAttributesModal()">
        @php
            $platformSamples = $marketerCampaign->samples->where('sample_owner', 'platform');
            $marketerSamples = $marketerCampaign->samples->where('sample_owner', '!=', 'platform');
            $needsCustomAttributes = $product?->has_custom_attributes ?? false;
            $liveSamples = $marketerCampaign->samples->where('status', '!=', 'cancelled');
            $sampleStatusLabel = fn ($st) => __('partner.marketer_campaigns_my.sample_status.'.$st);
        @endphp

        {{-- Totals: 1 fixed admin sample + 1 per influencer (+ affiliate row) --}}
        <div class="rounded-xl border border-purple-200 bg-purple-50 px-5 py-3 text-sm text-purple-900" data-testid="samples-summary">
            {{ __('partner.marketer_campaigns_my.samples_summary', ['count' => $liveSamples->count(), 'units' => $liveSamples->sum('quantity'), 'dispatched' => $liveSamples->whereNotNull('stock_deducted_at')->count()]) }}
            <span class="block text-xs text-purple-700 mt-1">{{ __('partner.marketer_campaigns_my.samples_accounting_note') }}</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.platform_samples') }}</h3>
            @if ($platformSamples->isEmpty())
                <p class="text-sm text-gray-400">{{ __('partner.marketer_campaigns_my.no_samples') }}</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($platformSamples as $sample)
                        <li class="rounded-lg border border-gray-100 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700">
                                    {{ __('partner.marketer_campaigns_my.sample_type.'.($sample->sample_type ?? 'admin')) }}
                                    &middot; {{ __('partner.marketer_campaigns_my.field.quantity') }}: {{ $sample->quantity }}
                                </span>
                                <span class="text-gray-500">
                                    {{ $sampleStatusLabel($sample->status) }}
                                    @if ($sample->stock_deducted_at)
                                        &middot; {{ __('partner.marketer_campaigns_my.field.stock_deducted') }}
                                        &middot; {{ __('partner.marketer_campaigns_my.field.commission_charged') }}: {{ \App\Support\Money::format($sample->commission_amount) }} {{ $sample->commission_currency }}
                                    @endif
                                </span>
                            </div>
                            @if ($needsCustomAttributes)
                                @include('partner.marketer_campaigns._sample_custom_attributes', ['sample' => $sample])
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <h3 class="px-5 pt-4 text-sm font-semibold text-gray-800">{{ __('partner.marketer_campaigns_my.per_marketer_samples') }}</h3>
            @if ($marketerSamples->isEmpty())
                <p class="p-6 text-center text-sm text-gray-400">{{ __('partner.marketer_campaigns_my.no_samples') }}</p>
            @else
                <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.marketer') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.quantity') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.status') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.stock_deducted') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.commission_charged') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.dispatched_at') }}</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-600">{{ __('partner.marketer_campaigns_my.field.delivered_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($marketerSamples as $sample)
                            <tr class="{{ $sample->status === 'cancelled' ? 'text-gray-400 line-through' : '' }}">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $sample->invitation?->marketer?->name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $sample->quantity }}</td>
                                <td class="px-4 py-2">{{ $sampleStatusLabel($sample->status) }}</td>
                                <td class="px-4 py-2">{{ $sample->stock_deducted_at ? __('common.yes') : '—' }}</td>
                                <td class="px-4 py-2">{{ $sample->stock_deducted_at ? \App\Support\Money::format($sample->commission_amount).' '.$sample->commission_currency : '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $sample->dispatched_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $sample->delivered_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                            @if ($needsCustomAttributes)
                                <tr>
                                    <td colspan="7" class="px-4 pb-2">
                                        @include('partner.marketer_campaigns._sample_custom_attributes', ['sample' => $sample])
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
                                    <td colspan="7" class="px-4 py-3 bg-blue-50 border-t border-blue-100">
                                        <div class="text-xs font-semibold text-blue-800 mb-2">
                                            {{ __('partner.marketer_campaigns_my.influencer_measurements') }}
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
                                                    <span class="text-gray-500">{{ __('partner.marketer_campaigns_my.measurement_' . $label) }}:</span>
                                                    <span class="font-medium text-gray-900">{{ $value }}</span>
                                                </div>
                                                @endif
                                            @endforeach
                                        </div>
                                        @if ($sampleProfile->measurements_notes)
                                        <div class="text-xs text-gray-600 mt-2">
                                            <span class="font-semibold">{{ __('partner.marketer_campaigns_my.measurement_notes') }}:</span>
                                            {{ $sampleProfile->measurements_notes }}
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                            @elseif ($isInfluencer && !$sampleProfile)
                                <tr>
                                    <td colspan="7" class="px-4 py-2 bg-amber-50 text-xs text-amber-700">
                                        {{ __('partner.marketer_campaigns_my.no_measurements_on_file') }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Fill-in modal, shared by every sample row above --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md" @click.outside="open = false">
                <h3 class="text-lg font-semibold mb-4">{{ __('partner.marketer_campaigns_my.sample_custom_details') }}</h3>

                <template x-for="attr in attributes" :key="attr.id">
                    <div class="mb-3">
                        <label class="block text-sm font-medium mb-1" x-text="attr.label"></label>
                        <input type="text" x-model="values[attr.id]"
                               class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                </template>

                <div class="flex gap-3 mt-4">
                    <button type="button" @click="submit()" class="btn btn-primary flex-1">{{ __('common.save') }}</button>
                    <button type="button" @click="open = false" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>

<script>
function sampleAttributesModal() {
    return {
        open: false,
        sampleId: null,
        attributes: [],
        values: {},

        openFor(sampleId, attributes, existingValues) {
            this.sampleId = sampleId;
            this.attributes = attributes;
            this.values = {};
            (existingValues || []).forEach(v => { this.values[v.product_custom_attribute_id] = v.value; });
            this.open = true;
        },

        async submit() {
            const payload = {
                values: this.attributes.map(a => ({
                    product_custom_attribute_id: a.id,
                    value: this.values[a.id] ?? '',
                })),
            };

            const url = '{{ route('partner.marketer-campaigns.samples.custom-attributes', ['marketerCampaign' => $marketerCampaign->id, 'sample' => 'SAMPLE_ID']) }}'.replace('SAMPLE_ID', this.sampleId);
            await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(payload),
            });

            window.location.reload();
        },
    };
}
</script>
@push('scripts')
    @vite(['resources/js/components/select2.js'])
@endpush
@endsection
