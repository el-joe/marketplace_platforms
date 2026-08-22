@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js', 'resources/js/components/column-renderers.js'])
@endpush

@section('title', __('admin.delivery_section.agents'))

@section('content')

{{-- ─── Page Header ─────────────────────────────────────────────────────────── --}}
<div class="mb-6 flex items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.delivery_section.agents') }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.delivery_section.agents_desc') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <x-export-dropdown />
        <button type="button" id="add-agent-btn" class="btn btn-primary btn-sm">
            {{ __('admin.delivery_section.add_agent') }}
        </button>
    </div>
</div>

{{-- ─── Stats Row ───────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['label' => __('admin.delivery_section.total_agents_stat'), 'value' => $stats['total'],     'color' => 'gray'],
        ['label' => __('common.active'),      'value' => $stats['active'],    'color' => 'success'],
        ['label' => __('admin.delivery_section.on_shift'),       'value' => $stats['on_shift'],  'color' => 'primary'],
        ['label' => __('admin.delivery_section.suspended'),      'value' => $stats['suspended'], 'color' => 'danger'],
    ] as $stat)
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
            <p class="mt-1 text-2xl font-bold text-{{ $stat['color'] }}-600">{{ number_format($stat['value']) }}</p>
        </div>
    @endforeach
</div>

{{-- ─── Filters ─────────────────────────────────────────────────────────────── --}}
<x-card class="mb-5">
    <form id="filter-form" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.search_label') }}</label>
            <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.delivery_section.search_agent_name_phone') }}">
        </div>
        <div class="w-44">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.country') }}</label>
            <select id="filter-country" class="form-input w-full text-sm">
                <option value="">{{ __('admin.delivery_section.all_countries') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}">{{ $country->name_en }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-44">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.delivery_section.zone_col') }}</label>
            <select id="filter-zone" class="form-input w-full text-sm">
                <option value="">{{ __('admin.delivery_section.all_zones') }}</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-36">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
            <select id="filter-status" class="form-input w-full text-sm">
                <option value="">{{ __('admin.delivery_section.all_statuses') }}</option>
                <option value="active">{{ __('admin.delivery_section.active') }}</option>
                <option value="inactive">{{ __('common.inactive') }}</option>
                <option value="on_shift">{{ __('admin.delivery_section.on_shift') }}</option>
                <option value="suspended">{{ __('admin.delivery_section.suspended') }}</option>
            </select>
        </div>
        <div class="w-36">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.type') }}</label>
            <select id="filter-type" class="form-input w-full text-sm">
                <option value="">{{ __('common.all') }}</option>
                <option value="platform">{{ __('admin.delivery_section.platform_type') }}</option>
                <option value="third_party">{{ __('admin.delivery_section.third_party') }}</option>
            </select>
        </div>
        <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('admin.delivery_section.reset') }}</button>
    </form>
</x-card>

{{-- ─── DataTable ───────────────────────────────────────────────────────────── --}}
<x-card>
    <div class="overflow-x-auto">
        <table id="agents-table" class="w-full text-sm" style="width:100%">
            <thead>
                <tr>
                    <th>{{ __('common.name') }}</th>
                    <th>{{ __('admin.delivery_section.phone_col') }}</th>
                    <th>{{ __('common.country') }}</th>
                    <th>{{ __('admin.delivery_section.zone_col') }}</th>
                    <th>{{ __('common.type') }}</th>
                    <th>{{ __('common.status') }}</th>
                    <th>{{ __('admin.delivery_section.rating_col') }}</th>
                    <th>{{ __('admin.delivery_section.deliveries_col') }}</th>
                    <th>{{ __('admin.delivery_section.available_col') }}</th>
                    <th>{{ __('admin.delivery_section.last_login_col') }}</th>
                    <th>{{ __('admin.delivery_section.actions_col') }}</th>
                </tr>
            </thead>
        </table>
    </div>
</x-card>

{{-- ─── Add / Edit Agent Modal ──────────────────────────────────────────────── --}}
<div id="agent-modal" class="modal-backdrop hidden">
    <div class="modal-box w-full max-w-2xl !p-0 overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 id="agent-modal-title" class="text-lg font-semibold text-gray-900">{{ __('admin.delivery_section.add_delivery_agent') }}</h3>
                <p id="agent-modal-subtitle" class="text-xs text-gray-500 mt-0.5">{{ __('admin.delivery_section.agents_desc') }}</p>
            </div>
            <button type="button" id="agent-modal-close" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <form id="agent-form">
            @csrf
            <input type="hidden" name="_method" id="agent-form-method" value="POST">
            <div class="px-6 py-5 space-y-6 max-h-[70vh] overflow-y-auto">

                {{-- Account --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">{{ __('admin.delivery_section.section_account') }}</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.full_name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="name" class="form-input w-full" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1" data-agent-field="email">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.email_required') }} <span class="text-red-500">*</span></label>
                            <input type="email" name="email" class="form-input w-full" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.phone_required') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="phone" class="form-input w-full" required>
                        </div>
                        <div class="col-span-2 sm:col-span-1" data-agent-field="password">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.password_required') }} <span class="text-red-500">*</span></label>
                            <input type="password" name="password" class="form-input w-full" minlength="8">
                        </div>
                    </div>
                </div>

                {{-- Location --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">{{ __('admin.delivery_section.section_location') }}</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('common.country') }} <span class="text-red-500">*</span></label>
                            <select name="country_id" class="form-input w-full" required>
                                <option value="">{{ __('admin.delivery_section.select_country') }}</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name_en }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.zone_col') }}</label>
                            <select name="zone_id" class="form-input w-full">
                                <option value="">{{ __('admin.delivery_section.no_zone') }}</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Vehicle & ID --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">{{ __('admin.delivery_section.section_vehicle') }}</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.agent_type_required') }} <span class="text-red-500">*</span></label>
                            <select name="agent_type" class="form-input w-full" required>
                                <option value="platform">{{ __('admin.delivery_section.platform_type') }}</option>
                                <option value="third_party">{{ __('admin.delivery_section.third_party') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.vehicle_required') }} <span class="text-red-500">*</span></label>
                            <select name="vehicle_type" class="form-input w-full" required>
                                <option value="motorcycle">{{ __('admin.delivery_section.motorcycle') }}</option>
                                <option value="car">{{ __('admin.delivery_section.car') }}</option>
                                <option value="van">{{ __('admin.delivery_section.van') }}</option>
                                <option value="bicycle">{{ __('admin.delivery_section.bicycle') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.national_id') }}</label>
                            <input type="text" name="national_id" class="form-input w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.vehicle_plate') }}</label>
                            <input type="text" name="vehicle_plate" class="form-input w-full">
                        </div>
                        <div id="shipping-company-field" class="col-span-2 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.delivery_section.shipping_company') }}</label>
                            <select name="shipping_company_id" class="form-input w-full">
                                <option value="">— {{ __('admin.delivery_section.select_company') }} —</option>
                                @foreach($shippingCompanies as $company)
                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">{{ __('admin.delivery_section.shipping_company_note') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                <button type="button" id="agent-modal-cancel" class="btn btn-ghost btn-sm">{{ __('common.cancel') }}</button>
                <button type="submit" id="agent-form-submit" class="btn btn-primary btn-sm">{{ __('admin.delivery_section.create_agent') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script type="module">
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    platform: @json(__('admin.delivery_section.platform_type')),
    thirdPartyShort: @json(__('admin.delivery_section.third_party_short')),
    yes: @json(__('admin.delivery_section.yes')),
    no: @json(__('admin.delivery_section.no')),
    view: @json(__('admin.delivery_section.view')),
    edit: @json(__('admin.delivery_section.edit')),
    quickSearchPlaceholder: @json(__('admin.delivery_section.quick_search_placeholder')),
    createAgentFailed: @json(__('admin.delivery_section.create_agent_failed')),
    updateAgentFailed: @json(__('admin.delivery_section.update_agent_failed')),
    addDeliveryAgent: @json(__('admin.delivery_section.add_delivery_agent')),
    editDeliveryAgent: @json(__('admin.delivery_section.edit_delivery_agent')),
    createAgent: @json(__('admin.delivery_section.create_agent')),
    saveChanges: @json(__('admin.delivery_section.save_changes')),
});

(function () {
    const DATATABLE_URL = @json(route('admin.delivery.agents.datatable'));
    const STORE_URL     = @json(route('admin.delivery.agents.store'));

    // ── Filter zone dropdown by selected country ─────────────────────────────
    const ALL_ZONES = @json($zones);

    function filterZonesByCountry(countryId) {
        const $zoneSelect = $('select[name="zone_id"]', '#agent-modal');
        const current = $zoneSelect.val();
        $zoneSelect.empty().append('<option value="">{{ __("admin.delivery_section.no_zone") }}</option>');

        if (!countryId) return;

        ALL_ZONES
            .filter(z => String(z.country_id) === String(countryId))
            .forEach(z => {
                $zoneSelect.append(`<option value="${z.id}">${z.name}</option>`);
            });

        if (ALL_ZONES.find(z => String(z.id) === String(current) && String(z.country_id) === String(countryId))) {
            $zoneSelect.val(current);
        }
    }

    $(document).on('change', '#agent-modal select[name="country_id"]', function () {
        filterZonesByCountry(this.value);
    });

    // ── Toggle shipping company field for third_party agents ────────────────
    const agentTypeSelect = document.querySelector('#agent-form select[name="agent_type"]');
    const companyField    = document.getElementById('shipping-company-field');

    function toggleCompanyField() {
        const isThirdParty = agentTypeSelect?.value === 'third_party';
        companyField?.classList.toggle('hidden', !isThirdParty);
        const companySelect = companyField?.querySelector('select');
        if (companySelect) companySelect.required = isThirdParty;
    }

    agentTypeSelect?.addEventListener('change', toggleCompanyField);
    toggleCompanyField();

    // ── DataTable ─────────────────────────────────────────────────────────────
    const table = $('#agents-table').DataTable({
        processing : true,
        serverSide : true,
        ajax: {
            url    : DATATABLE_URL,
            type   : 'POST',
            data   : d => Object.assign(d, {
                _token     : $('meta[name=csrf-token]').attr('content'),
                country_id : $('#filter-country').val(),
                zone_id    : $('#filter-zone').val(),
                status     : $('#filter-status').val(),
                agent_type : $('#filter-type').val(),
            }),
        },
        columns: [
            {
                data: null,
                render: r =>
                    `<a href="${r.show_url}" class="font-medium text-primary-600 hover:underline">${r.name}</a>
                     <div class="text-xs text-gray-400">${r.email}</div>`
            },
            { data: 'phone' },
            { data: 'country' },
            { data: 'zone', defaultContent: '—' },
            {
                data: 'agent_type',
                render: v => v === 'platform'
                    ? `<span class="badge badge-primary text-xs">${window.TRANSLATIONS.platform}</span>`
                    : `<span class="badge badge-gray text-xs">${window.TRANSLATIONS.thirdPartyShort}</span>`
            },
            {
                data: 'status',
                render: v => {
                    const colors = { active:'success', inactive:'gray', on_shift:'primary', suspended:'danger' };
                    const c = colors[v] ?? 'gray';
                    return `<span class="badge badge-${c} text-xs capitalize">${v.replace('_',' ')}</span>`;
                }
            },
            { data: 'rating_avg' },
            { data: 'total_deliveries' },
            {
                data: 'is_available',
                render: v => v
                    ? `<span class="inline-block w-2 h-2 rounded-full bg-green-500"></span> ${window.TRANSLATIONS.yes}`
                    : `<span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span> ${window.TRANSLATIONS.no}`
            },
            { data: 'last_login_at' },
            {
                data: null, orderable: false,
                render: r =>
                    `<a href="${r.show_url}" class="btn btn-xs btn-secondary">${window.TRANSLATIONS.view}</a>
                     <button type="button" class="btn btn-xs btn-ghost edit-agent-btn">${window.TRANSLATIONS.edit}</button>`
            },
        ],
        order: [[9, 'desc']],
        pageLength: 25,
        language: { search: '', searchPlaceholder: window.TRANSLATIONS.quickSearchPlaceholder },
        createdRow: (row, data) => $(row).find('.edit-agent-btn').data('agent', data),
    });

    // Filters
    ['#filter-country','#filter-zone','#filter-status','#filter-type'].forEach(sel =>
        $(sel).on('change', () => table.ajax.reload())
    );
    $('#search-input').on('input', function () {
        table.search(this.value).draw();
    });
    $('#clear-filters').on('click', () => {
        $('#filter-country, #filter-zone, #filter-status, #filter-type').val('');
        $('#search-input').val('');
        table.search('').ajax.reload();
    });

    // ── Add / Edit Agent Modal ────────────────────────────────────────────────
    const $modal = $('#agent-modal');
    const $form = $('#agent-form');
    const $title = $('#agent-modal-title');
    const $subtitle = $('#agent-modal-subtitle');
    const $submitBtn = $('#agent-form-submit');
    let submitUrl = STORE_URL;

    function openModal(mode, agent) {
        $form[0].reset();
        $form.find('[data-agent-field="email"], [data-agent-field="password"]').toggleClass('hidden', mode === 'edit');
        $form.find('input[name=email]').prop('required', mode === 'add');
        $form.find('input[name=password]').prop('required', mode === 'add');

        if (mode === 'edit') {
            $title.text(window.TRANSLATIONS.editDeliveryAgent);
            $subtitle.text(agent.name);
            $submitBtn.text(window.TRANSLATIONS.saveChanges);
            $('#agent-form-method').val('PUT');
            submitUrl = agent.update_url;

            $form.find('input[name=name]').val(agent.name);
            $form.find('input[name=phone]').val(agent.phone);
            $form.find('select[name=country_id]').val(agent.country_id);
            filterZonesByCountry(agent.country_id);
            if (agent.zone_id) $form.find('select[name=zone_id]').val(agent.zone_id);
            $form.find('select[name=agent_type]').val(agent.agent_type);
            $form.find('select[name=vehicle_type]').val(agent.vehicle_type);
            $form.find('input[name=national_id]').val(agent.national_id ?? '');
            $form.find('input[name=vehicle_plate]').val(agent.vehicle_plate ?? '');
        } else {
            $title.text(window.TRANSLATIONS.addDeliveryAgent);
            $subtitle.text(@json(__('admin.delivery_section.agents_desc')));
            $submitBtn.text(window.TRANSLATIONS.createAgent);
            $('#agent-form-method').val('POST');
            submitUrl = STORE_URL;
            filterZonesByCountry('');
        }

        $modal.removeClass('hidden');
    }

    function closeModal() {
        $modal.addClass('hidden');
    }

    $('#add-agent-btn').on('click', () => openModal('add'));
    $('#agent-modal-close, #agent-modal-cancel').on('click', closeModal);

    $(document).on('click', '.edit-agent-btn', function () {
        openModal('edit', $(this).data('agent'));
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        const isEdit = $('#agent-form-method').val() === 'PUT';
        $.ajax({
            url    : submitUrl,
            method : 'POST',
            data   : $(this).serialize() + '&_token=' + $('meta[name=csrf-token]').attr('content'),
            success: res => {
                if (res.success) {
                    window.Toast?.success(res.message);
                    if (isEdit) {
                        closeModal();
                        table.ajax.reload(null, false);
                    } else {
                        window.location.href = res.redirect;
                    }
                }
            },
            error: xhr => {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    window.Toast?.error(Object.values(errors).flat().join('\n'));
                } else {
                    window.Toast?.error(isEdit ? window.TRANSLATIONS.updateAgentFailed : window.TRANSLATIONS.createAgentFailed);
                }
            }
        });
    });
})();
</script>
@endpush
