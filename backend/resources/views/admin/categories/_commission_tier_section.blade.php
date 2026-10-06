{{--
    Renders a single scope's tier table inside the commission tiers tab.
    Variables passed by parent:
      $scopeKey     – 'global' or country UUID
      $currencyCode – e.g. 'EGP', 'AED', or '—' for global
--}}
<div class="p-4">

    <p
        x-show="tiersFor('{{ $scopeKey }}').length === 0 && addingKey !== '{{ $scopeKey }}'"
        x-cloak
        class="text-sm text-gray-400 italic"
    >{{ __('admin.categories.tier_none') }}</p>

    <div
        class="overflow-x-auto rounded-lg border border-gray-200"
        x-show="tiersFor('{{ $scopeKey }}').length > 0 || addingKey === '{{ $scopeKey }}'"
        x-cloak
    >
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase">
                        {{ __('admin.categories.tier_price_from') }}
                        @if($currencyCode !== '—')
                            <span class="ms-1 font-normal text-indigo-600">{{ $currencyCode }}</span>
                        @endif
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase">
                        {{ __('admin.categories.tier_price_to') }}
                        @if($currencyCode !== '—')
                            <span class="ms-1 font-normal text-indigo-600">{{ $currencyCode }}</span>
                        @endif
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('admin.categories.tier_rate') }}</th>
                    <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 uppercase">
                        {{ __('admin.categories.tier_min') }}
                        @if($currencyCode !== '—')
                            <span class="ms-1 font-normal text-indigo-600">{{ $currencyCode }}</span>
                        @endif
                    </th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <template x-for="tier in tiersFor('{{ $scopeKey }}')" :key="tier.id">
                    <tr>
                        <template x-if="!(editingKey === '{{ $scopeKey }}' && editingId === tier.id)">
                            <td class="px-3 py-2 tabular-nums" x-text="tier.price_from"></td>
                        </template>
                        <template x-if="!(editingKey === '{{ $scopeKey }}' && editingId === tier.id)">
                            <td class="px-3 py-2 tabular-nums text-gray-500" x-text="tier.price_to != null ? tier.price_to : '∞'"></td>
                        </template>
                        <template x-if="!(editingKey === '{{ $scopeKey }}' && editingId === tier.id)">
                            <td class="px-3 py-2 tabular-nums" x-text="tier.commission_rate + '%'"></td>
                        </template>
                        <template x-if="!(editingKey === '{{ $scopeKey }}' && editingId === tier.id)">
                            <td class="px-3 py-2 tabular-nums text-gray-500" x-text="tier.min_commission > 0 ? tier.min_commission : '—'"></td>
                        </template>
                        <template x-if="!(editingKey === '{{ $scopeKey }}' && editingId === tier.id)">
                            <td class="px-3 py-2 text-end whitespace-nowrap">
                                <button type="button" class="text-xs text-blue-600 hover:underline me-3" @click="startEdit('{{ $scopeKey }}', tier)">{{ __('common.edit') }}</button>
                                <button type="button" class="text-xs text-red-500 hover:underline" @click="remove(tier)">{{ __('common.delete') }}</button>
                            </td>
                        </template>
                        <template x-if="editingKey === '{{ $scopeKey }}' && editingId === tier.id">
                            <td colspan="5" class="px-3 py-3 bg-blue-50">
                                @include('admin.categories._commission_tier_fields_v2', [
                                    'currencyCode' => $currencyCode,
                                    'saveAction'   => 'save()',
                                    'cancelAction' => 'cancel()',
                                ])
                            </td>
                        </template>
                    </tr>
                </template>
                <tr x-show="addingKey === '{{ $scopeKey }}'" x-cloak>
                    <td colspan="5" class="px-3 py-3 bg-blue-50">
                        @include('admin.categories._commission_tier_fields_v2', [
                            'currencyCode' => $currencyCode,
                            'saveAction'   => 'save()',
                            'cancelAction' => 'cancel()',
                        ])
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>
