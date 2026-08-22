{{-- Compact per-block analytics panel, lazily loaded when the "Analytics" tab is opened --}}
<div id="config-analytics-body" data-config-tab-panel="analytics" class="hidden flex-1 overflow-y-auto p-4 space-y-4">
    <div id="analytics-loading" class="text-sm text-gray-400 text-center py-8">{{ __('common.loading') }}</div>

    <div id="analytics-content" class="hidden space-y-4">
        <div class="grid grid-cols-3 gap-2">
            <div class="rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-2 text-center">
                <div class="text-[11px] text-gray-500">{{ __('admin.page_builder.analytics_impressions') }}</div>
                <div id="analytics-stat-impressions" class="text-sm font-semibold text-gray-900 mt-0.5">–</div>
            </div>
            <div class="rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-2 text-center">
                <div class="text-[11px] text-gray-500">{{ __('admin.page_builder.analytics_clicks') }}</div>
                <div id="analytics-stat-clicks" class="text-sm font-semibold text-gray-900 mt-0.5">–</div>
            </div>
            <div class="rounded-lg bg-gray-50 border border-gray-200 px-2.5 py-2 text-center">
                <div class="text-[11px] text-gray-500">{{ __('admin.page_builder.analytics_ctr') }}</div>
                <div id="analytics-stat-ctr" class="text-sm font-semibold text-gray-900 mt-0.5">–</div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-2">
            <canvas id="analytics-sparkline" height="70"></canvas>
        </div>

        <div>
            <div class="text-xs font-medium text-gray-700 mb-1.5">{{ __('admin.page_builder.analytics_top_targets') }}</div>
            <ul id="analytics-top-targets" class="space-y-1 text-xs"></ul>
        </div>

        <div x-data="{ open: false }" class="border-t border-gray-200 pt-3">
            <button type="button" @click="open = !open"
                    class="flex items-center justify-between w-full text-xs font-medium text-gray-700">
                <span>{{ __('admin.page_builder.analytics_more_details') }}</span>
                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="mt-2 space-y-1.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('admin.page_builder.analytics_add_to_cart') }}</span>
                    <span id="analytics-stat-add-to-cart" class="font-medium text-gray-900">–</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('admin.page_builder.analytics_orders') }}</span>
                    <span id="analytics-stat-orders" class="font-medium text-gray-900">–</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">{{ __('admin.page_builder.analytics_revenue') }}</span>
                    <span id="analytics-stat-revenue" class="font-medium text-gray-900">–</span>
                </div>
            </div>
        </div>
    </div>

    <div id="analytics-empty" class="hidden text-sm text-gray-400 text-center py-8">
        {{ __('admin.page_builder.analytics_no_data') }}
    </div>
    <div id="analytics-error" class="hidden text-sm text-rose-600 text-center py-8">
        {{ __('admin.page_builder.analytics_load_error') }}
    </div>
</div>
