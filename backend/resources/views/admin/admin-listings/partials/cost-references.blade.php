{{--
    Cost References tab — admin-only, confidential.
    Guarded entirely by $canViewCost (products.cost_data.view), passed from
    AdminListingController::show(). Never render this partial, or leak
    any of its data, to a vendor- or customer-facing view.
--}}
@if($canViewCost)
    <div class="flex items-start gap-3 bg-red-50 border-b border-red-200 px-5 py-3 rounded-t-xl">
        <x-heroicon name="lock-closed" class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold text-red-800">{{ __('admin.admin_listings.confidential_cost_references') }}</p>
            <p class="text-xs text-red-600 mt-0.5">{{ __('admin.admin_listings.cost_references_confidential_desc') }}</p>
        </div>
    </div>

    <div class="p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('admin.admin_listings.cost_references_title') }}</h3>
            <button type="button" id="btn-add-cost-reference" class="btn btn-primary btn-sm">{{ __('admin.admin_listings.add_cost_reference') }}</button>
        </div>

        <div class="overflow-x-auto border border-gray-200 rounded-xl">
            <table id="cost-references-table" class="table-base w-full text-sm">
                <thead>
                    <tr>
                        <th>{{ __('admin.admin_listings.manufacturer_col') }}</th>
                        <th class="text-end">{{ __('admin.admin_listings.manufacturer_cost_col') }}</th>
                        <th class="text-end">{{ __('admin.admin_listings.shipping_cost') }}</th>
                        <th class="text-end">{{ __('admin.admin_listings.landed_cost_label') }}</th>
                        <th class="text-end">{{ __('admin.admin_listings.margin_pct_col') }}</th>
                        <th class="text-center">{{ __('admin.admin_listings.competitors_col') }}</th>
                        <th>{{ __('admin.admin_listings.competitor_last_checked') }}</th>
                        <th class="text-center w-24">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <x-modal id="cost-reference-modal" title="{{ __('admin.admin_listings.cost_reference_modal_title') }}" size="lg">
        <form id="cost-reference-form" novalidate>
            <input type="hidden" id="cost-reference-id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.manufacturer_name') }}</label>
                    <input type="text" name="manufacturer_name" class="form-input w-full text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.manufacturer_sku') }}</label>
                    <input type="text" name="manufacturer_sku" class="form-input w-full text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.manufacturer_url') }}</label>
                    <input type="url" name="manufacturer_url" class="form-input w-full text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.manufacturer_cost_cents') }}</label>
                    <input type="number" name="manufacturer_cost" min="0" step="1" class="form-input w-full text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.shipping_cost_cents') }}</label>
                    <input type="number" name="shipping_cost" min="0" step="1" class="form-input w-full text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.landed_cost_cents') }}</label>
                    <input type="number" name="landed_cost" min="0" step="1" class="form-input w-full text-sm" placeholder="{{ __('admin.admin_listings.landed_cost_auto_placeholder') }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.admin_listings.platform_margin_pct') }}</label>
                    <input type="number" name="platform_margin_pct" step="0.01" class="form-input w-full text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.notes') }}</label>
                    <textarea name="notes" rows="2" class="form-input w-full text-sm"></textarea>
                </div>

                <div class="sm:col-span-2 border border-gray-200 rounded-lg p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-700">{{ __('admin.admin_listings.competitor_links') }}</label>
                        <button type="button" id="btn-add-competitor-link" class="text-xs text-primary-600 hover:underline">{{ __('admin.admin_listings.add_link') }}</button>
                    </div>
                    <div id="competitor-links-rows" class="space-y-2"></div>
                </div>
            </div>
        </form>

        <x-slot:footer>
            <button type="button" data-modal-close class="btn-secondary">{{ __('common.cancel') }}</button>
            <button type="submit" form="cost-reference-form" class="btn-primary">{{ __('common.save') }}</button>
        </x-slot:footer>
    </x-modal>

    @push('scripts')
        @vite(['resources/js/components/datatable.js', 'resources/js/admin/cost-references.js'])
        <script type="module">
            window.COST_REFERENCE_ROUTES = {
                datatable: @json(route('admin.admin-listings.cost-references.datatable', $listing)),
                store: @json(route('admin.admin-listings.cost-references.store', $listing)),
                update: @json(route('admin.admin-listings.cost-references.update', [$listing, '__ID__'])),
                destroy: @json(route('admin.admin-listings.cost-references.destroy', [$listing, '__ID__'])),
            };
        </script>
    @endpush
@endif
