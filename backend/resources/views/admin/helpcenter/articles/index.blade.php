@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@section('title', __('admin.helpcenter.articles'))

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.helpcenter.articles') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.helpcenter.manage_all_content') }}</p>
        </div>
        <a href="{{ route('admin.helpcenter.articles.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.helpcenter.new_article') }}
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <x-stat-card title="{{ __('admin.helpcenter.published_articles') }}" :value="number_format($stats['published'])" icon="check-circle" iconBg="bg-emerald-100 text-emerald-600" />
        <x-stat-card title="{{ __('admin.helpcenter.draft_articles') }}" :value="number_format($stats['draft'])" icon="pencil" iconBg="bg-gray-100 text-gray-600" />
        <x-stat-card title="{{ __('admin.helpcenter.total_views') }}" :value="number_format($stats['total_views'])" icon="eye" iconBg="bg-primary-100 text-primary-600" />
    </div>

    {{-- Filter bar --}}
    <x-card>
        <form id="articles-filter-form" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[160px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.helpcenter.search_placeholder') }}">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.helpcenter.all_statuses') }}</option>
                    <option value="draft">{{ __('common.draft') }}</option>
                    <option value="published">{{ __('common.published') }}</option>
                </select>
            </div>
            <div class="w-56">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.category') }}</label>
                <select id="filter-category" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.helpcenter.all_categories') }}</option>
                    @foreach($categories as $col)
                        <optgroup label="{{ $col->name }}">
                            <option value="{{ $col->id }}">{{ $col->name }}</option>
                            @foreach($col->children as $child)
                                <option value="{{ $child->id }}">↳ {{ $child->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <button type="button" id="clear-filters" class="btn btn-ghost btn-sm self-end">{{ __('common.reset') }}</button>
        </form>
    </x-card>

    {{-- DataTable --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="articles-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.helpcenter.article_title') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.category') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.status') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.published') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.helpcenter.views') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

</div>

{{-- Delete confirmation modal --}}
<x-modal id="delete-article-modal" title="{{ __('admin.helpcenter.delete_article') }}" size="sm">
    <p class="text-sm text-gray-700">{{ __('admin.helpcenter.soft_delete_article_confirm') }}</p>
    <input type="hidden" id="delete-article-id">
    <div id="delete-article-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-delete-article" class="btn btn-danger">{{ __('common.delete') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
window.TRANSLATIONS = {
    loading: "{{ __('common.loading') }}",
    no_articles: "{{ __('admin.helpcenter.no_articles') }}",
    could_not_delete: "{{ __('admin.helpcenter.could_not_delete') }}",
};
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    async function req(url, method) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        return { ok: res.ok, data: await res.json().catch(() => ({})) };
    }

    function getFilters() {
        return {
            search: { value: document.getElementById('search-input').value },
            status: document.getElementById('filter-status').value,
            help_center_category_id: document.getElementById('filter-category').value,
        };
    }

    let dtInstance = null;

    function initTable() {
        if (typeof window.initDataTable !== 'function') {
            dtInstance = window.$('#articles-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("admin.helpcenter.articles.datatable") }}',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                    data: function (d) {
                        const f = getFilters();
                        d.status = f.status;
                        d.help_center_category_id = f.help_center_category_id;
                        if (f.search.value) d.search = f.search;
                    },
                },
                columns: [
                    { data: 'title', orderable: true },
                    { data: 'category', orderable: false },
                    { data: 'status', orderable: true },
                    { data: 'date', orderable: true },
                    { data: 'views', orderable: true },
                    { data: 'actions', orderable: false, className: 'text-end' },
                ],
                order: [[3, 'desc']],
                pageLength: 25,
                language: { processing: 'Loading…', emptyTable: 'No articles found.' },
            });
        } else {
            dtInstance = window.initDataTable('articles-table', {
                url: '{{ route("admin.helpcenter.articles.datatable") }}',
                columns: [
                    { data: 'title', orderable: true },
                    { data: 'category', orderable: false },
                    { data: 'status', orderable: true },
                    { data: 'date', orderable: true },
                    { data: 'views', orderable: true },
                    { data: 'actions', orderable: false },
                ],
                order: [[3, 'desc']],
                pageLength: 25,
                extraData: getFilters,
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTable);
    } else {
        initTable();
    }

    ['filter-status', 'filter-category'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.());
    });
    document.getElementById('search-input')?.addEventListener('input', function () {
        clearTimeout(this._t);
        this._t = setTimeout(() => dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.(), 400);
    });
    document.getElementById('clear-filters')?.addEventListener('click', () => {
        ['filter-status', 'filter-category', 'search-input'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.();
    });

    document.getElementById('articles-table').addEventListener('click', async (e) => {
        const featBtn = e.target.closest('.btn-feature');
        const deleteBtn = e.target.closest('.btn-delete');

        if (featBtn) {
            const { ok } = await req(featBtn.dataset.url, 'POST');
            if (ok) dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.();
        }

        if (deleteBtn) {
            document.getElementById('delete-article-id').value = deleteBtn.dataset.id;
            document.getElementById('delete-article-error').classList.add('hidden');
            document.getElementById('delete-article-modal')?.classList.remove('hidden');
            window._deleteArticleUrl = deleteBtn.dataset.url;
        }
    });

    document.getElementById('btn-confirm-delete-article')?.addEventListener('click', async () => {
        const url = window._deleteArticleUrl;
        if (!url) return;
        const { ok, data } = await req(url, 'DELETE');
        if (ok) {
            document.getElementById('delete-article-modal')?.classList.add('hidden');
            dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.();
        } else {
            const errEl = document.getElementById('delete-article-error');
            errEl.textContent = data.message ?? window.TRANSLATIONS.could_not_delete;
            errEl.classList.remove('hidden');
        }
    });

    document.querySelectorAll('[data-modal-close]').forEach(btn =>
        btn.addEventListener('click', () => btn.closest('[id]')?.classList.add('hidden'))
    );
})();
</script>
@endpush
@endsection
