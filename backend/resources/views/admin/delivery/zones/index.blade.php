@extends('layouts.admin')

@section('title', __('admin.delivery_section.zones'))

@section('content')

    {{-- ─── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.delivery_section.zones') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.delivery_section.zones_desc') }}</p>
        </div>
        <button type="button" id="add-zone-btn" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.add_zone') }}</button>
    </div>

    {{-- ─── Zone Cards ──────────────────────────────────────────────────────────── --}}
    @php
        $byCountry = $zones->groupBy(fn($z) => $z->country?->name_en ?? __('admin.delivery_section.unknown_country'));
    @endphp

    @forelse($byCountry as $countryName => $countryZones)
        <div class="mb-8">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                <span>{{ $countryName }}</span>
                <span class="text-xs font-normal text-gray-400">({{ __('admin.delivery_section.zones_count', ['count' => $countryZones->count()]) }})</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($countryZones as $zone)
                    <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="font-semibold text-gray-800">{{ $zone->name }}</p>
                                <code class="text-xs text-gray-400 font-mono">{{ $zone->code }}</code>
                            </div>
                            <div class="flex items-center gap-1">
                                @if($zone->is_active)
                                    <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                                    <span class="text-xs text-green-600">{{ __('common.active') }}</span>
                                @else
                                    <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                                    <span class="text-xs text-gray-500">{{ __('common.inactive') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="space-y-1.5 text-sm mb-4">
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('admin.delivery_section.agents_count_label') }}</span>
                                <span class="font-medium text-gray-800">{{ $zone->agents_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('admin.delivery_section.cities_label') }}</span>
                                <span class="font-medium text-gray-800">
                                    {{ !empty($zone->city_ids) ? count($zone->city_ids) : 0 }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('admin.delivery_section.delivery_fee_label') }}</span>
                                <span
                                    class="font-medium text-gray-800">{{ number_format($zone->base_delivery_fee, 2) }} {{ $zone->country?->currency_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('admin.delivery_section.cod_fee_label') }}</span>
                                <span class="font-medium text-gray-800">{{ number_format($zone->cod_fee, 2) }} {{ $zone->country?->currency_code }}</span>
                            </div>
                            @if($zone->max_active_agents)
                                @php
                                    $remaining = $zone->max_active_agents - $zone->agents_count;
                                    $pct = $zone->max_active_agents > 0
                                        ? min(100, round($zone->agents_count / $zone->max_active_agents * 100))
                                        : 0;
                                    $barColor = $pct >= 100 ? 'bg-red-500' : ($pct >= 75 ? 'bg-amber-400' : 'bg-emerald-500');
                                @endphp
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ __('admin.delivery_section.max_agents_label') }}</span>
                                    <span class="font-medium {{ $pct >= 100 ? 'text-red-600' : 'text-gray-800' }}">
                                        {{ $zone->agents_count }} / {{ $zone->max_active_agents }}
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-1.5 mt-1">
                                    <div class="{{ $barColor }} h-1.5 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                                </div>
                                @if($pct >= 100)
                                    <p class="text-xs text-red-500 font-medium mt-1">{{ __('admin.delivery_section.zone_at_capacity') }}</p>
                                @endif
                            @endif
                        </div>

                        <div class="flex gap-2">
                            <a href="{{ route('admin.delivery.zones.show', $zone->id) }}"
                               class="btn btn-xs btn-ghost flex-1">
                                {{ __('admin.delivery_section.view') }}
                            </a>
                            <button type="button" class="edit-zone-btn btn btn-xs btn-secondary flex-1" data-zone='@json($zone)'>
                                {{ __('admin.delivery_section.edit') }}
                            </button>
                            <button type="button" class="delete-zone-btn btn btn-xs btn-danger" data-zone-id="{{ $zone->id }}"
                                data-zone-name="{{ $zone->name }}">
                                {{ __('common.delete') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg font-medium">{{ __('admin.delivery_section.no_delivery_zones') }}</p>
            <p class="text-sm mt-1">{{ __('admin.delivery_section.no_zones_hint') }}</p>
        </div>
    @endforelse

    {{-- ─── Add / Edit Zone Modal ───────────────────────────────────────────────── --}}
    <div id="zone-modal" class="modal-backdrop hidden">
        <div class="modal-box w-full max-w-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold" id="zone-modal-title">{{ __('admin.delivery_section.add_zone') }}</h3>
                <button type="button" onclick="document.getElementById('zone-modal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form id="zone-form" class="space-y-4">
                @csrf
                <input type="hidden" id="zone-id">
                <input type="hidden" id="zone-method" value="POST">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.country_required_zone') }} <span
                                class="text-red-500">*</span></label>
                        <select name="country_id" id="zone-country" class="form-input w-full" required>
                            <option value="">{{ __('admin.delivery_section.select_country_option') }}</option>
                            @foreach($countries as $c)
                                <option value="{{ $c->id }}">{{ $c->name_en }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.zone_name_required') }} <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="name" id="zone-name" class="form-input w-full" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.code_required') }} <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="code" id="zone-code" class="form-input w-full font-mono uppercase" required
                            placeholder="{{ __('admin.delivery_section.code_placeholder') }}">
                        <p class="text-xs text-gray-400 mt-1">{{ __('admin.delivery_section.code_hint') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.max_active_agents') }}</label>
                        <input type="number" name="max_active_agents" id="zone-max-agents" class="form-input w-full"
                            min="1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.delivery_fee_required') }} <span
                                class="text-red-500">*</span></label>
                        <input type="number" name="base_delivery_fee" id="zone-delivery-fee" class="form-input w-full"
                            required min="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.cod_fee_required') }} <span
                                class="text-red-500">*</span></label>
                        <input type="number" name="cod_fee" id="zone-cod-fee" class="form-input w-full" required
                            min="0">
                    </div>
                    <div class="col-span-2 flex items-center gap-2">
                        <input type="checkbox" name="is_active" id="zone-active" value="1" class="rounded" checked>
                        <label for="zone-active" class="text-sm font-medium text-gray-700">{{ __('common.active') }}</label>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.delivery_section.cities_label') }}
                        </label>
                        <select name="city_ids[]" id="zone-cities" multiple
                                class="form-input w-full"
                                style="min-height: 120px;">
                            {{-- options injected by JS when country changes --}}
                        </select>
                        <p class="text-xs text-gray-400 mt-1">{{ __('admin.delivery_section.cities_hint') }}</p>
                    </div>
                </div>

                <div class="pt-4 border-t flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('zone-modal').classList.add('hidden')"
                        class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                    <button type="submit" id="zone-submit-btn" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.create_zone') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script type="module">
        window.TRANSLATIONS = window.TRANSLATIONS || {};
        Object.assign(window.TRANSLATIONS, {
            addZone: @json(__('admin.delivery_section.add_zone')),
            editZoneTitle: @json(__('admin.delivery_section.edit_zone_title')),
            createZone: @json(__('admin.delivery_section.create_zone')),
            saveChanges: @json(__('admin.delivery_section.save_changes')),
            saveZoneFailed: @json(__('admin.delivery_section.save_zone_failed')),
            deleteZoneConfirm: @json(__('admin.delivery_section.delete_zone_confirm')),
            deleteZoneFailed: @json(__('admin.delivery_section.delete_zone_failed')),
        });

        window.ALL_CITIES = @json($cities);

        (function () {
            const STORE_URL = @json(route('admin.delivery.zones.store'));
            const BASE_URL = @json(url('delivery/zones'));
            const token = () => $('meta[name=csrf-token]').attr('content');

            // ── City filter by country ───────────────────────────────────────────────
            function populateCities(countryId, selectedIds) {
                selectedIds = selectedIds || [];
                const $select = $('#zone-cities');
                $select.empty();

                const filtered = window.ALL_CITIES.filter(c => c.country_id === countryId);

                if (filtered.length === 0) {
                    $select.append('<option disabled value="">— No cities found for this country —</option>');
                    return;
                }

                filtered.forEach(city => {
                    const selected = selectedIds.includes(city.id) ? 'selected' : '';
                    $select.append(`<option value="${city.id}" ${selected}>${city.name_en}</option>`);
                });
            }

            // Repopulate on country change
            $('#zone-country').on('change', function () {
                populateCities(this.value, []);
            });

            // ── Open Add Modal ────────────────────────────────────────────────────────
            $('#add-zone-btn').on('click', () => {
                resetForm();
                $('#zone-modal-title').text(window.TRANSLATIONS.addZone);
                $('#zone-submit-btn').text(window.TRANSLATIONS.createZone);
                $('#zone-id').val('');
                $('#zone-method').val('POST');
                document.getElementById('zone-modal').classList.remove('hidden');
            });

            // ── Open Edit Modal ───────────────────────────────────────────────────────
            $(document).on('click', '.edit-zone-btn', function () {
                const zone = $(this).data('zone');
                resetForm();
                $('#zone-modal-title').text(window.TRANSLATIONS.editZoneTitle);
                $('#zone-submit-btn').text(window.TRANSLATIONS.saveChanges);
                $('#zone-id').val(zone.id);
                $('#zone-method').val('PUT');
                $('#zone-country').val(zone.country_id);
                $('#zone-name').val(zone.name);
                $('#zone-code').val(zone.code);
                $('#zone-max-agents').val(zone.max_active_agents || '');
                $('#zone-delivery-fee').val(zone.base_delivery_fee);
                $('#zone-cod-fee').val(zone.cod_fee);
                $('#zone-active').prop('checked', !!zone.is_active);
                const cityIds = zone.city_ids || [];
                populateCities(zone.country_id, cityIds);
                document.getElementById('zone-modal').classList.remove('hidden');
            });

            function resetForm() {
                document.getElementById('zone-form').reset();
                $('#zone-active').prop('checked', true);
                $('#zone-cities').empty();
            }

            // ── Submit ────────────────────────────────────────────────────────────────
            $('#zone-form').on('submit', function (e) {
                e.preventDefault();
                const id = $('#zone-id').val();
                const method = id ? 'PUT' : 'POST';
                const url = id ? `${BASE_URL}/${id}` : STORE_URL;

                $.ajax({
                    url,
                    method: 'POST',
                    data: $(this).serialize() + `&_method=${method}&_token=${token()}&is_active=${$('#zone-active').is(':checked') ? 1 : 0}`,
                    success: res => {
                        if (res.success) {
                            window.Toast?.success(res.message);
                            setTimeout(() => location.reload(), 800);
                        }
                    },
                    error: xhr => window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.saveZoneFailed),
                });
            });

            // ── Delete ────────────────────────────────────────────────────────────────
            $(document).on('click', '.delete-zone-btn', function () {
                const id = $(this).data('zone-id');
                const name = $(this).data('zone-name');
                if (!confirm(window.TRANSLATIONS.deleteZoneConfirm.replace(':name', name))) return;
                $.ajax({
                    url: `${BASE_URL}/${id}`,
                    method: 'POST',
                    data: { _method: 'DELETE', _token: token() },
                    success: res => {
                        if (res.success) {
                            window.Toast?.success(res.message);
                            setTimeout(() => location.reload(), 800);
                        }
                    },
                    error: xhr => window.Toast?.error(xhr.responseJSON?.message ?? window.TRANSLATIONS.deleteZoneFailed),
                });
            });

            // ── Auto-uppercase code field ──────────────────────────────────────────────
            $('#zone-code').on('input', function () {
                this.value = this.value.toUpperCase();
            });
        })();
    </script>
@endpush
