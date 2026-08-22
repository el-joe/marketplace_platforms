{{--
    Shipping Methods tab — manage category_shipping_methods for $category.
    Included only in edit mode from admin.categories._form.
--}}
<div
    x-data="categoryShippingMethodsTab({
        indexUrl: '{{ route('admin.categories.shipping-methods.index', $category->id) }}',
        storeUrl: '{{ route('admin.categories.shipping-methods.store', $category->id) }}',
        updateUrlTemplate: '{{ route('admin.categories.shipping-methods.update', [$category->id, '__ID__']) }}',
        destroyUrlTemplate: '{{ route('admin.categories.shipping-methods.destroy', [$category->id, '__ID__']) }}',
        reorderUrl: '{{ route('admin.categories.shipping-methods.reorder', $category->id) }}',
    })"
    x-init="load()"
>
    {{-- No methods warning --}}
    <div x-show="!loading && methods.length === 0" x-cloak
        class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <span>⚠</span>
        <span>{{ __('admin.categories.no_shipping_methods_warning') }}</span>
    </div>

    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">{{ __('admin.categories.shipping_methods_tab') }}</h3>
        <button type="button" @click="openAddModal" class="btn btn-primary btn-sm">
            <x-heroicon name="plus" class="w-4 h-4" />
            {{ __('admin.categories.add_shipping_method') }}
        </button>
    </div>

    <template x-if="loading">
        <div class="text-center text-sm text-gray-400 py-6">{{ __('common.loading') }}</div>
    </template>

    <template x-if="!loading && methods.length > 0">
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="w-8 px-2 py-2"></th>
                        <th class="text-start px-3 py-2">{{ __('admin.categories.method_name') }}</th>
                        <th class="text-start px-3 py-2">{{ __('admin.categories.method_code') }}</th>
                        <th class="text-start px-3 py-2">{{ __('admin.categories.is_default') }}</th>
                        <th class="text-start px-3 py-2">{{ __('admin.categories.available_express_fbn') }}</th>
                        <th class="text-start px-3 py-2">{{ __('admin.categories.available_merchant_fbp') }}</th>
                        <th class="text-end px-3 py-2">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody id="csm-sortable-body" class="divide-y divide-gray-100">
                    <template x-for="m in methods" :key="m.id">
                        <tr :data-id="m.id" class="hover:bg-gray-50">
                            <td class="px-2 py-2 text-gray-300 cursor-move csm-drag-handle">
                                <x-heroicon name="bars-3" class="w-4 h-4" />
                            </td>
                            <td class="px-3 py-2 font-medium text-gray-800" x-text="m.name"></td>
                            <td class="px-3 py-2 text-gray-500" x-text="m.code"></td>
                            <td class="px-3 py-2">
                                <template x-if="m.is_default">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-700">
                                        {{ __('admin.categories.default_badge') }}
                                    </span>
                                </template>
                                <template x-if="!m.is_default">
                                    <button type="button" class="text-xs text-primary-600 hover:underline" @click="setDefault(m)">
                                        {{ __('admin.categories.set_as_default') }}
                                    </button>
                                </template>
                            </td>
                            <td class="px-3 py-2">
                                <input type="checkbox" class="rounded border-gray-300 text-primary-600"
                                    :checked="m.is_available_for_express_fbn"
                                    @change="toggleFlag(m, 'is_available_for_express_fbn', $event.target.checked)">
                            </td>
                            <td class="px-3 py-2">
                                <input type="checkbox" class="rounded border-gray-300 text-primary-600"
                                    :checked="m.is_available_for_merchant_fbp"
                                    @change="toggleFlag(m, 'is_available_for_merchant_fbp', $event.target.checked)">
                            </td>
                            <td class="px-3 py-2 text-end">
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="confirmRemove(m)">
                                    {{ __('admin.remove') }}
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <p x-show="errorMsg" x-cloak class="text-xs text-red-600 font-medium" x-text="errorMsg"></p>

    {{-- Add Shipping Method Modal --}}
    <div x-show="showAddModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @keydown.escape.window="showAddModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5 space-y-4" @click.outside="showAddModal = false">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.categories.add_shipping_method') }}</h3>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.method_name') }}</label>
                <select x-model="form.shipping_method_id" class="input w-full">
                    <option value="">{{ __('common.select') }}</option>
                    <template x-for="a in available" :key="a.id">
                        <option :value="a.id" x-text="a.name + ' (' + a.code + ')'"></option>
                    </template>
                </select>
                <p x-show="available.length === 0" x-cloak class="text-xs text-gray-400 mt-1">
                    {{ __('admin.categories.no_available_methods') }}
                </p>
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300 text-primary-600" x-model="form.is_default">
                    {{ __('admin.categories.is_default') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300 text-primary-600" x-model="form.is_available_for_express_fbn">
                    {{ __('admin.categories.available_express_fbn') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300 text-primary-600" x-model="form.is_available_for_merchant_fbp">
                    {{ __('admin.categories.available_merchant_fbp') }}
                </label>
            </div>

            <p x-show="modalError" x-cloak class="text-xs text-red-600" x-text="modalError"></p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn btn-ghost btn-sm" @click="showAddModal = false">{{ __('common.cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="!form.shipping_method_id || saving" @click="submitAdd">
                    <span x-text="saving ? window.TRANSLATIONS.savingEllipsis : window.TRANSLATIONS.saveChangesBtn"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Remove Confirmation Modal --}}
    <div x-show="showRemoveModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5 space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.categories.remove_shipping_method_confirm_title') }}</h3>
            <p class="text-sm text-gray-600" x-text="removeTarget ? removeTarget.name : ''"></p>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-ghost btn-sm" @click="showRemoveModal = false">{{ __('common.cancel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" @click="doRemove">{{ __('admin.remove') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
function categoryShippingMethodsTab(config) {
    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    function buildUrl(template, id) {
        return template.replace('__ID__', id);
    }

    async function request(url, method, body) {
        const r = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            throw new Error(data.message || window.TRANSLATIONS.saveFailed);
        }
        return data;
    }

    return {
        loading: true,
        saving: false,
        methods: [],
        available: [],
        errorMsg: '',
        showAddModal: false,
        showRemoveModal: false,
        removeTarget: null,
        modalError: '',
        sortable: null,
        form: {
            shipping_method_id: '',
            is_default: false,
            is_available_for_express_fbn: true,
            is_available_for_merchant_fbp: false,
        },

        async load() {
            this.loading = true;
            this.errorMsg = '';
            try {
                const data = await request(config.indexUrl, 'GET');
                this.methods = data.methods;
                this.available = data.available;
                this.$nextTick(() => this.initSortable());
            } catch (e) {
                this.errorMsg = e.message;
            } finally {
                this.loading = false;
            }
        },

        initSortable() {
            const el = document.getElementById('csm-sortable-body');
            if (!el) return;
            if (this.sortable) {
                this.sortable.destroy();
            }
            import('sortablejs').then(({ default: Sortable }) => {
                this.sortable = Sortable.create(el, {
                    handle: '.csm-drag-handle',
                    animation: 150,
                    onEnd: () => this.persistOrder(el),
                });
            });
        },

        async persistOrder(el) {
            const ids = Array.from(el.querySelectorAll('tr[data-id]')).map(tr => tr.dataset.id);
            try {
                await request(config.reorderUrl, 'POST', { ids });
                const byId = Object.fromEntries(this.methods.map(m => [m.id, m]));
                this.methods = ids.map(id => byId[id]);
            } catch (e) {
                this.errorMsg = e.message;
            }
        },

        openAddModal() {
            this.form = {
                shipping_method_id: '',
                is_default: false,
                is_available_for_express_fbn: true,
                is_available_for_merchant_fbp: false,
            };
            this.modalError = '';
            this.showAddModal = true;
        },

        async submitAdd() {
            this.saving = true;
            this.modalError = '';
            try {
                await request(config.storeUrl, 'POST', this.form);
                this.showAddModal = false;
                await this.load();
            } catch (e) {
                this.modalError = e.message;
            } finally {
                this.saving = false;
            }
        },

        async setDefault(m) {
            this.errorMsg = '';
            try {
                await request(buildUrl(config.updateUrlTemplate, m.id), 'PUT', { is_default: true });
                this.methods.forEach(x => { x.is_default = x.id === m.id; });
            } catch (e) {
                this.errorMsg = e.message;
            }
        },

        async toggleFlag(m, key, value) {
            const previous = m[key];
            m[key] = value;
            try {
                await request(buildUrl(config.updateUrlTemplate, m.id), 'PUT', { [key]: value });
            } catch (e) {
                m[key] = previous;
                this.errorMsg = e.message;
            }
        },

        confirmRemove(m) {
            this.removeTarget = m;
            this.showRemoveModal = true;
        },

        async doRemove() {
            if (!this.removeTarget) return;
            try {
                await request(buildUrl(config.destroyUrlTemplate, this.removeTarget.id), 'DELETE');
                this.showRemoveModal = false;
                this.removeTarget = null;
                await this.load();
            } catch (e) {
                this.errorMsg = e.message;
            }
        },
    };
}
</script>
