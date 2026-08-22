@component('admin.docs._layout', ['title' => __('docs/features/shipping.title'), 'icon' => '🚛', 'breadcrumb' => __('admin.features')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. Architecture --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.architecture.heading') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium text-gray-700">{{ __('docs/features/shipping.architecture.layer') }}</th>
                            <th class="text-left px-4 py-2 font-medium text-gray-700">{{ __('docs/features/shipping.architecture.purpose') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-gray-800">shipping_zones</td>
                            <td class="px-4 py-2 text-gray-600">{{ __('docs/features/shipping.architecture.zones_desc') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-gray-800">shipping_methods</td>
                            <td class="px-4 py-2 text-gray-600">{{ __('docs/features/shipping.architecture.methods_desc') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-gray-800">shipping_rates</td>
                            <td class="px-4 py-2 text-gray-600">{{ __('docs/features/shipping.architecture.rates_desc') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-gray-800">shipping_weight_slabs</td>
                            <td class="px-4 py-2 text-gray-600">{{ __('docs/features/shipping.architecture.slabs_desc') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-gray-500 text-xs mt-2">shipping_zones &rarr; shipping_methods &rarr; shipping_rates &rarr; shipping_weight_slabs</p>
        </section>

        {{-- 2. Weight Calculation --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.weight_calc.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><code>actual_weight = product.weight_grams &times; quantity</code></li>
                <li><code>volumetric_weight = (L &times; W &times; H cm) &divide; volumetric_divisor &times; 1000</code></li>
                <li><code>billable_weight = MAX(actual, volumetric)</code></li>
            </ul>
            <p class="text-gray-600">{{ __('docs/features/shipping.weight_calc.divisor') }} <strong>5000</strong> {{ __('docs/features/shipping.weight_calc.divisor_note') }}</p>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-2 text-gray-700 text-sm">
                <strong>{{ __('docs/features/shipping.weight_calc.example_label') }}</strong> {{ __('docs/features/shipping.weight_calc.example') }}
            </div>
        </section>

        {{-- 3. Rate Calculation --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.rate_calc.heading') }}</h2>
            <p class="text-gray-600 font-mono text-sm bg-gray-50 border border-gray-200 rounded-lg p-3">
                base_fee + (billable_weight_kg &times; rate_per_kg) + slab_extra_fee
            </p>
        </section>

        {{-- 4. Shipping Zones --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.zones.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/shipping.zones.body') }}</p>
        </section>

        {{-- 5. Exceptional Zones --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.exceptional_zones.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><code>vendor_exceptional_zones</code>: {{ __('docs/features/shipping.exceptional_zones.opt_in') }}</li>
                <li><code>vendor_fbp_subsidy_settings</code>: {{ __('docs/features/shipping.exceptional_zones.split') }}</li>
            </ul>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-2 text-blue-800 text-sm">
                {{ __('docs/features/shipping.exceptional_zones.checkout_note') }}
            </div>
        </section>

        {{-- 6. Free Shipping Threshold --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.free_threshold.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/shipping.free_threshold.settings_key') }} <code>'free_shipping_threshold_{country_code}'</code></p>
            <p class="text-gray-600">{{ __('docs/features/shipping.free_threshold.rule') }}</p>
        </section>

        {{-- 7. Weight Slabs --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.weight_slabs.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/shipping.weight_slabs.intro') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>0&ndash;1kg: +0</li>
                <li>1&ndash;5kg: +10 AED</li>
                <li>5&ndash;20kg: +25 AED</li>
                <li>20kg+: {{ __('docs/features/shipping.weight_slabs.special_handling') }}</li>
            </ul>
        </section>

        {{-- 8. Surcharges --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.surcharges.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/shipping.surcharges.body') }} (<code>warehouse/shipping-surcharges</code>)</p>
        </section>

        {{-- 9. Estimated Delivery Date --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/shipping.delivery_date.heading') }}</h2>
            <p class="text-gray-600 font-mono text-sm bg-gray-50 border border-gray-200 rounded-lg p-3">
                order_confirmed_at + (missed cutoff ? +1 day : 0) + delivery_days range
            </p>
            <p class="text-gray-600">{{ __('docs/features/shipping.delivery_date.exclusions') }}</p>
        </section>

    </div>

@endcomponent
