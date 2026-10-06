{{--
    Commission Tiers tab for $category (edit mode only).
    Tiers are saved immediately via fetch (no nested form tag).
    Each country gets its own section; a "Global / Default" section handles NULL country_id.
    The global section applies when no country-specific tier matches at checkout.

    Variables:
      $category        – Category model (must be persisted)
      $activeCountries – Collection<Country> with id, name_en, currency_code
--}}

@php
    /** @var \App\Models\Category $category */
    /** @var \Illuminate\Support\Collection $activeCountries */

    // Build grouped tiers: 'global' + one key per country UUID
    $tiersGrouped = $category->commissionTiers()
        ->orderBy('country_id')
        ->orderBy('sort_order')
        ->get()
        ->groupBy(fn ($t) => $t->country_id ?? 'global')
        ->map(fn ($group) => $group->map(fn ($t) => [
            'id'              => $t->id,
            'country_id'      => $t->country_id,
            'price_from'      => $t->price_from,
            'price_to'        => $t->price_to,
            'commission_rate' => (string) $t->commission_rate,
            'min_commission'  => $t->min_commission,
        ])->values()->all())
        ->all();

    // Country lookup keyed by id for easy access
    $countriesById = $activeCountries->keyBy('id');
@endphp

<div
    x-data="categoryCommissionTiersTab({
        indexUrl:          '{{ route('admin.categories.commission-tiers.index', $category->id) }}',
        storeUrl:          '{{ route('admin.categories.commission-tiers.store', $category->id) }}',
        updateUrlTemplate: '{{ route('admin.categories.commission-tiers.update', [$category->id, '__ID__']) }}',
        destroyUrlTemplate:'{{ route('admin.categories.commission-tiers.destroy', [$category->id, '__ID__']) }}',
        tiersGrouped: @js($tiersGrouped),
        confirmText: @js(__('admin.categories.tier_delete_confirm')),
    })"
    class="space-y-6"
>

    {{-- Global banner --}}
    <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-xs text-blue-800">
        {{ __('admin.categories.tier_global_hint') }}
    </div>

    {{-- Global error --}}
    <p x-show="error" x-cloak x-text="error" class="rounded-lg border border-red-200 bg-red-50 p-2 text-xs text-red-700"></p>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- GLOBAL (null country) section                         --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-3 border-b border-gray-200">
            <div>
                <span class="text-sm font-semibold text-gray-700">{{ __('admin.categories.tier_global_label') }}</span>
                <span class="ms-2 text-xs text-gray-400">{{ __('admin.categories.tier_global_sublabel') }}</span>
            </div>
            <button
                type="button"
                class="btn btn-primary btn-sm whitespace-nowrap"
                @click="startAdd('global', null)"
                x-show="addingKey !== 'global' && editingKey !== 'global'"
            >
                {{ __('admin.categories.tier_add') }}
            </button>
        </div>

        @include('admin.categories._commission_tier_section', [
            'scopeKey'     => 'global',
            'currencyCode' => '—',
        ])
    </div>

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- Per-country sections                                   --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    @foreach ($activeCountries as $country)
    <div class="rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-3 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <span class="text-lg leading-none">{{ $country->flag_emoji }}</span>
                <span class="text-sm font-semibold text-gray-700">{{ $country->name_en }}</span>
                <span class="inline-flex items-center rounded-md bg-indigo-50 border border-indigo-200 px-2 py-0.5 text-xs font-medium text-indigo-700">
                    {{ $country->currency_code }}
                </span>
            </div>
            <button
                type="button"
                class="btn btn-primary btn-sm whitespace-nowrap"
                @click="startAdd('{{ $country->id }}', '{{ $country->id }}')"
                x-show="addingKey !== '{{ $country->id }}' && editingKey !== '{{ $country->id }}'"
            >
                {{ __('admin.categories.tier_add') }}
            </button>
        </div>

        @include('admin.categories._commission_tier_section', [
            'scopeKey'     => $country->id,
            'currencyCode' => $country->currency_code,
        ])
    </div>
    @endforeach

</div>

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- Alpine component                                           --}}
{{-- ══════════════════════════════════════════════════════════ --}}
<script>
function categoryCommissionTiersTab(config) {
    const blank = (countryId) => ({
        country_id:      countryId,
        price_from:      0,
        price_to:        '',
        commission_rate: 0,
        min_commission:  0,
    });

    async function request(url, method, body) {
        const r = await fetch(url, {
            method,
            headers: {
                'Content-Type':    'application/json',
                'Accept':          'application/json',
                'X-CSRF-TOKEN':    document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With':'XMLHttpRequest',
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
        // tiersGrouped: { 'global': [...], '<uuid>': [...] }
        tiersGrouped: config.tiersGrouped,
        addingKey:    null,   // scopeKey of section currently showing add form
        editingKey:   null,   // scopeKey of section currently in edit mode
        editingId:    null,   // tier UUID being edited
        saving:       false,
        error:        '',
        form:         blank(null),

        tiersFor(scopeKey) {
            return this.tiersGrouped[scopeKey] ?? [];
        },

        startAdd(scopeKey, countryId) {
            this.error    = '';
            this.form     = blank(countryId);
            this.editingId  = null;
            this.editingKey = null;
            this.addingKey  = scopeKey;
        },

        startEdit(scopeKey, tier) {
            this.error      = '';
            this.addingKey  = null;
            this.editingKey = scopeKey;
            this.editingId  = tier.id;
            this.form = {
                ...tier,
                price_to: tier.price_to ?? '',
            };
        },

        cancel() {
            this.addingKey  = null;
            this.editingKey = null;
            this.editingId  = null;
            this.error      = '';
        },

        async save() {
            this.saving = true;
            this.error  = '';
            const payload = {
                ...this.form,
                price_to: this.form.price_to === '' ? null : this.form.price_to,
            };
            try {
                const data = this.editingId
                    ? await request(config.updateUrlTemplate.replace('__ID__', this.editingId), 'PUT', payload)
                    : await request(config.storeUrl, 'POST', payload);
                // Merge updated grouped tiers from server response
                this.tiersGrouped = data.tiers;
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
                this.tiersGrouped = data.tiers;
            } catch (e) {
                this.error = e.message;
            }
        },
    };
}
</script>
