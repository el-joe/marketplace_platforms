@component('admin.docs._layout', ['title' => __('docs/features/order-lifecycle.title'), 'icon' => '📦', 'breadcrumb' => __('admin.features')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. Order Structure --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.structure.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.structure.intro') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/order-lifecycle.structure.row_master') }}</strong></li>
                <li><strong>{{ __('docs/features/order-lifecycle.structure.row_sub') }}</strong></li>
                <li><strong>{{ __('docs/features/order-lifecycle.structure.row_items') }}</strong></li>
            </ul>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.structure.settlement') }}</p>
        </section>

        {{-- 2. Status Flow --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.status_flow.heading') }}</h2>

            <p class="text-gray-700 font-medium mb-2">{{ __('docs/features/order-lifecycle.status_flow.master_order') }}</p>
            <div class="flex flex-wrap items-center gap-2 mb-6">
                @foreach (['pending', 'confirmed', 'partially_shipped', 'shipped', 'delivered', 'completed'] as $status)
                    <span class="px-3 py-1.5 rounded-full bg-primary-50 text-primary-700 text-xs font-medium border border-primary-200">{{ $status }}</span>
                    @if (!$loop->last)
                        <span class="text-gray-400">&rarr;</span>
                    @endif
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-xs text-gray-400 mr-1">{{ __('docs/features/order-lifecycle.status_flow.branches_to') }}</span>
                @foreach (['cancelled', 'partially_refunded', 'refunded'] as $status)
                    <span class="px-3 py-1.5 rounded-full bg-red-50 text-red-700 text-xs font-medium border border-red-200">{{ $status }}</span>
                    @if (!$loop->last)
                        <span class="text-gray-300">|</span>
                    @endif
                @endforeach
            </div>

            <p class="text-gray-700 font-medium mb-2">{{ __('docs/features/order-lifecycle.status_flow.sub_order') }}</p>
            <div class="flex flex-wrap items-center gap-2 mb-4">
                @foreach (['pending', 'processing', 'ready_for_pickup', 'shipped', 'out_for_delivery', 'delivered', 'completed'] as $status)
                    <span class="px-3 py-1.5 rounded-full bg-green-50 text-green-700 text-xs font-medium border border-green-200">{{ $status }}</span>
                    @if (!$loop->last)
                        <span class="text-gray-400">&rarr;</span>
                    @endif
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs text-gray-400 mr-1">{{ __('docs/features/order-lifecycle.status_flow.branches_to') }}</span>
                @foreach (['cancelled', 'return_requested', 'returned', 'refunded'] as $status)
                    <span class="px-3 py-1.5 rounded-full bg-red-50 text-red-700 text-xs font-medium border border-red-200">{{ $status }}</span>
                    @if (!$loop->last)
                        <span class="text-gray-300">|</span>
                    @endif
                @endforeach
            </div>
        </section>

        {{-- 3. Cart Phase --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.cart_phase.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><code>cart_inventory_locks</code> — {{ __('docs/features/order-lifecycle.cart_phase.lock') }}</li>
                <li><code>X-Cart-Token</code> — {{ __('docs/features/order-lifecycle.cart_phase.guest') }}</li>
                <li>{{ __('docs/features/order-lifecycle.cart_phase.oversell') }}</li>
            </ul>
        </section>

        {{-- 4. Checkout Calculation --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.checkout_calc.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/order-lifecycle.checkout_calc.subtotal') }}</li>
                <li><code>FLOOR(price &times; category_rate / 100)</code> &mdash; {{ __('docs/features/order-lifecycle.checkout_calc.commission') }}</li>
                <li>{{ __('docs/features/order-lifecycle.checkout_calc.shipping_fee') }}</li>
                <li>{{ __('docs/features/order-lifecycle.checkout_calc.subsidy') }}</li>
                <li>{{ __('docs/features/order-lifecycle.checkout_calc.coupon') }}</li>
                <li>{{ __('docs/features/order-lifecycle.checkout_calc.cod_fee') }}</li>
                <li>{{ __('docs/features/order-lifecycle.checkout_calc.tax') }}</li>
                <li>{{ __('docs/features/order-lifecycle.checkout_calc.warranty') }}</li>
                <li><code>ROUND(total &times; gateway_rate)</code> &mdash; {{ __('docs/features/order-lifecycle.checkout_calc.gateway_fee') }}</li>
            </ol>
        </section>

        {{-- 5. Payment --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.payment.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.payment.supported_methods') }} <code>card</code>, <code>wallet</code>, <code>COD</code>, <code>BNPL</code> (Tabby/Tamara), <code>gift_card</code></p>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2 text-amber-800 text-sm">
                <strong>{{ __('docs/features/order-lifecycle.payment.thawani_label') }}</strong> {{ __('docs/features/order-lifecycle.payment.thawani_note') }}
            </div>
        </section>

        {{-- 6. Fulfillment Flows --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.fulfillment.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/order-lifecycle.fulfillment.fbm_label') }}</strong> {{ __('docs/features/order-lifecycle.fulfillment.fbm') }}</li>
                <li><strong>{{ __('docs/features/order-lifecycle.fulfillment.fbn_label') }}</strong> {{ __('docs/features/order-lifecycle.fulfillment.fbn') }}</li>
            </ul>
        </section>

        {{-- 7. Delivery OTP --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.delivery_otp.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.delivery_otp.body') }}</p>
        </section>

        {{-- 8. Auto-Completion --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.auto_completion.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.auto_completion.intro') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>&rarr; <code>{{ __('docs/features/order-lifecycle.auto_completion.status_completed') }}</code></li>
                <li>&rarr; {{ __('docs/features/order-lifecycle.auto_completion.payout_unlocked') }}</li>
                <li>&rarr; {{ __('docs/features/order-lifecycle.auto_completion.conversion_approved') }}</li>
            </ul>
        </section>

        {{-- 9. COD Gate --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.cod_gate.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.cod_gate.body') }}</p>
        </section>

        {{-- 10. Cancellation --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.cancellation.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/order-lifecycle.cancellation.customer_label') }}</strong> {{ __('docs/features/order-lifecycle.cancellation.customer') }}</li>
                <li><strong>{{ __('docs/features/order-lifecycle.cancellation.vendor_label') }}</strong> {{ __('docs/features/order-lifecycle.cancellation.vendor') }}</li>
                <li><strong>{{ __('docs/features/order-lifecycle.cancellation.admin_label') }}</strong> {{ __('docs/features/order-lifecycle.cancellation.admin') }}</li>
            </ul>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.cancellation.on_cancel') }}</p>
        </section>

        {{-- 11. Return Flow --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.return_flow.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.return_flow.flow') }}</p>
            <p class="text-gray-700 font-medium mt-2">{{ __('docs/features/order-lifecycle.return_flow.outcomes_label') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/order-lifecycle.return_flow.good_label') }}</strong> {{ __('docs/features/order-lifecycle.return_flow.good') }}</li>
                <li><strong>{{ __('docs/features/order-lifecycle.return_flow.damaged_customer_label') }}</strong> {{ __('docs/features/order-lifecycle.return_flow.damaged_customer') }}</li>
                <li><strong>{{ __('docs/features/order-lifecycle.return_flow.damaged_transit_label') }}</strong> {{ __('docs/features/order-lifecycle.return_flow.damaged_transit') }}</li>
            </ul>
        </section>

        {{-- 12. Dispute --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.dispute.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.dispute.flow') }}</p>
            <p class="text-gray-700 font-medium mt-2">{{ __('docs/features/order-lifecycle.dispute.resolution_types_label') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><code>full_refund</code></li>
                <li><code>partial_refund</code></li>
                <li><code>no_refund</code></li>
                <li><code>platform_absorbs_loss</code></li>
            </ul>
        </section>

        {{-- 13. Warranty Claim --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/order-lifecycle.warranty_claim.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/order-lifecycle.warranty_claim.standard_label') }}</strong> {{ __('docs/features/order-lifecycle.warranty_claim.standard') }}</li>
                <li><strong>{{ __('docs/features/order-lifecycle.warranty_claim.extended_label') }}</strong> {{ __('docs/features/order-lifecycle.warranty_claim.extended') }}</li>
            </ul>
            <p class="text-gray-600">{{ __('docs/features/order-lifecycle.warranty_claim.vendor_panel') }}</p>
        </section>

    </div>

@endcomponent
