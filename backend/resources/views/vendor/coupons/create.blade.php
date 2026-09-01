@extends('layouts.partner')

@php
    $isEdit = isset($coupon) && $coupon !== null;
    $couponProductIds = $isEdit ? collect($coupon['product_ids'] ?? [])->implode(',') : '';
@endphp

@section('title', $isEdit ? __('partner.coupons.create.title_edit') : __('partner.coupons.create.title_new'))
@section('page-title', $isEdit ? __('partner.coupons.create.title_edit') : __('partner.coupons.create.title_new'))

@push('scripts')
    @vite('resources/js/vendor/coupons.js')
@endpush

@section('content')
    <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-3xl"
         x-data="{
            scope: '{{ $isEdit ? $coupon['scope'] : 'vendor' }}',
            type: '{{ $isEdit ? $coupon['type'] : 'percentage' }}',
            selectedProducts: [
                @if($isEdit && !empty($coupon['product_ids']))
                    @foreach($coupon['product_ids'] as $pid) {{ Js::from($pid) }}, @endforeach
                @endif
            ],
            productQuery: '',
            productResults: [],
            async searchProducts() {
                if (this.productQuery.length < 2) { this.productResults = []; return; }
                const res = await fetch('{{ route('partner.coupons.product-search') }}?q=' + encodeURIComponent(this.productQuery));
                this.productResults = await res.json();
            },
            toggleProduct(id) {
                const idx = this.selectedProducts.indexOf(id);
                if (idx === -1) { this.selectedProducts.push(id); } else { this.selectedProducts.splice(idx, 1); }
            },
         }">

        <div class="mb-6">
            <a href="{{ route('partner.coupons.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{!! __('partner.coupons.create.back_to_coupons') !!}</a>
        </div>

        <div id="coupon-form-error" class="hidden mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3"></div>

        <form id="coupon-form" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5"
              data-url="{{ $isEdit ? route('partner.coupons.update', $coupon['id']) : route('partner.coupons.store') }}"
              data-method="{{ $isEdit ? 'PUT' : 'POST' }}"
              data-success-message="{{ $isEdit ? __('partner.coupons.create.success_updated') : __('partner.coupons.create.success_created') }}">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.code') }}</label>
                    <input type="text" name="code" required maxlength="50"
                           value="{{ $isEdit ? $coupon['code'] : '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.name') }}</label>
                    <input type="text" name="name" required maxlength="150"
                           value="{{ $isEdit ? $coupon['name'] : '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.description') }}</label>
                <textarea name="description" rows="2" maxlength="2000"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ $isEdit ? $coupon['description'] : '' }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.type') }}</label>
                    <select name="type" x-model="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="percentage">{{ __('partner.coupons.create.type_percentage') }}</option>
                        <option value="fixed_amount">{{ __('partner.coupons.create.type_fixed_amount') }}</option>
                        <option value="free_shipping">{{ __('partner.coupons.create.type_free_shipping') }}</option>
                        <option value="bogo">{{ __('partner.coupons.create.type_bogo') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __('partner.coupons.create.value') }}
                        <span x-show="type === 'fixed_amount'" class="text-gray-400 font-normal text-xs ml-1">({{ $vendor_currency }})</span>
                    </label>
                    <input type="number" step="any" min="0" name="value" required
                           x-bind:placeholder="type === 'bogo' ? '0' : ''"
                           value="{{ $isEdit ? $coupon['value'] : '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <p x-show="type === 'bogo'" class="text-xs text-gray-400 mt-1">
                        {{ __('partner.coupons.create.bogo_hint') }}
                    </p>
                    <p x-show="type === 'free_shipping'" class="text-xs text-gray-400 mt-1">
                        {{ __('partner.coupons.create.free_shipping_hint') }}
                    </p>
                </div>
            </div>

            {{-- Currency is always the vendor's country currency, not user input --}}
            <input type="hidden" name="currency" value="{{ $vendor_currency }}">

            {{-- Scope --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.scope') }}</label>
                <div class="flex gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="scope" value="vendor" x-model="scope">
                        {{ __('partner.coupons.create.scope_vendor') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="scope" value="product" x-model="scope">
                        {{ __('partner.coupons.create.scope_product') }}
                    </label>
                </div>
            </div>

            {{-- Product multi-select, only visible when scope=product --}}
            <div x-show="scope === 'product'" x-cloak class="border border-gray-200 rounded-lg p-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('partner.coupons.create.products') }}</label>

                <template x-for="id in selectedProducts" :key="id">
                    <input type="hidden" name="product_ids[]" :value="id">
                </template>

                <input type="text" x-model="productQuery" @input.debounce.300ms="searchProducts()"
                       placeholder="{{ __('partner.coupons.create.search_products_placeholder') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2">

                <div class="max-h-48 overflow-y-auto divide-y divide-gray-100" x-show="productResults.length > 0">
                    <template x-for="product in productResults" :key="product.id">
                        <label class="flex items-center gap-2 py-2 text-sm cursor-pointer">
                            <input type="checkbox" :checked="selectedProducts.includes(product.id)"
                                   @change="toggleProduct(product.id)">
                            <span x-text="product.name_en ?? product.name"></span>
                        </label>
                    </template>
                </div>

                <p class="text-xs text-gray-400 mt-2" x-show="selectedProducts.length > 0">
                    <span x-text="selectedProducts.length"></span> {{ __('partner.coupons.create.products_selected') }}
                    {{ __('partner.coupons.create.products_eligible_note') }}
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.min_order_amount') }}</label>
                    <input type="number" step="any" min="0" name="min_order_amount"
                           value="{{ $isEdit ? $coupon['min_order_amount'] : '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.max_discount') }}</label>
                    <input type="number" step="any" min="0" name="max_discount"
                           value="{{ $isEdit ? $coupon['max_discount'] : '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.total_usage_limit') }}</label>
                    <input type="number" min="1" name="usage_limit_total"
                           value="{{ $isEdit ? $coupon['usage_limit_total'] : '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.usage_limit_per_customer') }}</label>
                    <input type="number" min="1" name="usage_limit_per_customer" required
                           value="{{ $isEdit ? $coupon['usage_limit_per_customer'] : 1 }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.valid_from') }}</label>
                    <input type="datetime-local" name="valid_from" required
                           value="{{ $isEdit ? \Illuminate\Support\Str::of($coupon['valid_from'])->before('+') : '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('partner.coupons.create.valid_until') }}</label>
                    <input type="datetime-local" name="valid_until" required
                           value="{{ $isEdit ? \Illuminate\Support\Str::of($coupon['valid_until'])->before('+') : '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" {{ !$isEdit || $coupon['is_active'] ? 'checked' : '' }}>
                    {{ __('partner.coupons.create.active') }}
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_stackable" value="1" {{ $isEdit && $coupon['is_stackable'] ? 'checked' : '' }}>
                    {{ __('partner.coupons.create.stackable') }}
                </label>
            </div>

            <div class="pt-2 flex justify-end gap-3">
                <a href="{{ route('partner.coupons.index') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('partner.coupons.create.cancel') }}</a>
                <button type="submit"
                        class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
                    {{ $isEdit ? __('partner.coupons.create.save_changes') : __('partner.coupons.create.create_coupon') }}
                </button>
            </div>
        </form>

        {{-- Analytics section (only shown when editing an existing coupon) --}}
        @if($isEdit)
        <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-6" x-data="couponAnalytics('{{ $coupon['id'] }}', '{{ route('partner.coupons.analytics', $coupon['id']) }}')">

            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-gray-800">{{ __('partner.coupons.analytics.title') }}</h2>
                <button type="button" @click="load()" x-show="!loaded && !loading"
                    class="text-sm text-primary-600 hover:underline">
                    {{ __('partner.coupons.analytics.load') }}
                </button>
                <span x-show="loading" class="text-sm text-gray-400">{{ __('partner.coupons.analytics.loading') }}</span>
            </div>

            {{-- Summary cards --}}
            <div x-show="loaded" class="grid grid-cols-3 gap-4 mb-6">
                <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900" x-text="data.total_uses ?? 0"></p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.coupons.analytics.total_uses') }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900" x-text="data.unique_customers ?? 0"></p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.coupons.analytics.unique_customers') }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900" x-text="(data.total_discount ?? 0).toLocaleString()"></p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('partner.coupons.analytics.total_discount_given') }}</p>
                </div>
            </div>

            {{-- Daily usage chart --}}
            <div x-show="loaded" class="mb-6">
                <p class="text-xs font-medium text-gray-500 mb-2">{{ __('partner.coupons.analytics.last_30_days') }}</p>
                <canvas id="coupon-usage-chart" height="80"></canvas>
            </div>

            {{-- Recent usages --}}
            <div x-show="loaded && data.recent_usages && data.recent_usages.length > 0">
                <p class="text-xs font-medium text-gray-500 mb-2">{{ __('partner.coupons.analytics.recent_redemptions') }}</p>
                <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 overflow-hidden">
                    <template x-for="usage in (data.recent_usages ?? [])" :key="usage.order_number">
                        <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                            <span class="font-mono text-gray-600" x-text="usage.order_number"></span>
                            <span class="text-gray-500 text-xs" x-text="new Date(usage.used_at).toLocaleDateString()"></span>
                            <span class="font-medium text-gray-900" x-text="usage.discount_amount.toLocaleString()"></span>
                        </div>
                    </template>
                </div>
            </div>

            <p x-show="loaded && (!data.total_uses || data.total_uses === 0)"
               class="text-sm text-gray-400 text-center py-4">
                {{ __('partner.coupons.analytics.no_data') }}
            </p>
        </div>

        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        function couponAnalytics(couponId, url) {
            return {
                loaded: false,
                loading: false,
                data: {},
                chart: null,
                async load() {
                    this.loading = true;
                    try {
                        const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } });
                        this.data  = await res.json();
                        this.loaded = true;
                        this.$nextTick(() => this.renderChart());
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.loading = false;
                    }
                },
                renderChart() {
                    const canvas = document.getElementById('coupon-usage-chart');
                    if (!canvas || !this.data.daily_usage) return;
                    if (this.chart) this.chart.destroy();
                    this.chart = new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: this.data.daily_usage.map(d => d.date.slice(5)),
                            datasets: [{
                                label: '{{ __('partner.coupons.analytics.uses') }}',
                                data: this.data.daily_usage.map(d => d.uses),
                                backgroundColor: '#FBBF24',
                                borderRadius: 3,
                            }],
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                                x: { grid: { display: false } },
                            },
                        },
                    });
                },
            };
        }
        </script>
        @endpush
        @endif
    </div>
@endsection
