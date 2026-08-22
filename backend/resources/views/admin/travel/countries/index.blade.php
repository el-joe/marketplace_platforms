@extends('layouts.admin')

@section('title', __('admin.travel.travel_countries'))

@section('content')
<div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900">{{ __('admin.travel.travel_countries') }}</h1>
        <button onclick="openAddModal()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" /> {{ __('admin.travel.add_country') }}
        </button>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('admin.travel.search_name_iso') }}"
               class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-56">
        <select name="continent" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">{{ __('admin.travel.all_continents') }}</option>
            @foreach($continents as $c)
            <option value="{{ $c }}" {{ request('continent') === $c ? 'selected' : '' }}>{{ $c }}</option>
            @endforeach
        </select>
        <select name="active" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="">{{ __('admin.travel.all_statuses') }}</option>
            <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>{{ __('common.active') }}</option>
            <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>{{ __('admin.travel.status.hidden') }}</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm">{{ __('admin.filter') }}</button>
        <a href="{{ route('admin.travel.countries.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('common.reset') }}</a>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700 w-10"></th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('common.country') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.iso') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.travel.continent') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.travel.cities') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.travel.packages') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('common.status') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($countries as $country)
                    <tr class="hover:bg-gray-50" id="country-row-{{ $country->id }}">
                        <td class="px-4 py-3 text-2xl">{{ $country->flag_emoji }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $country->name_en }}</div>
                            <div class="text-gray-400 text-xs" dir="rtl">{{ $country->name_ar }}</div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">
                            {{ $country->iso_code_2 }} / {{ $country->iso_code_3 }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $country->continent ?? '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ number_format($country->cities_count) }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ number_format($country->packages_count) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($country->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('common.active') }}</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">{{ __('admin.hidden') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end whitespace-nowrap">
                            <button onclick="openEditModal({{ json_encode($country) }})"
                                    class="text-primary-600 hover:underline text-xs mr-3">{{ __('common.edit') }}</button>
                            <button onclick="deleteCountry('{{ $country->id }}', '{{ addslashes($country->name_en) }}')"
                                    class="text-red-600 hover:underline text-xs">{{ __('common.delete') }}</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400">{{ __('admin.travel.no_countries_found') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $countries->withQueryString()->links() }}
        </div>
    </div>
</div>

{{-- Add / Edit Modal --}}
<div id="country-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 id="modal-title" class="font-semibold text-gray-900">{{ __('admin.travel.add_country_title') }}</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <x-heroicon name="x-mark" class="w-5 h-5" />
            </button>
        </div>
        <form id="country-form" class="px-6 py-4 space-y-4">
            <input type="hidden" id="country-id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.travel.iso_code_2') }} *</label>
                    <input id="f-iso2" maxlength="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase" placeholder="AE">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.travel.iso_code_3') }} *</label>
                    <input id="f-iso3" maxlength="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase" placeholder="ARE">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.name_en') }} *</label>
                    <input id="f-name-en" dir="ltr" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="United Arab Emirates">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.name_ar') }} *</label>
                    <input id="f-name-ar" dir="rtl" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="الإمارات العربية المتحدة">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.travel.flag_emoji') }}</label>
                    <input id="f-flag" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="🇦🇪">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.travel.continent') }}</label>
                    <select id="f-continent" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">{{ __('admin.travel.select_continent') }}</option>
                        @foreach(['Africa','Americas','Asia','Europe','Oceania'] as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="f-active" class="rounded border-gray-300">
                <label for="f-active" class="text-sm text-gray-700">{{ __('admin.travel.active_visible_to_agencies') }}</label>
            </div>
            <div id="form-error" class="text-red-600 text-sm hidden"></div>
        </form>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100">
            <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">{{ __('common.cancel') }}</button>
            <button onclick="saveCountry()" id="save-btn"
                    class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                {{ __('common.save') }}
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    add_country_title: "{{ __('admin.travel.add_country_title') }}",
    edit_country_title: "{{ __('admin.travel.edit_country_title') }}",
    delete_country_confirm: "{{ __('admin.travel.delete_country_confirm', ['name' => '__NAME__']) }}",
});

const modal  = document.getElementById('country-modal');
const errEl  = document.getElementById('form-error');

function openAddModal() {
    document.getElementById('modal-title').textContent = window.TRANSLATIONS.add_country_title;
    document.getElementById('country-id').value = '';
    ['iso2','iso3','name-en','name-ar','flag'].forEach(k => document.getElementById('f-'+k).value = '');
    document.getElementById('f-continent').value = '';
    document.getElementById('f-active').checked = true;
    errEl.classList.add('hidden');
    modal.classList.remove('hidden');
}

function openEditModal(c) {
    document.getElementById('modal-title').textContent = window.TRANSLATIONS.edit_country_title;
    document.getElementById('country-id').value = c.id;
    document.getElementById('f-iso2').value      = c.iso_code_2;
    document.getElementById('f-iso3').value      = c.iso_code_3;
    document.getElementById('f-name-en').value   = c.name_en;
    document.getElementById('f-name-ar').value   = c.name_ar;
    document.getElementById('f-flag').value      = c.flag_emoji ?? '';
    document.getElementById('f-continent').value = c.continent ?? '';
    document.getElementById('f-active').checked  = !!c.is_active;
    errEl.classList.add('hidden');
    modal.classList.remove('hidden');
}

function closeModal() { modal.classList.add('hidden'); }

async function saveCountry() {
    const id  = document.getElementById('country-id').value;
    const url = id
        ? `/travel/countries/${id}`
        : '/travel/countries';
    const method = id ? 'PUT' : 'POST';

    const body = {
        iso_code_2 : document.getElementById('f-iso2').value.toUpperCase(),
        iso_code_3 : document.getElementById('f-iso3').value.toUpperCase(),
        name_en    : document.getElementById('f-name-en').value,
        name_ar    : document.getElementById('f-name-ar').value,
        flag_emoji : document.getElementById('f-flag').value || null,
        continent  : document.getElementById('f-continent').value || null,
        is_active  : document.getElementById('f-active').checked,
    };

    const btn = document.getElementById('save-btn');
    btn.disabled = true;
    errEl.classList.add('hidden');

    try {
        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!res.ok) {
            const msg = json.errors ? Object.values(json.errors).flat().join(' ') : json.message;
            errEl.textContent = msg;
            errEl.classList.remove('hidden');
            return;
        }
        closeModal();
        window.location.reload();
    } finally {
        btn.disabled = false;
    }
}

async function deleteCountry(id, name) {
    if (!confirm(window.TRANSLATIONS.delete_country_confirm.replace('__NAME__', name))) return;

    const res = await fetch(`/travel/countries/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    const json = await res.json();
    if (!res.ok) { alert(json.message); return; }
    document.getElementById('country-row-' + id)?.remove();
}
</script>
@endpush
