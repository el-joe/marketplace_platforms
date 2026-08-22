<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * TEST/DEV-ONLY seeder — generates 7 test orders frozen at every distinct stage
 * of the order → sub_order → shipment → delivery_assignment lifecycle.
 *
 * Also fixes the pre-existing broken NOON-TEST-DEL-01 order (in_transit shipments
 * with delivery_assignments stuck at 'assigned' and no tracking events).
 *
 * Usage: php artisan db:seed --class=OrderLifecycleTestSeeder
 * Idempotent: keyed on order_number via firstOrCreate logic. Safe to re-run.
 */
class OrderLifecycleTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'staging')) {
            $this->command->error('OrderLifecycleTestSeeder must not run in production. Aborting.');
            abort(1);
        }

        // ── Resolve live references (never hardcode stale IDs) ───────────────
        $customer = DB::table('customers')->first();
        if (! $customer) {
            $this->command->error('No customer found. Run CustomerSeeder first.');
            return;
        }

        $vendor = DB::table('vendors')->where('global_status', 'active')->first()
            ?? DB::table('vendors')->first();
        if (! $vendor) {
            $this->command->error('No vendor found. Run VendorSeeder first.');
            return;
        }

        $listing = DB::table('vendor_listings')->where('vendor_id', $vendor->id)->first();
        if (! $listing) {
            $this->command->error('No vendor listing found for vendor ' . $vendor->id);
            return;
        }

        $shippingMethod = DB::table('shipping_methods')->first();
        if (! $shippingMethod) {
            $this->command->error('No shipping method found.');
            return;
        }

        // Carriers — match by name fragment, fall back to first available
        $smsa    = DB::table('shipping_carriers')->where('name', 'like', '%SMSA%')->first()
            ?? DB::table('shipping_carriers')->first();
        $aramex  = DB::table('shipping_carriers')->where('name', 'like', '%Aramex%')->first()
            ?? DB::table('shipping_carriers')->first();
        $bosta   = DB::table('shipping_carriers')->where('name', 'like', '%Bosta%')->first()
            ?? DB::table('shipping_carriers')->first();

        // Resolve delivery agents by name (IDs differ per environment)
        $agentRow     = fn(string $name) => DB::table('delivery_agents')->where('name', 'like', "%{$name}%")->first();
        $mahmoudAgent = $agentRow('Mahmoud');
        $khalifaAgent = $agentRow('Khalifa');
        $aramex1Agent = $agentRow('Aramex Rider 1');
        $aramex2Agent = $agentRow('Aramex Rider 2');
        $localAgent   = $agentRow('Local Express');

        foreach (['Mahmoud' => $mahmoudAgent, 'Khalifa' => $khalifaAgent, 'Aramex Rider 1' => $aramex1Agent, 'Aramex Rider 2' => $aramex2Agent, 'Local Express' => $localAgent] as $label => $row) {
            if (! $row) {
                $this->command->error("Delivery agent not found: {$label}. Run DeliveryAgentSeeder first.");
                return;
            }
        }

        $mahmoud      = $mahmoudAgent->id;
        $khalifa      = $khalifaAgent->id;
        $aramexRider1 = $aramex1Agent->id;
        $aramexRider2 = $aramex2Agent->id;
        $localRider   = $localAgent->id;

        $productSnapshot = json_encode([
            'name'  => 'Test Product',
            'sku'   => $listing->sku ?? 'TEST-SKU',
            'image' => null,
        ]);

        $addressSnapshot = json_encode([
            'street'  => '15 King Fahd Road',
            'city'    => 'Riyadh',
            'country' => 'Saudi Arabia',
        ]);

        $now = Carbon::now();

        DB::transaction(function () use (
            $customer, $vendor, $listing, $shippingMethod,
            $smsa, $aramex, $bosta,
            $mahmoud, $khalifa, $aramexRider1, $aramexRider2, $localRider,
            $productSnapshot, $addressSnapshot, $now
        ) {
            // ────────────────────────────────────────────────────────────────
            // FIX existing NOON-TEST-DEL-01
            // ────────────────────────────────────────────────────────────────
            $existingOrder = DB::table('orders')
                ->where('order_number', 'NOON-TEST-DEL-01')
                ->first();

            if ($existingOrder) {
                $subOrders = DB::table('sub_orders')
                    ->where('order_id', $existingOrder->id)
                    ->get();

                foreach ($subOrders as $i => $sub) {
                    $hoursBack = 6 + ($i * 2);

                    // Fix sub_order: add carrier and shipped_at
                    DB::table('sub_orders')->where('id', $sub->id)->update([
                        'carrier_id'  => $smsa->id,
                        'shipped_at'  => $now->copy()->subHours($hoursBack),
                        'updated_at'  => $now,
                    ]);

                    $shipment = DB::table('shipments')
                        ->where('sub_order_id', $sub->id)
                        ->first();

                    if ($shipment) {
                        // Fix shipment: add picked_up_at
                        DB::table('shipments')->where('id', $shipment->id)->update([
                            'picked_up_at' => $now->copy()->subHours($hoursBack - 1),
                            'updated_at'   => $now,
                        ]);

                        // Fix delivery_assignment: advance to picked_up
                        DB::table('delivery_assignments')
                            ->where('shipment_id', $shipment->id)
                            ->update([
                                'status'         => 'picked_up',
                                'accepted_at'    => $now->copy()->subHours($hoursBack - 0.5),
                                'picked_up_at'   => $now->copy()->subHours($hoursBack - 1),
                                'pickup_latitude'  => 24.7136,
                                'pickup_longitude' => 46.6753,
                                'updated_at'     => $now,
                            ]);

                        // Add tracking events if none exist
                        $eventsExist = DB::table('shipment_tracking_events')
                            ->where('shipment_id', $shipment->id)
                            ->exists();

                        if (! $eventsExist) {
                            $base = $now->copy()->subHours($hoursBack + 2);
                            DB::table('shipment_tracking_events')->insert([
                                [
                                    'id'          => Str::uuid(),
                                    'shipment_id' => $shipment->id,
                                    'status'      => 'label_created',
                                    'description' => 'Shipping label generated.',
                                    'location'    => 'Riyadh Warehouse',
                                    'occurred_at' => $base->copy(),
                                    'raw_payload' => null,
                                    'created_at'  => $now,
                                    'updated_at'  => $now,
                                ],
                                [
                                    'id'          => Str::uuid(),
                                    'shipment_id' => $shipment->id,
                                    'status'      => 'picked_up',
                                    'description' => 'Package picked up by carrier.',
                                    'location'    => 'Riyadh Warehouse',
                                    'occurred_at' => $base->copy()->addHour(),
                                    'raw_payload' => null,
                                    'created_at'  => $now,
                                    'updated_at'  => $now,
                                ],
                                [
                                    'id'          => Str::uuid(),
                                    'shipment_id' => $shipment->id,
                                    'status'      => 'in_transit',
                                    'description' => 'Package in transit to destination.',
                                    'location'    => 'Riyadh Sorting Hub',
                                    'occurred_at' => $base->copy()->addHours(2),
                                    'raw_payload' => null,
                                    'created_at'  => $now,
                                    'updated_at'  => $now,
                                ],
                            ]);
                        }
                    }
                }

                $this->command->line('  ✔ Fixed NOON-TEST-DEL-01 (delivery_assignments → picked_up, added tracking events)');
            }

            // ────────────────────────────────────────────────────────────────
            // ORDER 1 — Just placed, nothing shipped yet
            // ────────────────────────────────────────────────────────────────
            $this->createLifecycleOrder(
                'NOON-LIFECYCLE-01',
                'SUB-LIFECYCLE-01',
                [
                    'order_status'        => 'placed',
                    'payment_method'      => 'cod',
                    'payment_status'      => 'pending',
                    'sub_status'          => 'placed',
                    'item_fulfillment'    => 'pending',
                    'carrier_id'          => null,
                    'shipped_at'          => null,
                    'create_shipment'     => false,
                    'create_assignment'   => false,
                    'status_history'      => [
                        ['from' => '', 'to' => 'placed', 'at' => $now->copy()->subHours(2)],
                    ],
                ],
                $customer, $vendor, $listing, $shippingMethod,
                $productSnapshot, $addressSnapshot, $now
            );

            // ────────────────────────────────────────────────────────────────
            // ORDER 2 — Vendor packed it, label created, agent not yet accepted
            // ────────────────────────────────────────────────────────────────
            $o2ShipmentId    = Str::uuid()->toString();
            $o2AssignmentId  = Str::uuid()->toString();
            $this->createLifecycleOrder(
                'NOON-LIFECYCLE-02',
                'SUB-LIFECYCLE-02',
                [
                    'order_status'        => 'confirmed',
                    'payment_method'      => 'card',
                    'payment_status'      => 'captured',
                    'sub_status'          => 'packed',
                    'item_fulfillment'    => 'packed',
                    'carrier_id'          => $smsa->id,
                    'shipped_at'          => null,
                    'create_shipment'     => true,
                    'shipment_id'         => $o2ShipmentId,
                    'shipment_status'     => 'label_created',
                    'shipment_carrier_id' => $smsa->id,
                    'picked_up_at'        => null,
                    'delivery_otp'        => str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'create_assignment'   => true,
                    'assignment_id'       => $o2AssignmentId,
                    'assignment_status'   => 'assigned',
                    'agent_id'            => $mahmoud,
                    'assigned_at'         => $now->copy()->subMinutes(30),
                    'accepted_at'         => null,
                    'picked_up_at_da'     => null,
                    'tracking_events'     => [
                        ['status' => 'label_created', 'desc' => 'Shipping label generated.', 'location' => 'Riyadh Warehouse', 'at' => $now->copy()->subMinutes(20)],
                    ],
                    'status_history'      => [
                        ['from' => '', 'to' => 'placed',     'at' => $now->copy()->subHours(3)],
                        ['from' => 'placed', 'to' => 'confirmed', 'at' => $now->copy()->subHours(2)],
                    ],
                ],
                $customer, $vendor, $listing, $shippingMethod,
                $productSnapshot, $addressSnapshot, $now
            );

            // ────────────────────────────────────────────────────────────────
            // ORDER 3 — Agent accepted, awaiting pickup
            // ────────────────────────────────────────────────────────────────
            $o3ShipmentId   = Str::uuid()->toString();
            $o3AssignmentId = Str::uuid()->toString();
            $this->createLifecycleOrder(
                'NOON-LIFECYCLE-03',
                'SUB-LIFECYCLE-03',
                [
                    'order_status'        => 'confirmed',
                    'payment_method'      => 'card',
                    'payment_status'      => 'captured',
                    'sub_status'          => 'packed',
                    'item_fulfillment'    => 'packed',
                    'carrier_id'          => $smsa->id,
                    'shipped_at'          => null,
                    'create_shipment'     => true,
                    'shipment_id'         => $o3ShipmentId,
                    'shipment_status'     => 'label_created',
                    'shipment_carrier_id' => $smsa->id,
                    'picked_up_at'        => null,
                    'delivery_otp'        => str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'create_assignment'   => true,
                    'assignment_id'       => $o3AssignmentId,
                    'assignment_status'   => 'accepted',
                    'agent_id'            => $khalifa,
                    'assigned_at'         => $now->copy()->subHours(2),
                    'accepted_at'         => $now->copy()->subHour(),
                    'picked_up_at_da'     => null,
                    'tracking_events'     => [
                        ['status' => 'label_created', 'desc' => 'Shipping label generated.', 'location' => 'Riyadh Warehouse', 'at' => $now->copy()->subHours(2)->subMinutes(30)],
                    ],
                    'status_history'      => [
                        ['from' => '', 'to' => 'placed',     'at' => $now->copy()->subHours(5)],
                        ['from' => 'placed', 'to' => 'confirmed', 'at' => $now->copy()->subHours(4)],
                    ],
                ],
                $customer, $vendor, $listing, $shippingMethod,
                $productSnapshot, $addressSnapshot, $now
            );

            // ────────────────────────────────────────────────────────────────
            // ORDER 4 — Picked up, in transit
            // ────────────────────────────────────────────────────────────────
            $o4ShipmentId   = Str::uuid()->toString();
            $o4AssignmentId = Str::uuid()->toString();
            $this->createLifecycleOrder(
                'NOON-LIFECYCLE-04',
                'SUB-LIFECYCLE-04',
                [
                    'order_status'        => 'shipped',
                    'payment_method'      => 'card',
                    'payment_status'      => 'captured',
                    'sub_status'          => 'shipped',
                    'item_fulfillment'    => 'shipped',
                    'carrier_id'          => $aramex->id,
                    'shipped_at'          => $now->copy()->subHours(4),
                    'create_shipment'     => true,
                    'shipment_id'         => $o4ShipmentId,
                    'shipment_status'     => 'in_transit',
                    'shipment_carrier_id' => $aramex->id,
                    'picked_up_at'        => $now->copy()->subHours(4),
                    'delivery_otp'        => str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'create_assignment'   => true,
                    'assignment_id'       => $o4AssignmentId,
                    'assignment_status'   => 'picked_up',
                    'agent_id'            => $aramexRider1,
                    'assigned_at'         => $now->copy()->subHours(6),
                    'accepted_at'         => $now->copy()->subHours(5),
                    'picked_up_at_da'     => $now->copy()->subHours(4),
                    'pickup_latitude'     => 24.7136,
                    'pickup_longitude'    => 46.6753,
                    'tracking_events'     => [
                        ['status' => 'label_created', 'desc' => 'Shipping label generated.',       'location' => 'Riyadh Warehouse',    'at' => $now->copy()->subHours(7)],
                        ['status' => 'picked_up',     'desc' => 'Package picked up by carrier.',   'location' => 'Riyadh Warehouse',    'at' => $now->copy()->subHours(4)],
                        ['status' => 'in_transit',    'desc' => 'Package in transit to destination.', 'location' => 'Riyadh Sorting Hub', 'at' => $now->copy()->subHours(2)],
                    ],
                    'status_history'      => [
                        ['from' => '',        'to' => 'placed',     'at' => $now->copy()->subHours(10)],
                        ['from' => 'placed',  'to' => 'confirmed',  'at' => $now->copy()->subHours(8)],
                        ['from' => 'confirmed', 'to' => 'shipped',  'at' => $now->copy()->subHours(4)],
                    ],
                ],
                $customer, $vendor, $listing, $shippingMethod,
                $productSnapshot, $addressSnapshot, $now
            );

            // ────────────────────────────────────────────────────────────────
            // ORDER 5 — Out for delivery (COD, cash collection flow)
            // ────────────────────────────────────────────────────────────────
            $o5ShipmentId   = Str::uuid()->toString();
            $o5AssignmentId = Str::uuid()->toString();
            $o5Otp          = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $this->createLifecycleOrder(
                'NOON-LIFECYCLE-05',
                'SUB-LIFECYCLE-05',
                [
                    'order_status'        => 'shipped',
                    'payment_method'      => 'cod',
                    'payment_status'      => 'pending',
                    'sub_status'          => 'out_for_delivery',
                    'item_fulfillment'    => 'shipped',
                    'carrier_id'          => $bosta->id,
                    'shipped_at'          => $now->copy()->subHours(5),
                    'create_shipment'     => true,
                    'shipment_id'         => $o5ShipmentId,
                    'shipment_status'     => 'out_for_delivery',
                    'shipment_carrier_id' => $bosta->id,
                    'picked_up_at'        => $now->copy()->subHours(5),
                    'delivery_otp'        => $o5Otp,
                    'create_assignment'   => true,
                    'assignment_id'       => $o5AssignmentId,
                    // delivery_assignments has no 'out_for_delivery' value — picked_up is last pre-delivery status
                    'assignment_status'   => 'picked_up',
                    'agent_id'            => $aramexRider2,
                    'assigned_at'         => $now->copy()->subHours(7),
                    'accepted_at'         => $now->copy()->subHours(6),
                    'picked_up_at_da'     => $now->copy()->subHours(5),
                    'pickup_latitude'     => 24.7136,
                    'pickup_longitude'    => 46.6753,
                    'da_otp'             => $o5Otp,
                    'otp_attempts'       => 0,
                    'otp_verified'       => false,
                    'tracking_events'     => [
                        ['status' => 'label_created',    'desc' => 'Shipping label generated.',              'location' => 'Muscat Warehouse',  'at' => $now->copy()->subHours(8)],
                        ['status' => 'picked_up',        'desc' => 'Package picked up by carrier.',          'location' => 'Muscat Warehouse',  'at' => $now->copy()->subHours(5)],
                        ['status' => 'in_transit',       'desc' => 'Package in transit to destination.',     'location' => 'Muscat Sorting Hub', 'at' => $now->copy()->subHours(3)],
                        ['status' => 'out_for_delivery', 'desc' => 'Out for delivery to customer address.', 'location' => 'Muscat City',       'at' => $now->copy()->subHour()],
                    ],
                    'status_history'      => [
                        ['from' => '',        'to' => 'placed',    'at' => $now->copy()->subHours(10)],
                        ['from' => 'placed',  'to' => 'confirmed', 'at' => $now->copy()->subHours(8)],
                        ['from' => 'confirmed', 'to' => 'shipped', 'at' => $now->copy()->subHours(5)],
                    ],
                ],
                $customer, $vendor, $listing, $shippingMethod,
                $productSnapshot, $addressSnapshot, $now
            );

            // ────────────────────────────────────────────────────────────────
            // ORDER 6 — Fully delivered, COD cash collected
            // ────────────────────────────────────────────────────────────────
            $o6ShipmentId   = Str::uuid()->toString();
            $o6AssignmentId = Str::uuid()->toString();
            $o6Otp          = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $deliveredAt    = $now->copy()->subHours(1);
            // COD amount = sub_order subtotal + shipping (what the agent actually collects)
            $o6SubtotalCents = 5000;
            $o6ShippingCents = 500;
            $o6CodCollected  = $o6SubtotalCents + $o6ShippingCents;
            $this->createLifecycleOrder(
                'NOON-LIFECYCLE-06',
                'SUB-LIFECYCLE-06',
                [
                    'order_status'        => 'delivered',
                    'payment_method'      => 'cod',
                    'payment_status'      => 'captured',
                    // completed_at left null — 'delivered' != 'completed'; completed fires after return window closes
                    'completed_at'        => null,
                    'sub_status'          => 'delivered',
                    'item_fulfillment'    => 'delivered',
                    'carrier_id'          => $bosta->id,
                    'shipped_at'          => $now->copy()->subHours(24),
                    'delivered_at_sub'    => $deliveredAt,
                    'create_shipment'     => true,
                    'shipment_id'         => $o6ShipmentId,
                    'shipment_status'     => 'delivered',
                    'shipment_carrier_id' => $bosta->id,
                    'picked_up_at'        => $now->copy()->subHours(24),
                    'delivered_at_ship'   => $deliveredAt,
                    'delivery_otp'        => $o6Otp,
                    'create_assignment'   => true,
                    'assignment_id'       => $o6AssignmentId,
                    'assignment_status'   => 'delivered',
                    'agent_id'            => $localRider,
                    'assigned_at'         => $now->copy()->subHours(26),
                    'accepted_at'         => $now->copy()->subHours(25),
                    'picked_up_at_da'     => $now->copy()->subHours(24),
                    'delivered_at_da'     => $deliveredAt,
                    'pickup_latitude'     => 23.5880,
                    'pickup_longitude'    => 58.3829,
                    'delivery_latitude'   => 23.6140,
                    'delivery_longitude'  => 58.5922,
                    'da_otp'             => $o6Otp,
                    'otp_attempts'       => 1,
                    'otp_verified'       => true,
                    'cod_amount_collected' => $o6CodCollected,
                    'customer_rating'    => 5,
                    'agent_notes'        => 'Delivered to customer directly.',
                    // return_eligible_until = delivered_at + 14 days
                    'return_eligible_until' => $deliveredAt->copy()->addDays(14)->toDateString(),
                    'tracking_events'     => [
                        ['status' => 'label_created',    'desc' => 'Shipping label generated.',              'location' => 'Muscat Warehouse',  'at' => $now->copy()->subHours(25)],
                        ['status' => 'picked_up',        'desc' => 'Package picked up by carrier.',          'location' => 'Muscat Warehouse',  'at' => $now->copy()->subHours(24)],
                        ['status' => 'in_transit',       'desc' => 'Package in transit.',                    'location' => 'Muscat Sorting Hub', 'at' => $now->copy()->subHours(12)],
                        ['status' => 'out_for_delivery', 'desc' => 'Out for delivery to customer address.', 'location' => 'Muscat City',       'at' => $now->copy()->subHours(3)],
                        ['status' => 'delivered',        'desc' => 'Package delivered to customer.',         'location' => 'Customer Address',  'at' => $deliveredAt],
                    ],
                    'status_history'      => [
                        ['from' => '',           'to' => 'placed',     'at' => $now->copy()->subHours(30)],
                        ['from' => 'placed',     'to' => 'confirmed',  'at' => $now->copy()->subHours(28)],
                        ['from' => 'confirmed',  'to' => 'shipped',    'at' => $now->copy()->subHours(24)],
                        ['from' => 'shipped',    'to' => 'delivered',  'at' => $deliveredAt],
                    ],
                    'subtotal'            => $o6SubtotalCents,
                    'shipping'            => $o6ShippingCents,
                ],
                $customer, $vendor, $listing, $shippingMethod,
                $productSnapshot, $addressSnapshot, $now
            );

            // ────────────────────────────────────────────────────────────────
            // ORDER 7 — Failed delivery attempt (same shape as Order 5)
            // ────────────────────────────────────────────────────────────────
            $o7ShipmentId   = Str::uuid()->toString();
            $o7AssignmentId = Str::uuid()->toString();
            $o7Otp          = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $failedAt       = $now->copy()->subHours(1);
            $this->createLifecycleOrder(
                'NOON-LIFECYCLE-07',
                'SUB-LIFECYCLE-07',
                [
                    'order_status'        => 'shipped',
                    'payment_method'      => 'cod',
                    'payment_status'      => 'pending',
                    // sub_order stays out_for_delivery — platform doesn't auto-cancel on one failed attempt
                    'sub_status'          => 'out_for_delivery',
                    'item_fulfillment'    => 'shipped',
                    'carrier_id'          => $bosta->id,
                    'shipped_at'          => $now->copy()->subHours(6),
                    'create_shipment'     => true,
                    'shipment_id'         => $o7ShipmentId,
                    'shipment_status'     => 'failed',
                    'shipment_carrier_id' => $bosta->id,
                    'picked_up_at'        => $now->copy()->subHours(6),
                    'delivery_otp'        => $o7Otp,
                    'create_assignment'   => true,
                    'assignment_id'       => $o7AssignmentId,
                    'assignment_status'   => 'failed',
                    'agent_id'            => $aramexRider2,
                    'assigned_at'         => $now->copy()->subHours(8),
                    'accepted_at'         => $now->copy()->subHours(7),
                    'picked_up_at_da'     => $now->copy()->subHours(6),
                    'failed_at'           => $failedAt,
                    'failure_reason'      => 'customer_not_home',
                    'failure_notes'       => 'Called twice, no answer. Left at neighbor per customer SMS confirmation.',
                    'pickup_latitude'     => 24.7136,
                    'pickup_longitude'    => 46.6753,
                    'da_otp'             => $o7Otp,
                    'otp_attempts'       => 0,
                    'otp_verified'       => false,
                    'tracking_events'     => [
                        ['status' => 'label_created',    'desc' => 'Shipping label generated.',              'location' => 'Muscat Warehouse', 'at' => $now->copy()->subHours(9)],
                        ['status' => 'picked_up',        'desc' => 'Package picked up by carrier.',          'location' => 'Muscat Warehouse', 'at' => $now->copy()->subHours(6)],
                        ['status' => 'in_transit',       'desc' => 'Package in transit.',                    'location' => 'Muscat Sorting Hub', 'at' => $now->copy()->subHours(4)],
                        ['status' => 'out_for_delivery', 'desc' => 'Out for delivery to customer address.', 'location' => 'Muscat City',      'at' => $now->copy()->subHours(2)],
                        ['status' => 'failed',           'desc' => 'Delivery attempt failed — customer not home.', 'location' => 'Customer Address', 'at' => $failedAt],
                    ],
                    'status_history'      => [
                        ['from' => '',        'to' => 'placed',    'at' => $now->copy()->subHours(10)],
                        ['from' => 'placed',  'to' => 'confirmed', 'at' => $now->copy()->subHours(9)],
                        ['from' => 'confirmed', 'to' => 'shipped', 'at' => $now->copy()->subHours(6)],
                    ],
                ],
                $customer, $vendor, $listing, $shippingMethod,
                $productSnapshot, $addressSnapshot, $now
            );
        });

        $this->command->info(
            'Created/verified 7 test lifecycle orders across stages: ' .
            'placed, packed, accepted, in_transit, out_for_delivery, delivered, failed.'
        );
    }

    private function createLifecycleOrder(
        string $orderNumber,
        string $subOrderNumber,
        array $cfg,
        object $customer,
        object $vendor,
        object $listing,
        object $shippingMethod,
        string $productSnapshot,
        string $addressSnapshot,
        Carbon $now
    ): void {
        // Skip if already exists (idempotency)
        if (DB::table('orders')->where('order_number', $orderNumber)->exists()) {
            $this->command->line("  ↳ {$orderNumber} already exists, skipping.");
            return;
        }

        $subtotal  = $cfg['subtotal']       ?? 5000;
        $shipping  = $cfg['shipping'] ?? 500;
        $tax       = (int) round($subtotal * 0.15);
        $codFee    = $cfg['payment_method'] === 'cod' ? 200 : 0;
        $total     = $subtotal + $shipping + $tax + $codFee;

        $orderId    = Str::uuid()->toString();
        $subOrderId = Str::uuid()->toString();

        // Order
        DB::table('orders')->insert([
            'id'                       => $orderId,
            'order_number'             => $orderNumber,
            'customer_id'              => $customer->id,
            'status'                   => $cfg['order_status'],
            'currency'                 => 'SAR',
            'subtotal'                 => $subtotal,
            'discount'                 => 0,
            'shipping'                 => $shipping,
            'tax'                      => $tax,
            'cod_fee'                  => $codFee,
            'total'                    => $total,
            'coupon_id'                => null,
            'coupon_code_used'         => null,
            'payment_method'           => $cfg['payment_method'],
            'payment_status'           => $cfg['payment_status'],
            'shipping_address_snapshot'=> $addressSnapshot,
            'billing_address_snapshot' => null,
            'customer_notes'           => null,
            'ip_address'               => '127.0.0.1',
            'user_agent'               => null,
            'device_fingerprint'       => null,
            'risk_score'               => null,
            'placed_at'                => $now->copy()->subHours(24),
            'completed_at'             => $cfg['completed_at'] ?? null,
            'cancelled_at'             => null,
            'created_at'               => $now,
            'updated_at'               => $now,
        ]);

        // Sub-order
        $platform_commission = (int) round($subtotal * 0.10);
        $vendor_payout       = $subtotal - $platform_commission;

        DB::table('sub_orders')->insert([
            'id'                  => $subOrderId,
            'order_id'            => $orderId,
            'sub_order_number'    => $subOrderNumber,
            'vendor_id'           => $vendor->id,
            'warehouse_id'        => null,
            'status'              => $cfg['sub_status'],
            'fulfillment_model'   => 'fbm',
            'subtotal'            => $subtotal,
            'shipping'            => $shipping,
            'tax'                 => $tax,
            'platform_commission' => $platform_commission,
            'vendor_payout'       => $vendor_payout,
            'shipping_method_id'  => $shippingMethod->id,
            'carrier_id'          => $cfg['carrier_id'] ?? null,
            'tracking_number'     => isset($cfg['shipment_id']) ? ('TRK-' . strtoupper(Str::random(8))) : null,
            'estimated_delivery_date' => null,
            'shipped_at'          => $cfg['shipped_at'] ?? null,
            'delivered_at'        => $cfg['delivered_at_sub'] ?? null,
            'cancelled_at'        => null,
            'cancellation_reason' => null,
            'sla_ship_deadline'   => null,
            'sla_breached'        => false,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        // Order items (1 item for simplicity)
        $itemId = Str::uuid()->toString();
        DB::table('order_items')->insert([
            'id'                  => $itemId,
            'order_id'            => $orderId,
            'sub_order_id'        => $subOrderId,
            'product_variant_id'  => $listing->product_variant_id,
            'product_snapshot'    => $productSnapshot,
            'vendor_id'           => $vendor->id,
            'sku'                 => $listing->sku ?? 'TEST-SKU',
            'quantity'            => 2,
            'unit_price'          => (int) ($subtotal / 2),
            'unit_cost_price'     => null,
            'line_subtotal'       => $subtotal,
            'line_discount'       => 0,
            'line_tax'            => $tax,
            'line_total'          => $subtotal + $tax,
            'commission_rate_pct' => 10.00,
            'commission_amount'   => $platform_commission,
            'fulfillment_status'  => $cfg['item_fulfillment'],
            'return_eligible_until' => $cfg['return_eligible_until'] ?? null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        // Shipment
        $shipmentId = null;
        if (! empty($cfg['create_shipment'])) {
            $shipmentId = $cfg['shipment_id'];
            DB::table('shipments')->insert([
                'id'                  => $shipmentId,
                'sub_order_id'        => $subOrderId,
                'carrier_id'          => $cfg['shipment_carrier_id'],
                'tracking_number'     => 'TRK-TEST-' . strtoupper(Str::random(6)),
                'awb_label_url'       => null,
                'weight_grams'        => 500,
                'dimensions'          => null,
                'shipping_cost_actual'=> $shipping,
                'status'              => $cfg['shipment_status'],
                'picked_up_at'        => $cfg['picked_up_at'] ?? null,
                'delivered_at'        => $cfg['delivered_at_ship'] ?? null,
                'delivery_otp'        => $cfg['delivery_otp'] ?? null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            // Tracking events
            foreach ($cfg['tracking_events'] ?? [] as $event) {
                DB::table('shipment_tracking_events')->insert([
                    'id'          => Str::uuid()->toString(),
                    'shipment_id' => $shipmentId,
                    'status'      => $event['status'],
                    'description' => $event['desc'],
                    'location'    => $event['location'] ?? null,
                    'occurred_at' => $event['at'],
                    'raw_payload' => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        // Delivery assignment
        if (! empty($cfg['create_assignment']) && $shipmentId) {
            DB::table('delivery_assignments')->insert([
                'id'                        => $cfg['assignment_id'],
                'shipment_id'               => $shipmentId,
                'sub_order_id'              => $subOrderId,
                'agent_id'                  => $cfg['agent_id'],
                'status'                    => $cfg['assignment_status'],
                'assigned_at'               => $cfg['assigned_at'],
                'accepted_at'               => $cfg['accepted_at'] ?? null,
                'picked_up_at'              => $cfg['picked_up_at_da'] ?? null,
                'delivered_at'              => $cfg['delivered_at_da'] ?? null,
                'failed_at'                 => $cfg['failed_at'] ?? null,
                'failure_reason'            => $cfg['failure_reason'] ?? null,
                'failure_notes'             => $cfg['failure_notes'] ?? null,
                'delivery_otp'              => $cfg['da_otp'] ?? $cfg['delivery_otp'] ?? null,
                'cod_amount_collected'=> $cfg['cod_amount_collected'] ?? null,
                'otp_attempts'              => $cfg['otp_attempts'] ?? 0,
                'otp_verified'              => $cfg['otp_verified'] ?? false,
                'proof_file_id'             => null,
                'agent_notes'               => $cfg['agent_notes'] ?? null,
                'pickup_latitude'           => $cfg['pickup_latitude'] ?? null,
                'pickup_longitude'          => $cfg['pickup_longitude'] ?? null,
                'delivery_latitude'         => $cfg['delivery_latitude'] ?? null,
                'delivery_longitude'        => $cfg['delivery_longitude'] ?? null,
                'customer_rating'           => $cfg['customer_rating'] ?? null,
                'created_at'               => $now,
                'updated_at'               => $now,
            ]);
        }

        // Order status histories
        foreach ($cfg['status_history'] ?? [] as $hist) {
            DB::table('order_status_histories')->insert([
                'id'                  => Str::uuid()->toString(),
                'order_id'            => $orderId,
                'sub_order_id'        => null,
                'from_status'         => $hist['from'],
                'to_status'           => $hist['to'],
                'changed_by_admin_id' => null,
                'reason'              => null,
                'metadata'            => null,
                'created_at'          => $hist['at'],
                'updated_at'          => $hist['at'],
            ]);
        }

        $this->command->line("  ✔ {$orderNumber} — order.status={$cfg['order_status']}, sub.status={$cfg['sub_status']}");
    }
}
