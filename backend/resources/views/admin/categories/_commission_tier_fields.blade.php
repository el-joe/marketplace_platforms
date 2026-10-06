<div class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end" @keydown.enter.prevent="{{ $saveAction }}">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.tier_price_from') }}</label>
        <input type="number" min="0" step="1" x-model="form.price_from" class="input w-full text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.tier_price_to') }} <span class="text-gray-400">({{ __('admin.categories.tier_no_limit') }})</span></label>
        <input type="number" min="1" step="1" x-model="form.price_to" class="input w-full text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.tier_rate') }}</label>
        <input type="number" min="0" max="100" step="0.01" x-model="form.commission_rate" class="input w-full text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.categories.tier_min') }}</label>
        <input type="number" min="0" step="1" x-model="form.min_commission" class="input w-full text-sm">
    </div>
    <div class="flex gap-2">
        <button type="button" class="btn btn-primary btn-sm flex-1 justify-center" :disabled="saving" @click="{{ $saveAction }}">{{ __('common.save') }}</button>
        <button type="button" class="btn btn-ghost btn-sm flex-1 justify-center" @click="{{ $cancelAction }}">{{ __('common.cancel') }}</button>
    </div>
</div>
