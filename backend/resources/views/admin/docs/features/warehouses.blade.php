@component('admin.docs._layout', ['title' => __('docs/features/warehouses.title'), 'icon' => '🏭', 'breadcrumb' => __('admin.features')])

    <div class="prose prose-sm max-w-none space-y-10">

        {{-- 1. Warehouse Types --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warehouses.types.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><code>platform_fbn</code>: {{ __('docs/features/warehouses.types.platform_fbn') }}</li>
                <li><code>vendor_owned</code>: {{ __('docs/features/warehouses.types.vendor_owned') }}</li>
                <li><code>cross_dock</code>: {{ __('docs/features/warehouses.types.cross_dock') }}</li>
            </ul>
        </section>

        {{-- 2. Inventory Columns --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warehouses.inventory_columns.heading') }}</h2>
            <ul class="list-disc list-inside text-gray-600 space-y-1">
                <li><code>quantity_on_hand</code>: {{ __('docs/features/warehouses.inventory_columns.on_hand') }}</li>
                <li><code>quantity_reserved</code>: {{ __('docs/features/warehouses.inventory_columns.reserved') }}</li>
                <li><code>quantity_available</code>: {{ __('docs/features/warehouses.inventory_columns.available') }} <code>on_hand - reserved</code></li>
            </ul>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-2 text-red-800 text-sm font-medium">
                {{ __('docs/features/warehouses.inventory_columns.warning') }}
            </div>
        </section>

        {{-- 3. inventory_movements --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warehouses.movements.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/warehouses.movements.body') }}</p>
            <p class="text-gray-600">{{ __('docs/features/warehouses.movements.types') }} <code>inbound</code> | <code>outbound</code> | <code>adjustment</code> | <code>transfer</code> | <code>return</code></p>
            <p class="text-gray-600">{{ __('docs/features/warehouses.movements.received_note') }}</p>
        </section>

        {{-- 4. FBN Inbound Request Flow --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warehouses.inbound_flow.heading') }}</h2>
            <ol class="list-decimal list-inside text-gray-600 space-y-1">
                <li>{{ __('docs/features/warehouses.inbound_flow.submit') }}</li>
                <li>{{ __('docs/features/warehouses.inbound_flow.approve') }}</li>
                <li>{{ __('docs/features/warehouses.inbound_flow.ship') }}</li>
                <li>{{ __('docs/features/warehouses.inbound_flow.receive') }}</li>
                <li>{{ __('docs/features/warehouses.inbound_flow.movement_created') }}</li>
                <li>{{ __('docs/features/warehouses.inbound_flow.on_hand_incremented') }}</li>
                <li>{{ __('docs/features/warehouses.inbound_flow.storage_begins') }}</li>
                <li>{{ __('docs/features/warehouses.inbound_flow.orderable') }}</li>
            </ol>
        </section>

        {{-- 5. Free Storage Period --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warehouses.free_storage.heading') }}</h2>
            <p class="text-gray-600"><code>{{ __('docs/features/warehouses.free_storage.default') }}</code></p>
            <p class="text-gray-600 font-mono text-sm bg-gray-50 border border-gray-200 rounded-lg p-3">
                free_period_ends_at = received_at + free_storage_days
            </p>
        </section>

        {{-- 6. Daily Overage Fees --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warehouses.overage_fees.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/warehouses.overage_fees.after') }}</p>
            <p class="text-gray-600 font-mono text-sm bg-gray-50 border border-gray-200 rounded-lg p-3">
                daily_fee = quantity_on_hand &times; daily_fee_per_unit
            </p>
            <p class="text-gray-600">{{ __('docs/features/warehouses.overage_fees.job') }}</p>
            <p class="text-gray-600">{{ __('docs/features/warehouses.overage_fees.monthly') }}</p>
        </section>

        {{-- 7. Inventory Transfers --}}
        <section>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('docs/features/warehouses.transfers.heading') }}</h2>
            <p class="text-gray-600">{{ __('docs/features/warehouses.transfers.body') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['pending', 'in_transit', 'received'] as $status)
                    <span class="px-3 py-1.5 rounded-full bg-primary-50 text-primary-700 text-xs font-medium border border-primary-200">{{ $status }}</span>
                    @if (!$loop->last)
                        <span class="text-gray-400">&rarr;</span>
                    @endif
                @endforeach
                <span class="text-gray-300 mx-1">|</span>
                <span class="px-3 py-1.5 rounded-full bg-red-50 text-red-700 text-xs font-medium border border-red-200">cancelled</span>
            </div>
        </section>

    </div>

@endcomponent
