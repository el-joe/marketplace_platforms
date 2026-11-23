{{--
    Shipping methods of the category — edited in the page and saved with the whole
    form as shipping_methods[i][...] (order = display priority). Works on create too.
    Expects: $shippingMethods (active methods), $assignedShipping (CategoryShippingMethod rows, edit only)
--}}
@php
    $methodsById = $shippingMethods->keyBy('id');
    $initialRows = $assignedShipping->map(fn ($row) => [
        'shipping_method_id' => $row->shipping_method_id,
        'name' => $methodsById->get($row->shipping_method_id)?->name ?? $row->shippingMethod?->name ?? '—',
        'code' => $methodsById->get($row->shipping_method_id)?->code ?? $row->shippingMethod?->code ?? '',
        'is_default' => (bool) $row->is_default,
        'is_available_for_express_fbn' => (bool) $row->is_available_for_express_fbn,
        'is_available_for_merchant_fbp' => (bool) $row->is_available_for_merchant_fbp,
    ])->values();
@endphp
<div class="space-y-4 p-6"
     x-data="categoryShippingSection({
        methods: @js($shippingMethods->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'code' => $m->code])->values()),
        rows: @js($initialRows),
     })">

    <div class="flex flex-col gap-2 sm:flex-row">
        <select x-model="pick" class="input flex-1" aria-label="{{ __('admin.categories.add_shipping_method') }}">
            <option value="">{{ __('category_form.shipping.choose') }}</option>
            <template x-for="m in available()" :key="m.id">
                <option :value="m.id" x-text="m.name + ' (' + m.code + ')'"></option>
            </template>
        </select>
        <button type="button" class="btn btn-secondary btn-sm justify-center" :disabled="!pick" @click="add()">
            <x-heroicon name="plus" class="h-4 w-4" />
            {{ __('category_form.shipping.add') }}
        </button>
    </div>

    <div x-show="rows.length === 0" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        <x-heroicon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ __('category_form.shipping.none') }}</span>
    </div>

    <div x-show="rows.length > 0" x-cloak class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="w-16 px-2 py-2"></th>
                    <th class="px-3 py-2 text-start">{{ __('admin.categories.method_name') }}</th>
                    <th class="px-3 py-2 text-center">{{ __('admin.categories.is_default') }}</th>
                    <th class="px-3 py-2 text-center">{{ __('admin.categories.available_express_fbn') }}</th>
                    <th class="px-3 py-2 text-center">{{ __('admin.categories.available_merchant_fbp') }}</th>
                    <th class="px-3 py-2 text-end">{{ __('admin.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="(row, i) in rows" :key="row.shipping_method_id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-0.5">
                                <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" :disabled="i === 0" @click="move(i, -1)"
                                    title="{{ __('category_form.shipping.move_up') }}" aria-label="{{ __('category_form.shipping.move_up') }}">
                                    <x-heroicon name="chevron-down" class="h-4 w-4 rotate-180" />
                                </button>
                                <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30" :disabled="i === rows.length - 1" @click="move(i, 1)"
                                    title="{{ __('category_form.shipping.move_down') }}" aria-label="{{ __('category_form.shipping.move_down') }}">
                                    <x-heroicon name="chevron-down" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <span class="font-medium text-gray-800" x-text="row.name"></span>
                            <span class="ms-1 text-xs text-gray-400" x-text="row.code"></span>
                            <input type="hidden" :name="`shipping_methods[${i}][shipping_method_id]`" :value="row.shipping_method_id">
                            <input type="hidden" :name="`shipping_methods[${i}][is_default]`" :value="row.is_default ? 1 : 0">
                            <input type="hidden" :name="`shipping_methods[${i}][is_available_for_express_fbn]`" :value="row.is_available_for_express_fbn ? 1 : 0">
                            <input type="hidden" :name="`shipping_methods[${i}][is_available_for_merchant_fbp]`" :value="row.is_available_for_merchant_fbp ? 1 : 0">
                        </td>
                        <td class="px-3 py-2 text-center">
                            <input type="radio" class="text-primary-600" :checked="row.is_default" @change="setDefault(i)" :aria-label="'{{ __('admin.categories.is_default') }}: ' + row.name">
                        </td>
                        <td class="px-3 py-2 text-center">
                            <input type="checkbox" class="rounded border-gray-300 text-primary-600" x-model="row.is_available_for_express_fbn">
                        </td>
                        <td class="px-3 py-2 text-center">
                            <input type="checkbox" class="rounded border-gray-300 text-primary-600" x-model="row.is_available_for_merchant_fbp">
                        </td>
                        <td class="px-3 py-2 text-end">
                            <button type="button" class="text-xs text-danger-600 hover:underline" @click="remove(i)">{{ __('admin.remove') }}</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-400">{{ __('category_form.shipping.order_hint') }}</p>
</div>

<script>
function categoryShippingSection(config) {
    return {
        methods: config.methods,
        rows: config.rows,
        pick: '',

        available() {
            const used = new Set(this.rows.map(r => r.shipping_method_id));
            return this.methods.filter(m => !used.has(m.id));
        },

        add() {
            const method = this.methods.find(m => m.id === this.pick);
            if (!method) return;
            this.rows.push({
                shipping_method_id: method.id,
                name: method.name,
                code: method.code,
                is_default: this.rows.length === 0,
                is_available_for_express_fbn: true,
                is_available_for_merchant_fbp: false,
            });
            this.pick = '';
            this.$dispatch('category-form:changed');
        },

        setDefault(index) {
            this.rows.forEach((row, i) => { row.is_default = i === index; });
            this.$dispatch('category-form:changed');
        },

        move(index, delta) {
            const target = index + delta;
            if (target < 0 || target >= this.rows.length) return;
            const [row] = this.rows.splice(index, 1);
            this.rows.splice(target, 0, row);
            this.$dispatch('category-form:changed');
        },

        remove(index) {
            const [removed] = this.rows.splice(index, 1);
            if (removed?.is_default && this.rows.length) this.rows[0].is_default = true;
            this.$dispatch('category-form:changed');
        },
    };
}
</script>
