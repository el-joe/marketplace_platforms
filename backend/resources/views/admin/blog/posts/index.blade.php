@extends('layouts.admin')

@push('styles')
    @vite(['resources/js/components/datatable.js'])
@endpush

@section('title', __('admin.blog.posts'))

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.blog.posts') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.blog.post.manage_all_content') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-export-dropdown />
            <a href="{{ route('admin.blog.posts.create') }}"
               class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 sm:self-auto">
                <x-heroicon name="plus" class="w-4 h-4" />
                {{ __('admin.blog.new_post') }}
            </a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <x-stat-card title="{{ __('admin.blog.published_posts') }}"    :value="number_format($stats['published'])" icon="check-circle"   iconBg="bg-emerald-100 text-emerald-600" />
        <x-stat-card title="{{ __('admin.blog.draft_posts') }}"        :value="number_format($stats['draft'])"     icon="pencil"         iconBg="bg-gray-100 text-gray-600" />
        <x-stat-card title="{{ __('admin.blog.scheduled_posts') }}"    :value="number_format($stats['scheduled'])" icon="clock"          iconBg="bg-blue-100 text-blue-600" />
        <x-stat-card title="{{ __('admin.blog.archived_posts') }}"     :value="number_format($stats['archived'])"  icon="archive-box"    iconBg="bg-amber-100 text-amber-600" />
        <x-stat-card title="{{ __('admin.blog.total_views') }}" :value="number_format($stats['views_month'])" icon="eye"          iconBg="bg-primary-100 text-primary-600" />
    </div>

    {{-- Filter bar --}}
    <x-card>
        <form id="posts-filter-form" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 xl:items-end">
            <div class="sm:col-span-2 lg:col-span-4 xl:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.search') }}</label>
                <input type="text" id="search-input" class="form-input w-full text-sm" placeholder="{{ __('admin.blog.search_placeholder') }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.status') }}</label>
                <select id="filter-status" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.blog.all_statuses') }}</option>
                    <option value="draft">{{ __('common.draft') }}</option>
                    <option value="scheduled">{{ __('admin.blog.scheduled_posts') }}</option>
                    <option value="published">{{ __('common.published') }}</option>
                    <option value="archived">{{ __('common.archived') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.category') }}</label>
                <select id="filter-category" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.blog.all_categories') }}</option>
                    @foreach($categories as $cat)
                        <optgroup label="{{ $cat->name_en }}">
                            <option value="{{ $cat->id }}">{{ $cat->name_en }}</option>
                            @foreach($cat->children as $child)
                                <option value="{{ $child->id }}">↳ {{ $child->name_en }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('common.country') }}</label>
                <select id="filter-country" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.blog.all_countries') }}</option>
                    @foreach($countries as $c)
                        <option value="{{ $c->id }}">{{ $c->flag_emoji ? $c->flag_emoji . ' ' : '' }}{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.blog.author') }}</label>
                <select id="filter-author" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.blog.all_authors') }}</option>
                    @foreach($authors as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.blog.published_from') }}</label>
                <input type="date" id="filter-date-from" class="form-input w-full text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.blog.published_to') }}</label>
                <input type="date" id="filter-date-to" class="form-input w-full text-sm">
            </div>
            <div class="flex items-end">
                <button type="button" id="clear-filters" class="btn btn-ghost btn-sm w-full sm:w-auto">{{ __('common.reset') }}</button>
            </div>
        </form>
    </x-card>

    {{-- DataTable --}}
    <x-card>
        <div class="overflow-x-auto">
            <table id="posts-table" class="w-full text-sm" style="width:100%">
                <thead>
                    <tr class="border-b border-gray-100 text-start">
                        <th class="py-2 pr-3 text-xs font-medium text-gray-500 uppercase w-12"></th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.blog.post_title') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.blog.author') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.country') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.status') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('common.published') }}</th>
                        <th class="py-2 pr-4 text-xs font-medium text-gray-500 uppercase">{{ __('admin.blog.views') }}</th>
                        <th class="py-2 text-xs font-medium text-gray-500 uppercase text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>

</div>

{{-- Delete confirmation modal --}}
<x-modal id="delete-post-modal" title="{{ __('admin.blog.delete_post') }}" size="sm">
    <p class="text-sm text-gray-700">{{ __('admin.blog.soft_delete_post_confirm') }}</p>
    <input type="hidden" id="delete-post-id">
    <div id="delete-post-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('common.cancel') }}</button>
        <button type="button" id="btn-confirm-delete-post" class="btn btn-danger">{{ __('common.delete') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
window.TRANSLATIONS = {
    loading: "{{ __('common.loading') }}",
    no_posts: "{{ __('admin.blog.no_posts') }}",
    archive_post_confirm: "{{ __('admin.blog.archive_post_confirm') }}",
    could_not_delete: "{{ __('admin.blog.could_not_delete') }}",
    success: "{{ __('common.success') }}",
    error: "{{ __('common.error') }}",
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

    // ── Init DataTable ────────────────────────────────────────────────────────
    function getFilters() {
        return {
            search:           { value: document.getElementById('search-input').value },
            status:           document.getElementById('filter-status').value,
            blog_category_id: document.getElementById('filter-category').value,
            country_id:       document.getElementById('filter-country').value,
            author_admin_id:  document.getElementById('filter-author').value,
            date_from:        document.getElementById('filter-date-from').value,
            date_to:          document.getElementById('filter-date-to').value,
        };
    }

    let dtInstance = null;

    function initTable() {
        if (typeof window.initDataTable !== 'function') {
            // fallback — raw jQuery DataTables
            dtInstance = window.$('#posts-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("admin.blog.posts.datatable") }}',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                    data: function (d) {
                        const f = getFilters();
                        d.status           = f.status;
                        d.blog_category_id = f.blog_category_id;
                        d.country_id       = f.country_id;
                        d.author_admin_id  = f.author_admin_id;
                        d.date_from        = f.date_from;
                        d.date_to          = f.date_to;
                        if (f.search.value) d.search = f.search;
                    },
                },
                columns: [
                    { data: 'image',   orderable: false, responsivePriority: 4 },
                    { data: 'title',   orderable: true,  responsivePriority: 1 },
                    { data: 'author',  orderable: false, responsivePriority: 6 },
                    { data: 'country', orderable: false, responsivePriority: 7 },
                    { data: 'status',  orderable: true,  responsivePriority: 3 },
                    { data: 'date',    orderable: true,  responsivePriority: 5 },
                    { data: 'views',   orderable: true,  responsivePriority: 8 },
                    { data: 'actions', orderable: false, className: 'text-end', responsivePriority: 2 },
                ],
                order: [[5, 'desc']],
                pageLength: 25,
                responsive: true,
                language: { processing: window.TRANSLATIONS.loading, emptyTable: window.TRANSLATIONS.no_posts },
            });
        } else {
            dtInstance = window.initDataTable('posts-table', {
                url: '{{ route("admin.blog.posts.datatable") }}',
                columns: [
                    { data: 'image',   orderable: false, responsivePriority: 4 },
                    { data: 'title',   orderable: true,  responsivePriority: 1 },
                    { data: 'author',  orderable: false, responsivePriority: 6 },
                    { data: 'country', orderable: false, responsivePriority: 7 },
                    { data: 'status',  orderable: true,  responsivePriority: 3 },
                    { data: 'date',    orderable: true,  responsivePriority: 5 },
                    { data: 'views',   orderable: true,  responsivePriority: 8 },
                    { data: 'actions', orderable: false, responsivePriority: 2 },
                ],
                order: [[5, 'desc']],
                pageLength: 25,
                extraData: getFilters,
            });
        }
    }

    // Wait for DT to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTable);
    } else {
        initTable();
    }

    // ── Filters ───────────────────────────────────────────────────────────────
    ['filter-status','filter-category','filter-country','filter-author','filter-date-from','filter-date-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.());
    });
    document.getElementById('search-input')?.addEventListener('input', function () {
        clearTimeout(this._t);
        this._t = setTimeout(() => dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.(), 400);
    });
    document.getElementById('clear-filters')?.addEventListener('click', () => {
        ['filter-status','filter-category','filter-country','filter-author'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        ['filter-date-from','filter-date-to','search-input'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.();
    });

    // ── Table action delegation ───────────────────────────────────────────────
    document.getElementById('posts-table').addEventListener('click', async (e) => {
        const featBtn    = e.target.closest('.btn-feature');
        const archiveBtn = e.target.closest('.btn-archive');
        const deleteBtn  = e.target.closest('.btn-delete');

        if (featBtn) {
            const { ok } = await req(featBtn.dataset.url, 'POST');
            if (ok) dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.();
        }

        if (archiveBtn) {
            if (!confirm(window.TRANSLATIONS.archive_post_confirm)) return;
            const { ok } = await req(archiveBtn.dataset.url, 'POST');
            if (ok) dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.();
        }

        if (deleteBtn) {
            document.getElementById('delete-post-id').value = deleteBtn.dataset.id;
            document.getElementById('delete-post-error').classList.add('hidden');
            document.getElementById('delete-post-modal')?.classList.remove('hidden');
            window._deletPostUrl = deleteBtn.dataset.url;
        }
    });

    document.getElementById('btn-confirm-delete-post')?.addEventListener('click', async () => {
        const url = window._deletPostUrl;
        if (!url) return;
        const { ok, data } = await req(url, 'DELETE');
        if (ok) {
            document.getElementById('delete-post-modal')?.classList.add('hidden');
            dtInstance?.ajax?.reload?.() ?? dtInstance?.draw?.();
        } else {
            const errEl = document.getElementById('delete-post-error');
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
