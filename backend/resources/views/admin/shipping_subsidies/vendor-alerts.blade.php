@extends('layouts.admin')

@section('title', 'Vendor Exceptional Zone Alerts')

@section('content')

    <div class="mb-6 flex items-center gap-2">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.vendor_exceptional_zone_alerts') }}</h1>
        @if($pending->count())
            <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full">{{ $pending->count() }} pending</span>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @forelse($pending as $entry)
        @php
            $alert = $entry['alert'];
            $zonesDetected = $entry['zones_detected'];
            $allZonesInCountry = $entry['all_zones_country'];
        @endphp
        <div class="bg-white border rounded-xl p-5 mb-4 shadow-sm">

            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <p class="font-semibold text-gray-900">{{ $alert->vendor->name ?? $alert->vendor->store_name ?? '—' }}</p>
                    <div class="mt-1 text-sm text-gray-600 space-y-0.5">
                        <p>Warehouse: <strong>{{ $alert->warehouse->name }}</strong> ({{ $alert->warehouse->code }})</p>
                        <p>Carrier: <strong>{{ $alert->carrier->name ?? 'All carriers' }}</strong></p>
                        <p class="text-orange-700">
                            {{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.reported_carrier_fee') }} <strong>{{ $alert->reported_carrier_fee }} {{ $alert->currency }}</strong>
                        </p>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach($zonesDetected as $group)
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $group['has_zone'] ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                                {{ $group['zone_name'] }} ({{ $group['cities']->count() }})
                            </span>
                        @endforeach
                    </div>
                    @if($alert->vendor_note)
                        <p class="mt-2 text-sm text-gray-500 italic">"{{ $alert->vendor_note }}"</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-1">{{ $alert->created_at->diffForHumans() }}</p>
                </div>

                <div class="flex gap-2 flex-shrink-0">
                    <button type="button" onclick="openModal('accept-{{ $alert->id }}')"
                            class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">
                        Accept & Configure
                    </button>
                    <button type="button" onclick="toggleBlock('reject-{{ $alert->id }}')"
                            class="px-4 py-2 border border-red-300 text-red-600 text-sm rounded-lg hover:bg-red-50">
                        Reject
                    </button>
                </div>
            </div>

            {{-- Accept modal --}}
            <div id="accept-{{ $alert->id }}" class="hidden fixed inset-0 z-50 overflow-y-auto">
                <div class="fixed inset-0 bg-black/40" onclick="closeModal('accept-{{ $alert->id }}')"></div>
                <div class="relative min-h-full flex items-start justify-center p-4 py-10">
                    <div class="relative bg-white rounded-xl shadow-lg w-full max-w-3xl p-5">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900">Accept & Configure</h3>
                            <button type="button" onclick="closeModal('accept-{{ $alert->id }}')"
                                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                        </div>

                        <form method="POST" action="{{ route('admin.shipping-subsidies.alerts.accept', $alert) }}">
                            @csrf

                            <p class="text-sm font-semibold text-gray-700 mb-4">
                                Configure a subsidy for each zone below. Accepting will update the carrier rate,
                                register the exceptional zone for this warehouse, and create the subsidy split rule
                                for each zone.
                            </p>

                            @if(count($zonesDetected) > 1)
                                <div class="mb-4 p-3 bg-gray-50 border rounded-lg grid grid-cols-2 gap-4">
                                    @if(collect($zonesDetected)->contains(fn($g) => !$g['has_zone']))
                                        <div>
                                            <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.apply_one_zone_to_all_unassigned') }}</label>
                                            <div class="flex gap-2 mt-1">
                                                <select id="bulk-zone-{{ $alert->id }}" class="w-full border rounded-lg px-3 py-2 text-sm">
                                                    <option value="">Select a zone&hellip;</option>
                                                    @foreach($allZonesInCountry as $z)
                                                        <option value="{{ $z->id }}">{{ $z->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button" onclick="applyZoneToAll('{{ $alert->id }}')"
                                                        class="px-3 py-2 border rounded-lg text-sm whitespace-nowrap hover:bg-gray-100">
                                                    {{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.apply_to_all') }}
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    <div>
                                        <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.apply_one_shipping_method_to_all') }}</label>
                                        <div class="flex gap-2 mt-1">
                                            <select id="bulk-method-{{ $alert->id }}" class="w-full border rounded-lg px-3 py-2 text-sm">
                                                <option value="">Select a method&hellip;</option>
                                                @foreach($methods as $m)
                                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" onclick="applyMethodToAll('{{ $alert->id }}')"
                                                    class="px-3 py-2 border rounded-lg text-sm whitespace-nowrap hover:bg-gray-100">
                                                {{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.apply_to_all') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @foreach($zonesDetected as $i => $group)
                                <fieldset class="border rounded-lg p-4 mb-4">
                                    <legend class="text-xs font-semibold text-gray-600 px-2">
                                        Zone {{ $i + 1 }}: {{ $group['zone_name'] }}
                                        <span class="font-normal text-gray-400">
                                            ({{ $group['cities']->pluck('name_en')->join(', ') }})
                                        </span>
                                    </legend>

                                    @if($group['has_zone'])
                                        <input type="hidden" name="zone_configs[{{ $i }}][zone_id]" value="{{ $group['zone_id'] }}">
                                    @else
                                        <div class="mb-3">
                                            <label class="text-xs text-gray-500">
                                                {{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.these_cities_have_no_zone_assigned') }}
                                            </label>
                                            <select name="zone_configs[{{ $i }}][zone_id]" required
                                                    class="mt-1 w-full border rounded-lg px-3 py-2 text-sm zone-select-{{ $alert->id }}">
                                                <option value="">Select a zone&hellip;</option>
                                                @foreach($allZonesInCountry as $z)
                                                    <option value="{{ $z->id }}">{{ $z->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.shipping_method') }}</label>
                                            <select name="zone_configs[{{ $i }}][shipping_method_id]" required
                                                    class="mt-1 w-full border rounded-lg px-3 py-2 text-sm method-select-{{ $alert->id }}">
                                                <option value="all">{{ __('admin.shipping_subsidies.all_methods') }}</option>
                                                @foreach($methods as $m)
                                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.carrier_base_fee') }}</label>
                                            <input type="number" name="zone_configs[{{ $i }}][carrier_rate]" min="1" required
                                                   value="{{ $alert->reported_carrier_fee }}"
                                                   class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 mt-3">
                                        <div>
                                            <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.carrier_rate_per_kg') }}</label>
                                            <input type="number" name="zone_configs[{{ $i }}][carrier_rate_per_kg]" min="0" value="0" required
                                                   class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">Currency</label>
                                            <select name="zone_configs[{{ $i }}][currency]" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" required>
                                                @foreach(['SAR','AED','OMR','KWD','QAR','BHD','EGP','JOD'] as $c)
                                                    <option value="{{ $c }}" {{ $alert->currency === $c ? 'selected' : '' }}>{{ $c }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.admin_cap_per_delivery') }}</label>
                                        <input type="number" name="zone_configs[{{ $i }}][subsidy_cap]" min="0" value="0" required
                                               class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                                    </div>

                                    <div class="flex gap-6 mt-3 mb-3">
                                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                                            <input type="radio" name="zone_configs[{{ $i }}][split_type]" value="percentage"
                                                   onchange="setSplitType('{{ $alert->id }}-{{ $i }}', 'percentage')" checked>
                                            Percentage
                                        </label>
                                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                                            <input type="radio" name="zone_configs[{{ $i }}][split_type]" value="fixed"
                                                   onchange="setSplitType('{{ $alert->id }}-{{ $i }}', 'fixed')">
                                            {{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.fixed_amounts') }}
                                        </label>
                                    </div>

                                    <div id="split-percentage-{{ $alert->id }}-{{ $i }}" class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-xs text-gray-500">Vendor % of gap</label>
                                            <div class="flex items-center gap-2 mt-1">
                                                <input type="number" name="zone_configs[{{ $i }}][vendor_share_pct]" min="0" max="100" value="50" class="w-20 border rounded-lg px-3 py-2 text-sm">
                                                <span class="text-sm text-gray-500">%</span>
                                                <span class="text-xs text-gray-400">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.admin_absorbs_the_rest') }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="split-fixed-{{ $alert->id }}-{{ $i }}" class="hidden grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.vendor_fixed_per_delivery') }}</label>
                                            <input type="number" name="zone_configs[{{ $i }}][vendor_fixed_amount]" min="0" value="0" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.admin_fixed_per_delivery') }}</label>
                                            <input type="number" name="zone_configs[{{ $i }}][admin_fixed_amount]" min="0" value="0" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                                        </div>
                                    </div>
                                </fieldset>
                            @endforeach

                            <div class="mb-4">
                                <label class="text-xs text-gray-500">Note to Vendor (optional)</label>
                                <input type="text" name="admin_note" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm" maxlength="500"
                                       placeholder="{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.e_g_configured_50_50_split') }}">
                            </div>

                            <button type="submit" class="w-full bg-green-600 text-white rounded-lg py-2.5 text-sm font-semibold">
                                {{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.accept_create_all_records') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Reject form --}}
            <div id="reject-{{ $alert->id }}" class="hidden mt-4 border-t pt-4">
                <form method="POST" action="{{ route('admin.shipping-subsidies.alerts.reject', $alert) }}">
                    @csrf
                    <label class="text-xs text-gray-500">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.rejection_reason') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="admin_note" required maxlength="500"
                           class="mt-1 w-full border rounded-lg px-3 py-2 text-sm mb-3"
                           placeholder="{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.e_g_zone_not_eligible_for') }}">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg">
                        {{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.confirm_rejection') }}
                    </button>
                </form>
            </div>

        </div>
    @empty
        <p class="text-gray-400 text-sm py-4">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.no_pending_alerts_from_vendors') }}</p>
    @endforelse

    @if($reviewed->isNotEmpty())
        <div class="mt-10">
            <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('admin.static_text.admin_shipping_subsidies_vendor_alerts.reviewed_alerts') }}</h2>
            <x-card padding="none">
                <table class="table-base w-full">
                    <thead>
                        <tr>
                            <th>{{ __('admin.shipping_subsidies.vendor') }}</th>
                            <th>{{ __('admin.shipping_subsidies.warehouse') }}</th>
                            <th>{{ __('admin.shipping_subsidies.zones_configured') }}</th>
                            <th>{{ __('admin.shipping_subsidies.carrier') }}</th>
                            <th>{{ __('admin.shipping_subsidies.status') }}</th>
                            <th>{{ __('admin.shipping_subsidies.reviewed_by') }}</th>
                            <th>{{ __('admin.shipping_subsidies.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reviewed as $alert)
                            <tr>
                                <td>{{ $alert->vendor->store_name ?? '—' }}</td>
                                <td>{{ $alert->warehouse->name ?? '—' }}</td>
                                <td>
                                    @if($alert->results->isNotEmpty())
                                        {{ $alert->results->pluck('zone.name')->filter()->unique()->join(', ') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-gray-500">{{ $alert->carrier->name ?? 'All carriers' }}</td>
                                <td>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $alert->status === 'accepted' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ ucfirst($alert->status) }}
                                    </span>
                                </td>
                                <td class="text-gray-500 text-xs">{{ $alert->reviewedBy->name ?? '—' }}</td>
                                <td class="text-gray-400 text-xs">{{ $alert->reviewed_at?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
    @endif

@endsection

@push('scripts')
    <script type="module">
        window.toggleBlock = function (id) {
            document.getElementById(id)?.classList.toggle('hidden');
        };
        window.openModal = function (id) {
            document.getElementById(id)?.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        };
        window.closeModal = function (id) {
            document.getElementById(id)?.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };
        window.setSplitType = function (alertId, type) {
            document.getElementById('split-percentage-' + alertId)?.classList.toggle('hidden', type !== 'percentage');
            document.getElementById('split-fixed-' + alertId)?.classList.toggle('hidden', type !== 'fixed');
        };
        window.applyZoneToAll = function (alertId) {
            const value = document.getElementById('bulk-zone-' + alertId)?.value;
            if (!value) return;
            document.querySelectorAll('.zone-select-' + alertId).forEach(function (el) {
                el.value = value;
            });
        };
        window.applyMethodToAll = function (alertId) {
            const value = document.getElementById('bulk-method-' + alertId)?.value;
            if (!value) return;
            document.querySelectorAll('.method-select-' + alertId).forEach(function (el) {
                el.value = value;
            });
        };
    </script>
@endpush
