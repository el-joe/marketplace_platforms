@component('admin.docs._layout', ['title' => __('docs/features/payments.title'), 'icon' => '💳', 'breadcrumb' => __('admin.features')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. Payment Methods --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/payments.methods.heading') }}</h2>
            <p class="text-gray-600"><code>card</code>, <code>wallet</code>, <code>cod</code>, <code>bnpl</code> (Tabby/Tamara), <code>gift_card</code></p>
            <p class="text-gray-600">{{ __('docs/features/payments.methods.availability') }}</p>
        </section>

        {{-- 2. Payment Gateways --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/payments.gateways.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/payments.gateways.configured') }}</li>
                <li>{{ __('docs/features/payments.gateways.test') }}</li>
                <li>{{ __('docs/features/payments.gateways.webhooks') }}</li>
            </ul>
        </section>

        {{-- 3. Customer Wallet --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/payments.wallet.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/payments.wallet.one_per_currency') }}</li>
                <li>{{ __('docs/features/payments.wallet.ledger') }}</li>
                <li>{{ __('docs/features/payments.wallet.frozen') }}</li>
                <li>{{ __('docs/features/payments.wallet.withdrawal') }}</li>
            </ul>
        </section>

        {{-- 4. COD Flow --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/payments.cod_flow.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/payments.cod_flow.collect') }}</li>
                <li>{{ __('docs/features/payments.cod_flow.settlements') }}</li>
                <li>{{ __('docs/features/payments.cod_flow.generate') }}</li>
            </ul>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2 text-amber-800 text-sm">
                {{ __('docs/features/payments.cod_flow.note') }}
            </div>
        </section>

        {{-- 5. Refunds --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/payments.refunds.heading') }}</h2>
            <p class="text-gray-600 font-mono text-sm bg-gray-50 border border-gray-200 rounded-lg p-3">
                refunds.net_refund = amount - gateway_fee_deducted - tax_deducted
            </p>
            <p class="text-gray-500 text-xs">{{ __('docs/features/payments.refunds.virtual_note') }}</p>
            <ul class="list-disc list-inside text-gray-600 space-y-1 mt-2">
                <li><strong>{{ __('docs/features/payments.refunds.card_label') }}</strong> {{ __('docs/features/payments.refunds.card') }}</li>
                <li><strong>{{ __('docs/features/payments.refunds.wallet_label') }}</strong> {{ __('docs/features/payments.refunds.wallet') }}</li>
                <li><strong>{{ __('docs/features/payments.refunds.cod_label') }}</strong> {{ __('docs/features/payments.refunds.cod') }}</li>
            </ul>
        </section>

        {{-- 6. Gift Cards --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/payments.gift_cards.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/payments.gift_cards.create') }}</li>
                <li>{{ __('docs/features/payments.gift_cards.redeem') }}</li>
                <li>{{ __('docs/features/payments.gift_cards.manage') }}</li>
            </ul>
        </section>

        {{-- 7. Money Rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/payments.money_rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/payments.money_rules.bigint') }}</li>
                <li>{{ __('docs/features/payments.money_rules.no_divide') }}</li>
                <li>{{ __('docs/features/payments.money_rules.no_sum') }}</li>
                <li>{{ __('docs/features/payments.money_rules.rounding') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
