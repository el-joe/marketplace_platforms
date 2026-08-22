{{-- Review modal: shown when admin clicks "Review" on a submission row --}}
<x-modal id="review-modal" title="{{ __('admin.flash_sales.review_submission') }}" size="xl">
    <form id="review-form">
        <input type="hidden" name="submission_id">
        @csrf

        <div class="flex flex-col sm:flex-row gap-6">

            {{-- ── Left: product + vendor info ───────────────────────────── --}}
            <div class="w-full sm:w-64 flex-shrink-0 space-y-4">

                <div class="text-center">
                    <img id="review-product-img" src="" alt="" class="w-48 h-48 object-cover rounded-lg mx-auto mb-2 bg-gray-100">
                    <p id="review-product-name" class="font-semibold text-gray-900 text-sm"></p>
                    <p id="review-variant-name" class="text-xs text-gray-500"></p>
                </div>

                <div class="bg-gray-50 rounded-lg p-3 text-sm space-y-1.5">
                    <p class="font-semibold text-xs text-gray-500 uppercase">{{ __('admin.flash_sales.vendor') }}</p>
                    <p id="review-vendor-name" class="font-medium text-gray-900"></p>
                    <p id="review-vendor-country" class="text-gray-500 text-xs"></p>
                    <div class="flex items-center gap-1 text-xs">
                        <span class="text-warning-500">★</span>
                        <span id="review-vendor-rating">—</span>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-3 text-sm">
                    <p class="font-semibold text-xs text-gray-500 uppercase mb-2">{{ __('admin.flash_sales.stock_levels') }}</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-gray-400 border-b">
                                    <th class="pb-1 text-start">{{ __('admin.flash_sales.warehouse_short') }}</th>
                                    <th class="pb-1 text-end">{{ __('admin.flash_sales.on_hand') }}</th>
                                    <th class="pb-1 text-end">{{ __('admin.flash_sales.avail') }}</th>
                                </tr>
                            </thead>
                            <tbody id="review-stock-tbody">
                                <tr><td colspan="3" class="text-center text-gray-400 py-2">{{ __('admin.flash_sales.loading') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            {{-- ── Right: price analysis + decision ───────────────────────── --}}
            <div class="flex-1 min-w-0 space-y-4">

                {{-- Price comparison --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-500 mb-1">{{ __('admin.flash_sales.original_price') }}</p>
                        <p id="review-original-price" class="text-xl font-bold text-gray-900"></p>
                    </div>
                    <div class="bg-primary-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-primary-600 mb-1">{{ __('admin.flash_sales.flash_price') }}</p>
                        <p id="review-flash-price" class="text-xl font-bold text-primary-700"></p>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">{{ __('admin.flash_sales.discount_label') }}</p>
                        <p id="review-discount-pct" class="text-3xl font-bold"></p>
                    </div>
                    <div class="text-end">
                        <p class="text-xs text-gray-500 mb-1">{{ __('admin.flash_sales.discount_check_min', ['pct' => $sale->min_discount_pct]) }}</p>
                        <span id="review-discount-check"></span>
                    </div>
                </div>

                <div id="review-30d-row" class="hidden text-sm text-gray-600 bg-gray-50 rounded p-2">
                    {!! __('admin.flash_sales.30d_avg_price', ['price' => '<strong id="review-30d-price"></strong>', 'diff' => '<strong id="review-30d-diff"></strong>']) !!}
                </div>

                {{-- Price history chart --}}
                <div>
                    <p class="text-xs text-gray-500 mb-1">{{ __('admin.flash_sales.30d_price_history') }}</p>
                    <div style="height:150px"><canvas id="price-history-chart"></canvas></div>
                </div>

                {{-- Fake discount warnings --}}
                <div id="fake-discount-warnings" class="hidden space-y-1"></div>

                {{-- Decision form --}}
                <div x-data="{ decision: '' }" class="space-y-3 border-t pt-3">
                    <x-form-radio-group name="decision" label="{{ __('admin.flash_sales.decision') }}"
                        :options="['approved' => __('admin.flash_sales.approve'), 'rejected' => __('admin.flash_sales.reject')]"
                        x-model="decision" />

                    <div x-show="decision === 'rejected'" id="rejection-fields" class="space-y-3">
                        <x-form-select name="rejection_code" label="{{ __('admin.flash_sales.rejection_code') }}" required
                            :options="[
                                'discount_too_low'        => __('admin.flash_sales.rejection_discount_too_low'),
                                'fake_discount_suspected' => __('admin.flash_sales.rejection_fake_discount_suspected'),
                                'insufficient_stock'      => __('admin.flash_sales.rejection_insufficient_stock'),
                                'not_eligible_category'   => __('admin.flash_sales.rejection_not_eligible_category'),
                                'slot_limit_reached'      => __('admin.flash_sales.rejection_slot_limit_reached'),
                                'policy_violation'        => __('admin.flash_sales.rejection_policy_violation'),
                                'vendor_not_eligible'     => __('admin.flash_sales.rejection_vendor_not_eligible'),
                            ]" />
                        <x-form-textarea name="rejection_reason" label="{{ __('admin.flash_sales.message_to_vendor') }}" rows="2" />
                    </div>

                    <x-form-textarea name="admin_notes" label="{{ __('admin.flash_sales.internal_admin_notes') }}" rows="2" />

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeModal('review-modal')" class="btn btn-ghost">{{ __('common.cancel') }}</button>
                        <button type="submit" id="review-submit-btn" class="btn btn-primary">{{ __('admin.flash_sales.save_decision') }}</button>
                    </div>
                </div>

            </div>
        </div>
    </form>
</x-modal>
