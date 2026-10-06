{{--
    Commission tiers CRUD for $category (edit mode only). Saved immediately via fetch,
    independent of the main category form (nested <form> tags are not valid HTML).
--}}
<div class="mt-4 border-t border-gray-200 pt-4"
    x-data="categoryCommissionTiers({
        indexUrl: '{{ route('admin.categories.commission-tiers.index', $category->id) }}',
        storeUrl: '{{ route('admin.categories.commission-tiers.store', $category->id) }}',
        updateUrlTemplate: '{{ route('admin.categories.commission-tiers.update', [$category->id, '__ID__']) }}',
        destroyUrlTemplate: '{{ route('admin.categories.commission-tiers.destroy', [$category->id, '__ID__']) }}',
        tiers: @js($category->commissionTiers->map(fn ($t) => [
            'id' => $t->id,
            'price_from' => $t->price_from,
            'price_to' => $t->price_to,
            'commission_rate' => $t->commission_rate,
            'min_commission' => $t->min_commission,
        ])->values()),
        confirmText: @js(__('admin.categories.tier_delete_confirm')),
    })">
    <div class="flex items-start justify-between gap-3 mb-3">
        <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-1">{{ __('admin.categories.variable_commission') }}</h4>
            <p class="text-xs text-gray-500">{{ __('admin.categories.variable_commission_hint') }}</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm whitespace-nowrap" @click="startAdd()" x-show="!editingId && !adding">
            {{ __('admin.categories.tier_add') }}
        </button>
    </div>

    <p x-show="error" x-cloak x-text="error" class="mb-3 rounded-lg border border-red-200 bg-red-50 p-2 text-xs text-red-700"></p>

    <p x-show="tiers.length === 0 && !adding" x-cloak class="text-sm text-gray-400 italic">{{ __('admin.categories.tier_none') }}</p>

    <div class="overflow-x-auto rounded-lg border border-gray-200" x-show="tiers.length > 0 || adding" x-cloak>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('admin.categories.tier_price_from') }}</th>
                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('admin.categories.tier_price_to') }}</th>
                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('admin.categories.tier_rate') }}</th>
                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('admin.categories.tier_min') }}</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <template x-for="tier in tiers" :key="tier.id">
                    <tr>
                        <template x-if="editingId !== tier.id">
                            <td class="px-3 py-2 tabular-nums" x-text="tier.price_from"></td>
                        </template>
                        <template x-if="editingId !== tier.id">
                            <td class="px-3 py-2 tabular-nums text-gray-500" x-text="tier.price_to ?? '∞'"></td>
                        </template>
                        <template x-if="editingId !== tier.id">
                            <td class="px-3 py-2 tabular-nums" x-text="tier.commission_rate + '%'"></td>
                        </template>
                        <template x-if="editingId !== tier.id">
                            <td class="px-3 py-2 tabular-nums text-gray-500" x-text="tier.min_commission > 0 ? tier.min_commission : '—'"></td>
                        </template>
                        <template x-if="editingId !== tier.id">
                            <td class="px-3 py-2 text-end whitespace-nowrap">
                                <button type="button" class="text-xs text-blue-600 hover:underline me-3" @click="startEdit(tier)">{{ __('common.edit') }}</button>
                                <button type="button" class="text-xs text-red-500 hover:underline" @click="remove(tier)">{{ __('common.delete') }}</button>
                            </td>
                        </template>
                        <template x-if="editingId === tier.id">
                            <td colspan="5" class="px-3 py-3 bg-blue-50">
                                @include('admin.categories._commission_tier_fields', ['saveAction' => 'save()', 'cancelAction' => 'cancel()'])
                            </td>
                        </template>
                    </tr>
                </template>
                <tr x-show="adding" x-cloak>
                    <td colspan="5" class="px-3 py-3 bg-blue-50">
                        @include('admin.categories._commission_tier_fields', ['saveAction' => 'save()', 'cancelAction' => 'cancel()'])
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function categoryCommissionTiers(config) {
    const blank = () => ({ price_from: 0, price_to: '', commission_rate: 0, min_commission: 0 });

    async function request(url, method, body) {
        const r = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
            throw new Error(first || data.message || 'Request failed');
        }
        return data;
    }

    return {
        tiers: config.tiers,
        adding: false,
        editingId: null,
        saving: false,
        error: '',
        form: blank(),

        startAdd() {
            this.error = '';
            this.form = blank();
            this.editingId = null;
            this.adding = true;
        },
        startEdit(tier) {
            this.error = '';
            this.adding = false;
            this.form = { ...tier, price_to: tier.price_to ?? '' };
            this.editingId = tier.id;
        },
        cancel() {
            this.adding = false;
            this.editingId = null;
            this.error = '';
        },
        async save() {
            this.saving = true;
            this.error = '';
            const payload = { ...this.form, price_to: this.form.price_to === '' ? null : this.form.price_to };
            try {
                const data = this.editingId
                    ? await request(config.updateUrlTemplate.replace('__ID__', this.editingId), 'PUT', payload)
                    : await request(config.storeUrl, 'POST', payload);
                this.tiers = data.tiers;
                this.cancel();
            } catch (e) {
                this.error = e.message;
            } finally {
                this.saving = false;
            }
        },
        async remove(tier) {
            if (!confirm(config.confirmText)) return;
            this.error = '';
            try {
                const data = await request(config.destroyUrlTemplate.replace('__ID__', tier.id), 'DELETE');
                this.tiers = data.tiers;
            } catch (e) {
                this.error = e.message;
            }
        },
    };
}
</script>
