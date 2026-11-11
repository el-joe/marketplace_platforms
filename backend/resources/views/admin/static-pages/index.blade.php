@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@section('title', __('admin.static_pages.title'))

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.static_pages.title') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.static_pages.subtitle') }}</p>
        </div>
        @if(auth('admin')->user()?->can('static_pages.create'))
            <a href="{{ route('admin.static-pages.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('admin.static_pages.new_page') }}
            </a>
        @endif
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-card title="{{ __('admin.static_pages.total') }}" :value="number_format($stats['total'])" icon="document-text" iconBg="bg-gray-100 text-gray-600" />
        <x-stat-card title="{{ __('admin.static_pages.active_pages') }}" :value="number_format($stats['active'])" icon="check-circle" iconBg="bg-emerald-100 text-emerald-600" />
        <x-stat-card title="{{ __('admin.static_pages.footer_links') }}" :value="number_format($stats['in_footer'])" icon="view-columns" iconBg="bg-primary-100 text-primary-600" />
    </div>

    {{-- Filter bar --}}
    <x-card>
        <form id="static-pages-filter-form" class="flex flex-wrap gap-3 items-end" onsubmit="return false">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.static_pages.search_placeholder') }}">
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.static_pages.audience') }}</label>
                <select id="filter-audience" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.static_pages.all_audiences') }}</option>
                    @foreach($audiences as $audience)
                        <option value="{{ $audience }}">{{ __('admin.static_pages.audiences.'.$audience) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.static_pages.footer_group') }}</label>
                <select id="filter-footer-group" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.static_pages.all_footer_groups') }}</option>
                    @foreach($footerGroups as $group)
                        <option value="{{ $group }}">{{ __('admin.static_pages.footer_groups.'.$group) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="filter-active" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.static_pages.all_statuses') }}</option>
                    <option value="1">{{ __('common.active') }}</option>
                    <option value="0">{{ __('common.inactive') }}</option>
                </select>
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('common.reset') }}</button>
        </form>
    </x-card>

    {{-- DataTable --}}
    <x-card>
        <div class="table-responsive">
            <table id="static-pages-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pe-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.static_pages.page_title') }}</th>
                        <th class="py-2 pe-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.static_pages.audience') }}</th>
                        <th class="py-2 pe-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.static_pages.footer_group') }}</th>
                        <th class="py-2 pe-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.static_pages.in_footer') }}</th>
                        <th class="py-2 pe-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.status') }}</th>
                        <th class="py-2 pe-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.static_pages.sort_order') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>
</div>

{{-- Delete confirmation modal --}}
<x-modal id="delete-static-page-modal" title="{{ __('admin.static_pages.delete_page') }}" size="sm">
    <p class="text-sm text-gray-700">{{ __('admin.static_pages.delete_confirm') }}</p>
    <div id="delete-static-page-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-delete-static-page" class="btn btn-danger">{{ __('common.delete') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const I18N = {{ Js::from(['loading' => __('common.loading'), 'empty' => __('admin.static_pages.no_pages'), 'could_not_delete' => __('admin.static_pages.could_not_delete')]) }};
    const DATATABLE_URL = {{ Js::from(route('admin.static-pages.datatable')) }};
    const COLUMNS = [
        { data: 'title', orderable: true },
        { data: 'audience', orderable: true },
        { data: 'footer_group', orderable: true },
        { data: 'show_in_footer', orderable: true },
        { data: 'is_active', orderable: true },
        { data: 'sort_order', orderable: true },
        { data: 'actions', orderable: false, className: 'text-end' },
    ];

    async function req(url, method) {
        const res = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
        return { ok: res.ok, data: await res.json().catch(() => ({})) };
    }

    function getFilters() {
        return {
            search: { value: document.getElementById('search-input').value },
            audience: document.getElementById('filter-audience').value,
            footer_group: document.getElementById('filter-footer-group').value,
            is_active: document.getElementById('filter-active').value,
        };
    }

    let dt = null;
    const reload = () => dt?.ajax?.reload?.() ?? dt?.draw?.();

    function initTable() {
        if (typeof window.initDataTable === 'function') {
            dt = window.initDataTable('static-pages-table', {
                url: DATATABLE_URL,
                columns: COLUMNS,
                order: [[5, 'asc']],
                pageLength: 25,
                extraData: getFilters,
            });
            return;
        }
        dt = window.$('#static-pages-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: DATATABLE_URL,
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF },
                data: function (d) {
                    const f = getFilters();
                    d.audience = f.audience;
                    d.footer_group = f.footer_group;
                    d.is_active = f.is_active;
                    if (f.search.value) d.search = f.search;
                },
            },
            columns: COLUMNS,
            order: [[5, 'asc']],
            pageLength: 25,
            language: { processing: I18N.loading, emptyTable: I18N.empty },
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTable);
    } else {
        initTable();
    }

    ['filter-audience', 'filter-footer-group', 'filter-active'].forEach(id =>
        document.getElementById(id)?.addEventListener('change', reload));
    document.getElementById('search-input')?.addEventListener('input', function () {
        clearTimeout(this._t);
        this._t = setTimeout(reload, 400);
    });
    document.getElementById('clear-filters')?.addEventListener('click', () => {
        ['search-input', 'filter-audience', 'filter-footer-group', 'filter-active'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        reload();
    });

    let deleteUrl = null;
    document.getElementById('static-pages-table').addEventListener('click', async (e) => {
        const toggleBtn = e.target.closest('.btn-toggle');
        const deleteBtn = e.target.closest('.btn-delete');

        if (toggleBtn) {
            const { ok } = await req(toggleBtn.dataset.url, 'POST');
            if (ok) reload();
        }

        if (deleteBtn) {
            deleteUrl = deleteBtn.dataset.url;
            document.getElementById('delete-static-page-error').classList.add('hidden');
            document.getElementById('delete-static-page-modal')?.classList.remove('hidden');
        }
    });

    document.getElementById('btn-confirm-delete-static-page')?.addEventListener('click', async () => {
        if (!deleteUrl) return;
        const { ok, data } = await req(deleteUrl, 'DELETE');
        if (ok) {
            document.getElementById('delete-static-page-modal')?.classList.add('hidden');
            deleteUrl = null;
            reload();
        } else {
            const errEl = document.getElementById('delete-static-page-error');
            errEl.textContent = data.message ?? I18N.could_not_delete;
            errEl.classList.remove('hidden');
        }
    });

    document.querySelectorAll('[data-modal-close]').forEach(btn =>
        btn.addEventListener('click', () => btn.closest('[id]')?.classList.add('hidden')));
})();
</script>
@endpush
@endsection
