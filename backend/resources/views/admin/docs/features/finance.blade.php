@component('admin.docs._layout', ['title' => __('docs/features/finance.title'), 'icon' => '💰', 'breadcrumb' => __('docs/features/finance.breadcrumb')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- What it is --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.what_it_is.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/finance.what_it_is.p1') }}</p>
        </section>

        {{-- Vendor Payouts --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.vendor_payouts.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/finance.vendor_payouts.eligibility_label') }}: <code>sub_order.status = 'completed'</code> + {{ __('docs/features/finance.vendor_payouts.eligibility_desc') }}</li>
                <li><code>PayoutCalculationService</code> {{ __('docs/features/finance.vendor_payouts.grouping') }}</li>
                <li>{{ __('docs/features/finance.vendor_payouts.admin_flow_label') }}: <a href="{{ route('admin.payouts.index') }}" class="text-primary-600 hover:underline">{{ __('docs/features/finance.vendor_payouts.approve_process') }}</a> ({{ __('docs/features/finance.vendor_payouts.process_note') }})</li>
                <li>{{ __('docs/features/finance.vendor_payouts.can_also_label') }}: <strong>{{ __('docs/features/finance.vendor_payouts.hold') }}</strong> ({{ __('docs/features/finance.vendor_payouts.hold_note') }}) {{ __('docs/features/finance.vendor_payouts.or') }} <strong>{{ __('docs/features/finance.vendor_payouts.recalculate') }}</strong></li>
            </ul>
        </section>

        {{-- COD Gate --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.cod_gate.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><code>sub_order.cod_remittance_confirmed</code> {{ __('docs/features/finance.cod_gate.must_be') }} <code>true</code> {{ __('docs/features/finance.cod_gate.p1') }}</li>
                <li>{{ __('docs/features/finance.cod_gate.p2') }}</li>
            </ul>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2 text-amber-800 text-sm">
                {{ __('docs/features/finance.cod_gate.notice') }}
            </div>
        </section>

        {{-- Delivery Agent Payouts --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.delivery_payouts.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.delivery.payouts.index') }}" class="text-primary-600 hover:underline">admin/delivery/payouts</a>: <strong>{{ __('docs/features/finance.delivery_payouts.flow') }}</strong>, {{ __('docs/features/finance.delivery_payouts.p1') }}</p>
        </section>

        {{-- Marketer Payouts --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.marketer_payouts.heading') }}</h2>
            <p class="text-gray-600"><a href="{{ route('admin.marketers.payouts.index') }}" class="text-primary-600 hover:underline">admin/marketer-payouts</a>: <strong>{{ __('docs/features/finance.marketer_payouts.flow') }}</strong>, {{ __('docs/features/finance.marketer_payouts.p1') }} <code>marketer_conversions</code> {{ __('docs/features/finance.marketer_payouts.p2') }}</p>
        </section>

        {{-- Ledger --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.ledger.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/finance.ledger.p1') }} <a href="{{ route('admin.ledger.index') }}" class="text-primary-600 hover:underline">admin/ledger</a>. {{ __('docs/features/finance.ledger.p2') }}</p>
        </section>

        {{-- Transactions --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.transactions.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/finance.transactions.p1') }} <a href="{{ route('admin.transactions.index') }}" class="text-primary-600 hover:underline">admin/transactions</a>, {{ __('docs/features/finance.transactions.p2') }}</p>
        </section>

        {{-- Subscriptions --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.subscriptions.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><a href="{{ route('admin.subscriptions.index') }}" class="text-primary-600 hover:underline">admin/subscriptions</a>: {{ __('docs/features/finance.subscriptions.item1') }}</li>
                <li>{{ __('docs/features/finance.subscriptions.plans_label') }}: {{ __('docs/features/finance.subscriptions.plans_desc') }}</li>
                <li>{{ __('docs/features/finance.subscriptions.invoices_label') }}: {{ __('docs/features/finance.subscriptions.invoices_desc') }}</li>
                <li>{{ __('docs/features/finance.subscriptions.item4') }}</li>
            </ul>
        </section>

        {{-- Who / rules --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/finance.who_rules.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><strong>{{ __('docs/features/finance.who_rules.admins_label') }}</strong> {{ __('docs/features/finance.who_rules.admins_desc') }}</li>
                <li><strong>{{ __('docs/features/finance.who_rules.vendors_label') }}</strong> {{ __('docs/features/finance.who_rules.vendors_desc') }}</li>
                <li>{{ __('docs/features/finance.who_rules.currency_rule') }}</li>
            </ul>
        </section>

    </div>

@endcomponent
