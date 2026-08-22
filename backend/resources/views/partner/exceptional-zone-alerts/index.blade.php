@extends('layouts.partner')

@section('title', __('partner.exceptional_zone_alerts.page_title'))

@section('content')

    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('partner.exceptional_zone_alerts.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ __('partner.exceptional_zone_alerts.subtitle') }}
            </p>
        </div>
        <button type="button" onclick="openAlertModal()"
                class="shrink-0 px-4 py-2 bg-orange-500 text-white text-sm rounded-lg hover:bg-orange-600 font-medium">
            {{ __('partner.exceptional_zone_alerts.alert_admin') }}
        </button>
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

    @if($myAlerts->isNotEmpty())
        <div class="mt-4">
            <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('partner.exceptional_zone_alerts.alert_history') }}</h2>
            <x-card padding="none">
                <table class="table-base w-full">
                    <thead>
                        <tr>
                            <th>{{ __('partner.exceptional_zone_alerts.table_warehouse') }}</th>
                            <th>{{ __('partner.exceptional_zone_alerts.table_cities') }}</th>
                            <th>{{ __('partner.exceptional_zone_alerts.table_carrier') }}</th>
                            <th>{{ __('partner.exceptional_zone_alerts.table_reported_fee') }}</th>
                            <th>{{ __('partner.exceptional_zone_alerts.table_status') }}</th>
                            <th>{{ __('partner.exceptional_zone_alerts.table_admin_note') }}</th>
                            <th>{{ __('partner.exceptional_zone_alerts.table_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($myAlerts as $alert)
                            <tr>
                                <td>{{ $alert->warehouse->name ?? '—' }}</td>
                                <td class="text-gray-500 text-xs">{{ count($alert->city_ids ?? []) }} {{ Str::plural('city', count($alert->city_ids ?? [])) }}</td>
                                <td class="text-gray-500">{{ $alert->carrier->name ?? __('partner.exceptional_zone_alerts.all_carriers') }}</td>
                                <td>{{ $alert->reported_carrier_fee }} {{ $alert->currency }}</td>
                                <td>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $alert->status === 'pending'  ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $alert->status === 'accepted' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $alert->status === 'rejected' ? 'bg-gray-100 text-gray-500' : '' }}">
                                        {{ ucfirst($alert->status) }}
                                    </span>
                                    @if($alert->isPending())
                                        <form method="POST" action="{{ route('partner.exceptional-zone-alerts.cancel', $alert) }}" class="inline">
                                            @csrf
                                            <button type="submit" onclick="return confirm(@js(__('partner.exceptional_zone_alerts.cancel_confirm')))"
                                                    class="ml-1 text-xs text-red-500 hover:underline">
                                                {{ __('partner.exceptional_zone_alerts.cancel') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td class="text-gray-500 text-xs">{{ $alert->admin_note ?? '—' }}</td>
                                <td class="text-gray-400 text-xs">{{ $alert->created_at->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $myAlerts->links() }}</div>
            </x-card>
        </div>
    @else
        <div class="text-center py-12 text-gray-400">
            <p class="text-lg mb-1">{{ __('partner.exceptional_zone_alerts.no_alerts_title') }}</p>
            <p class="text-sm">{{ __('partner.exceptional_zone_alerts.no_alerts_hint') }}</p>
        </div>
    @endif

    <div id="alertModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-start justify-between mb-4">
                <h3 class="font-bold text-lg">{{ __('partner.exceptional_zone_alerts.modal_title') }}</h3>
                <button type="button" onclick="closeAlertModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>

            <form method="POST" action="{{ route('partner.exceptional-zone-alerts.store') }}">
                @csrf

                <div class="mb-4">
                    <label class="text-sm font-medium text-gray-700">
                        {{ __('partner.exceptional_zone_alerts.warehouse_label') }} <span class="text-red-500">*</span>
                    </label>
                    <select name="warehouse_id" id="alertWarehouseId" required
                            class="mt-1 w-full border rounded-lg px-3 py-2 text-sm"
                            onchange="loadCitiesForWarehouse(this.value)">
                        <option value="">{{ __('partner.exceptional_zone_alerts.select_warehouse_placeholder') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="text-sm font-medium text-gray-700">
                        {{ __('partner.exceptional_zone_alerts.cities_label') }} <span class="text-red-500">*</span>
                    </label>
                    <div id="alertCitiesList" class="mt-1 max-h-48 overflow-y-auto border rounded-lg p-2 text-sm text-gray-400">
                        {{ __('partner.exceptional_zone_alerts.select_warehouse_first') }}
                    </div>
                </div>

                <div class="mb-4">
                    <label class="text-sm font-medium text-gray-700">
                        {{ __('partner.exceptional_zone_alerts.carrier_label') }} <span class="text-gray-400 font-normal">{{ __('partner.exceptional_zone_alerts.carrier_optional_hint') }}</span>
                    </label>
                    <select name="carrier_id" id="alertCarrierId" class="mt-1 w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">{{ __('partner.exceptional_zone_alerts.all_carriers') }}</option>
                        @foreach($carriers as $carrier)
                            <option value="{{ $carrier->id }}">{{ $carrier->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="text-sm font-medium text-gray-700">
                        {{ __('partner.exceptional_zone_alerts.carrier_fee_label') }} <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-2 mt-1">
                        <input type="number" name="reported_carrier_fee" required min="1"
                               class="flex-1 border rounded-lg px-3 py-2 text-sm" placeholder="{{ __('partner.exceptional_zone_alerts.carrier_fee_placeholder') }}">
                        <input type="hidden" name="currency" id="alertCurrency" value="">
                        <div id="alertCurrencyDisplay"
                             class="w-28 border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-500 text-center select-none">
                            —
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        {{ __('partner.exceptional_zone_alerts.carrier_fee_hint') }}
                    </p>
                </div>

                <div class="mb-5">
                    <label class="text-sm font-medium text-gray-700">
                        {{ __('partner.exceptional_zone_alerts.note_to_admin_label') }} <span class="text-gray-400 font-normal">{{ __('partner.exceptional_zone_alerts.optional') }}</span>
                    </label>
                    <textarea name="vendor_note" rows="3" maxlength="1000"
                              class="mt-1 w-full border rounded-lg px-3 py-2 text-sm resize-none"
                              placeholder="{{ __('partner.exceptional_zone_alerts.note_placeholder') }}"></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeAlertModal()"
                            class="flex-1 border rounded-lg py-2 text-sm text-gray-700 hover:bg-gray-50">
                        {{ __('partner.exceptional_zone_alerts.cancel') }}
                    </button>
                    <button type="submit"
                            class="flex-1 bg-orange-500 text-white rounded-lg py-2 text-sm font-medium hover:bg-orange-600">
                        {{ __('partner.exceptional_zone_alerts.submit_alert') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script type="module">
        const citiesUrl = @json(route('partner.exceptional-zone-alerts.cities-for-warehouse'));
        const i18n = {
            selectWarehouseFirst: @json(__('partner.exceptional_zone_alerts.select_warehouse_first')),
            loadingCities: @json(__('partner.exceptional_zone_alerts.loading_cities')),
            noActiveCities: @json(__('partner.exceptional_zone_alerts.no_active_cities')),
            noZoneAssigned: @json(__('partner.exceptional_zone_alerts.no_zone_assigned')),
        };

        window.openAlertModal = function () {
            const modal = document.getElementById('alertModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        window.closeAlertModal = function () {
            const modal = document.getElementById('alertModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('alertCurrency').value = '';
            document.getElementById('alertCurrencyDisplay').textContent = '—';
            document.getElementById('alertCitiesList').innerHTML = i18n.selectWarehouseFirst;
            document.getElementById('alertWarehouseId').value = '';
        };

        let citiesRequestId = 0;

        window.loadCitiesForWarehouse = async function (warehouseId) {
            const list = document.getElementById('alertCitiesList');
            const currencyHidden = document.getElementById('alertCurrency');
            const currencyDisplay = document.getElementById('alertCurrencyDisplay');
            const requestId = ++citiesRequestId;

            if (!warehouseId) {
                list.innerHTML = i18n.selectWarehouseFirst;
                currencyHidden.value = '';
                currencyDisplay.textContent = '—';
                return;
            }

            list.textContent = i18n.loadingCities;

            const res = await fetch(citiesUrl + '?warehouse_id=' + encodeURIComponent(warehouseId), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();

            // Discard the response if a newer warehouse selection has been made since this request started.
            if (requestId !== citiesRequestId) {
                return;
            }

            if (data.currency) {
                currencyHidden.value = data.currency;
                currencyDisplay.textContent = data.currency;
            }

            if (!data.cities || data.cities.length === 0) {
                list.innerHTML = '<p class="text-gray-400">' + i18n.noActiveCities + '</p>';
                return;
            }

            list.innerHTML = data.cities.map(function (city) {
                const label = city.name_en + (city.has_zone ? '' : ' ' + i18n.noZoneAssigned);
                return '<label class="flex items-center gap-2 py-1 text-sm">'
                    + '<input type="checkbox" name="city_ids[]" value="' + city.id + '">'
                    + '<span>' + label + '</span>'
                    + '</label>';
            }).join('');
        };
    </script>
@endpush
