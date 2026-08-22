<div id="country-override-modal" class="modal fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm p-6">
        <h3 class="font-semibold text-gray-900 mb-4">{{ __('admin.app_contexts.add_country_override') }}</h3>
        <form id="country-override-form" class="space-y-4">
            <div>
                <label class="label">{{ __('admin.app_contexts.country') }}</label>
                <select name="country_id" class="input" data-select2-init required>
                    <option value="">{{ __('admin.app_contexts.select_country') }}</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn btn-primary">{{ __('admin.app_contexts.save') }}</button>
                <button type="button" class="btn btn-secondary js-close-modal" data-modal="country-override-modal">{{ __('admin.app_contexts.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
