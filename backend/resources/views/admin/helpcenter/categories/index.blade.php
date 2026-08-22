@extends('layouts.admin')

@section('title', __('admin.helpcenter.categories'))

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" defer></script>
@endpush

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.helpcenter.categories') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.helpcenter.category_tree_desc') }}</p>
        </div>
        <button type="button" id="btn-new-category"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.helpcenter.new_category') }}
        </button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <x-stat-card title="{{ __('admin.helpcenter.total_categories') }}" :value="$stats['total']" icon="rectangle-stack" iconBg="bg-primary-100 text-primary-600" />
        <x-stat-card title="{{ __('common.active') }}" :value="$stats['active']" icon="check-circle" iconBg="bg-emerald-100 text-emerald-600" />
        <x-stat-card
            title="{{ __('admin.helpcenter.most_articles') }}"
            :value="$stats['top_category'] ? $stats['top_category']->name . ' (' . $stats['top_category']->articles_count . ')' : '—'"
            icon="information-circle"
            iconBg="bg-amber-100 text-amber-600" />
    </div>

    {{-- Tree table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-8 px-3 py-3"></th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.helpcenter.data_table.name') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.helpcenter.data_table.slug') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700">{{ __('admin.helpcenter.data_table.parent') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.helpcenter.data_table.articles') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.helpcenter.data_table.active') }}</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700">{{ __('admin.helpcenter.data_table.actions') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody id="category-tree" class="divide-y divide-gray-100">
                    @forelse($roots as $root)
                        <tr class="hover:bg-gray-50 bg-white category-row" data-id="{{ $root->id }}">
                            <td class="px-3 py-3 text-gray-300 cursor-grab drag-handle">
                                <x-heroicon name="bars-3" class="w-4 h-4" />
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-900">{{ $root->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $root->slug }}</td>
                            <td class="px-4 py-3 text-xs text-gray-400">{{ __('admin.helpcenter.data_table.top_level') }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ $root->articles_count }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" dir="ltr"
                                        class="btn-toggle-active relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $root->is_active ? 'bg-emerald-500' : 'bg-gray-200' }}"
                                        data-id="{{ $root->id }}" data-active="{{ $root->is_active ? '1' : '0' }}"
                                        aria-label="{{ __('common.toggle_active') }}">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform {{ $root->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button type="button" class="btn-edit-category text-xs text-primary-600 font-medium hover:underline"
                                        data-category="{{ json_encode($root->only(['id','name','name_en','name_ar','slug','parent_id','country_id','icon','description','description_en','description_ar','sort_order','is_active'])) }}">
                                    {{ __('common.edit') }}
                                </button>
                                <button type="button" class="btn-delete-category ms-2 text-xs text-red-500 hover:underline"
                                        data-id="{{ $root->id }}" data-name="{{ $root->name }}">
                                    {{ __('common.delete') }}
                                </button>
                            </td>
                        </tr>
                        @foreach($root->children->sortBy('sort_order') as $child)
                        <tr class="hover:bg-gray-50 bg-gray-50/40 category-row" data-id="{{ $child->id }}">
                            <td class="px-3 py-3 text-gray-200"></td>
                            <td class="px-4 py-3 ps-10">
                                <span class="text-gray-400 me-1">↳</span>
                                <span class="font-medium text-gray-800">{{ $child->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $child->slug }}</td>
                            <td class="px-4 py-3 text-xs text-gray-400">{{ $root->name }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ $child->articles->count() }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" dir="ltr"
                                        class="btn-toggle-active relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none {{ $child->is_active ? 'bg-emerald-500' : 'bg-gray-200' }}"
                                        data-id="{{ $child->id }}" data-active="{{ $child->is_active ? '1' : '0' }}"
                                        aria-label="{{ __('common.toggle_active') }}">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform {{ $child->is_active ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <button type="button" class="btn-edit-category text-xs text-primary-600 font-medium hover:underline"
                                        data-category="{{ json_encode($child->only(['id','name','name_en','name_ar','slug','parent_id','country_id','icon','description','description_en','description_ar','sort_order','is_active'])) }}">
                                    {{ __('common.edit') }}
                                </button>
                                <button type="button" class="btn-delete-category ms-2 text-xs text-red-500 hover:underline"
                                        data-id="{{ $child->id }}" data-name="{{ $child->name }}">
                                    {{ __('common.delete') }}
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">{{ __('admin.helpcenter.no_categories_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Collection Modal --}}
<x-modal id="category-modal" title="{{ __('admin.helpcenter.category_label') }}" size="lg">
    <form id="category-form" class="space-y-4">
        @csrf
        <input type="hidden" id="form-category-id" value="">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.name') }} ({{ __('common.english') }}) <span class="text-red-500">*</span></label>
                <input type="text" name="name_en" id="f-name-en" required maxlength="150"
                       class="form-input w-full text-sm" placeholder="{{ __('admin.helpcenter.modal.name_en_placeholder') }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.name') }} ({{ __('common.arabic') }}) <span class="text-red-500">*</span></label>
                <input type="text" name="name_ar" id="f-name-ar" required maxlength="150" dir="rtl"
                       class="form-input w-full text-sm" placeholder="{{ __('admin.helpcenter.modal.name_ar_placeholder') }}">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.slug') }}</label>
                <input type="text" name="slug" id="f-slug" maxlength="150"
                       class="form-input w-full text-sm font-mono" placeholder="{{ __('admin.helpcenter.modal.auto_generated') }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.parent_category') }}</label>
                <select name="parent_id" id="f-parent-id" class="form-input w-full text-sm">
                    <option value="">{{ __('admin.helpcenter.data_table.top_level') }}</option>
                    @foreach($roots as $root)
                        <option value="{{ $root->id }}">{{ $root->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.country') }}</label>
            <select name="country_id" id="f-country-id" class="form-input w-full text-sm">
                <option value="">{{ __('admin.helpcenter.modal.all_countries') }}</option>
                @foreach($countries as $c)
                    <option value="{{ $c->site_code }}">{{ $c->flag_emoji }} {{ $c->name_en }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.icon') }}</label>
            <input type="text" name="icon" id="f-icon" maxlength="255"
                   class="form-input w-full text-sm" placeholder="https://.../icon.svg">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.description') }} ({{ __('common.english') }})</label>
                <textarea name="description_en" id="f-description-en" rows="3"
                          class="form-input w-full text-sm" placeholder="{{ __('admin.helpcenter.modal.category_description_placeholder') }}"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.description') }} ({{ __('common.arabic') }})</label>
                <textarea name="description_ar" id="f-description-ar" rows="3" dir="rtl"
                          class="form-input w-full text-sm" placeholder="{{ __('admin.helpcenter.modal.category_description_placeholder') }}"></textarea>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('admin.helpcenter.modal.sort_order') }}</label>
                <input type="number" name="sort_order" id="f-sort-order" value="0" min="0"
                       class="form-input w-28 text-sm">
            </div>
            <label class="flex items-center gap-2 cursor-pointer select-none mt-4">
                <input type="checkbox" name="is_active" id="f-is-active" value="1" class="rounded text-primary-600" checked>
                <span class="text-sm text-gray-700">{{ __('admin.helpcenter.modal.active') }}</span>
            </label>
        </div>

        <div id="form-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    </form>

    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('admin.helpcenter.modal.cancel') }}</button>
        <button type="button" id="btn-save-category" class="btn btn-primary">{{ __('admin.helpcenter.modal.save') }}</button>
    </x-slot:footer>
</x-modal>

{{-- Delete confirmation modal --}}
<x-modal id="delete-modal" title="{{ __('admin.helpcenter.delete_modal.delete_category') }}" size="sm">
    <p class="text-sm text-gray-700">{!! __('admin.helpcenter.delete_modal.message', ['name' => '<strong id="delete-cat-name"></strong>']) !!}</p>
    <input type="hidden" id="delete-cat-id">
    <div id="delete-error" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
    <x-slot:footer>
        <button type="button" data-modal-close class="btn btn-secondary">{{ __('admin.helpcenter.delete_modal.cancel') }}</button>
        <button type="button" id="btn-confirm-delete" class="btn btn-danger">{{ __('admin.helpcenter.delete_modal.delete') }}</button>
    </x-slot:footer>
</x-modal>

@push('scripts')
<script>
window.TRANSLATIONS = window.TRANSLATIONS || {};
Object.assign(window.TRANSLATIONS, {
    new_category: @json(__('admin.helpcenter.new_category')),
    edit_category: @json(__('admin.helpcenter.edit_category')),
    could_not_delete: @json(__('admin.helpcenter.could_not_delete')),
});
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const base = '{{ rtrim(route("admin.helpcenter.categories.index"), "/") }}';

    function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

    document.querySelectorAll('[data-modal-close]').forEach(btn =>
        btn.addEventListener('click', () => btn.closest('[id]')?.classList.add('hidden'))
    );

    async function req(url, method, body) {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, data };
    }

    // ── Slug auto-generate ────────────────────────────────────────────────────
    const nameInput = document.getElementById('f-name-en');
    const slugInput = document.getElementById('f-slug');
    let slugManual = false;
    slugInput.addEventListener('input', () => { slugManual = slugInput.value !== ''; });
    nameInput.addEventListener('input', () => {
        if (!slugManual) {
            slugInput.value = nameInput.value
                .toLowerCase().trim()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });

    // ── New Collection ────────────────────────────────────────────────────────
    document.getElementById('btn-new-category').addEventListener('click', () => {
        resetForm();
        document.querySelector('#category-modal [id$="-title"]').textContent = window.TRANSLATIONS.new_category;
        openModal('category-modal');
    });

    function resetForm() {
        document.getElementById('form-category-id').value = '';
        ['f-name-en', 'f-name-ar', 'f-slug', 'f-icon', 'f-description-en', 'f-description-ar'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('f-parent-id').value = '';
        document.getElementById('f-sort-order').value = '0';
        document.getElementById('f-is-active').checked = true;
        document.getElementById('form-error').classList.add('hidden');
        slugManual = false;
    }

    // ── Edit Collection ───────────────────────────────────────────────────────
    document.querySelectorAll('.btn-edit-category').forEach(btn => {
        btn.addEventListener('click', () => {
            const cat = JSON.parse(btn.dataset.category);
            document.getElementById('form-category-id').value = cat.id;
            document.getElementById('f-name-en').value = cat.name_en ?? cat.name ?? '';
            document.getElementById('f-name-ar').value = cat.name_ar ?? cat.name ?? '';
            document.getElementById('f-slug').value = cat.slug ?? '';
            document.getElementById('f-icon').value = cat.icon ?? '';
            document.getElementById('f-description-en').value = cat.description_en ?? cat.description ?? '';
            document.getElementById('f-description-ar').value = cat.description_ar ?? cat.description ?? '';
            document.getElementById('f-parent-id').value = cat.parent_id ?? '';
            document.getElementById('f-country-id').value = cat.country_id ?? '';
            document.getElementById('f-sort-order').value = cat.sort_order ?? 0;
            document.getElementById('f-is-active').checked = !!cat.is_active;
            slugManual = true;
            document.querySelector('#category-modal [id$="-title"]').textContent = window.TRANSLATIONS.edit_category;
            openModal('category-modal');
        });
    });

    // ── Save Collection ───────────────────────────────────────────────────────
    document.getElementById('btn-save-category').addEventListener('click', async () => {
        const id = document.getElementById('form-category-id').value;
        const payload = {
            name_en: document.getElementById('f-name-en').value,
            name_ar: document.getElementById('f-name-ar').value,
            slug: document.getElementById('f-slug').value,
            parent_id: document.getElementById('f-parent-id').value || null,
            country_id: document.getElementById('f-country-id').value || null,
            icon: document.getElementById('f-icon').value || null,
            description_en: document.getElementById('f-description-en').value || null,
            description_ar: document.getElementById('f-description-ar').value || null,
            sort_order: parseInt(document.getElementById('f-sort-order').value) || 0,
            is_active: document.getElementById('f-is-active').checked ? 1 : 0,
        };

        const url = id ? `${base}/${id}` : base;
        const method = id ? 'PUT' : 'POST';
        const errEl = document.getElementById('form-error');

        const { ok, data } = await req(url, method, payload);
        if (ok) {
            closeModal('category-modal');
            window.location.reload();
        } else {
            errEl.textContent = data.message ?? Object.values(data.errors ?? {}).flat().join(' ');
            errEl.classList.remove('hidden');
        }
    });

    // ── Delete Collection ─────────────────────────────────────────────────────
    document.querySelectorAll('.btn-delete-category').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete-cat-name').textContent = btn.dataset.name;
            document.getElementById('delete-cat-id').value = btn.dataset.id;
            document.getElementById('delete-error').classList.add('hidden');
            openModal('delete-modal');
        });
    });

    document.getElementById('btn-confirm-delete').addEventListener('click', async () => {
        const id = document.getElementById('delete-cat-id').value;
        const { ok, data } = await req(`${base}/${id}`, 'DELETE');
        if (ok) {
            closeModal('delete-modal');
            window.location.reload();
        } else {
            const errEl = document.getElementById('delete-error');
            errEl.textContent = data.message ?? window.TRANSLATIONS.could_not_delete;
            errEl.classList.remove('hidden');
        }
    });

    // ── Toggle Active ─────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-toggle-active').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const { ok, data } = await req(`${base}/${id}/toggle`, 'POST');
            if (ok) {
                const active = data.is_active;
                btn.classList.toggle('bg-emerald-500', active);
                btn.classList.toggle('bg-gray-200', !active);
                const dot = btn.querySelector('span');
                dot.classList.toggle('translate-x-4', active);
                dot.classList.toggle('translate-x-0.5', !active);
            }
        });
    });

    // ── SortableJS drag reorder ───────────────────────────────────────────────
    const tbody = document.getElementById('category-tree');
    if (tbody && window.Sortable) {
        Sortable.create(tbody, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: async () => {
                const ids = Array.from(document.querySelectorAll('.category-row')).map(r => r.dataset.id);
                await req('{{ route("admin.helpcenter.categories.reorder") }}', 'POST', { ids });
            },
        });
    }
})();
</script>
@endpush
@endsection
