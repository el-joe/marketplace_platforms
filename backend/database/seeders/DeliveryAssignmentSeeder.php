<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingCarrier;
use App\Models\ShippingMethod;
use App\Models\SubOrder;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds two delivery_assignments for the Playwright Phase 2 lifecycle tests.
 *
 * Prerequisites (run first):
 *   DeliveryAgentSeeder, VendorSeeder, VendorCustomerSeeder (or CustomerSeeder)
 *
 * What gets created:
 *   - 1 order (COD, customer = ali@customer.com, vendor = techzone@vendor.com)
 *   - 2 sub_orders → 2 shipments → 2 delivery_assignments (both status='assigned')
 *     Assignment 1  — lifecycle happy-path test (accept → pick-up → deliver)
 *     Assignment 2  — fail-path test (accept → fail)
 *
 * OTP for both assignments: 123456 (hardcoded — read by the Playwright tests directly)
 *
 * Agent lookup:
 *   Uses DELIVERY_TEST_PHONE env var if set; falls back to mahmoud@delivery.com.
 *   Set DELIVERY_TEST_PHONE in your .env (or .env.testing) to match the agent
 *   account your Playwright auth session logs in as.
 *
 * Idempotent: keyed on sub_order_number — safe to re-run; won't duplicate rows.
 */
class DeliveryAssignmentSeeder extends Seeder
{
    public const TEST_OTP = '123456';

    public function run(): void
    {
        // ── Resolve the test delivery agent ──────────────────────────────────
        $testPhone = '+1-681-242-4935';
        $agent = $testPhone
            ? DeliveryAgent::where('phone', $testPhone)->first()
            : null;

        if (!$agent) {
            $agent = DeliveryAgent::where('email', 'mahmoud@delivery.com')->first();
        }

        if (!$agent) {
            $this->command->error('No delivery agent found. Run DeliveryAgentSeeder first.');
            return;
        }

        $this->command->line("  Using agent: {$agent->name} ({$agent->email})");

        // ── Resolve supporting records ────────────────────────────────────────
        $customer = Customer::where('email', 'ali@customer.com')->first();
        $vendor = Vendor::where('email', 'techzone@vendor.com')->first();
        $shippingMethod = ShippingMethod::first();

        if (!$customer || !$vendor) {
            $this->command->error('Customer ali@customer.com or vendor techzone@vendor.com not found. Run VendorCustomerSeeder first.');
            return;
        }

        if (!$shippingMethod) {
            $this->command->error('No shipping methods found. Run ShippingMethodSeeder or create one in the admin panel first.');
            return;
        }

        $productVariant = ProductVariant::first();
        if (!$productVariant) {
            $this->command->warn('No product variants found — order items will be skipped. Run ProductSeeder for richer fixtures.');
        }

        $carrier = ShippingCarrier::first();
        if (!$carrier) {
            $this->command->error('No shipping carriers found. Run BrandShippingSeeder or ShippingCompanySeeder first.');
            return;
        }

        // ── Create the parent order ───────────────────────────────────────────
        $orderNumber = 'NOON-TEST-DEL-01';

        $order = Order::firstOrCreate(
            ['order_number' => $orderNumber],
            [
                'customer_id' => $customer->id,
                'status' => 'shipped',
                'currency' => 'USD',
                'subtotal' => 5000,
                'discount' => 0,
                'shipping' => 500,
                'tax' => 750,
                'cod_fee' => 200,
                'total' => 6450,
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'shipping_address_snapshot' => [
                    'street' => '15 King Fahd Road',
                    'city' => 'Riyadh',
                    'country' => 'Saudi Arabia',
                ],
                'placed_at' => now()->subHours(3),
                'ip_address' => '127.0.0.1',
            ]
        );

        // ── Assignment 1: happy-path (accept → picked-up → deliver) ──────────
        $this->createAssignment(
            order: $order,
            vendor: $vendor,
            agent: $agent,
            shippingMethodId: $shippingMethod->id,
            carrierId: $carrier->id,
            productVariant: $productVariant,
            subOrderNumber: 'SUB-TEST-DEL-01A',
            label: 'Assignment 1 (happy path)',
        );

        // ── Assignment 2: fail-path (accept → fail) ───────────────────────────
        $this->createAssignment(
            order: $order,
            vendor: $vendor,
            agent: $agent,
            shippingMethodId: $shippingMethod->id,
            carrierId: $carrier->id,
            productVariant: $productVariant,
            subOrderNumber: 'SUB-TEST-DEL-01B',
            label: 'Assignment 2 (fail path)',
        );

        $this->command->info("✅ DeliveryAssignmentSeeder done — OTP for both assignments: " . self::TEST_OTP);
        $this->command->line("  Agent: {$agent->name} | Customer: {$customer->name} | Order: {$orderNumber}");
    }

    private function createAssignment(
        Order $order,
        Vendor $vendor,
        DeliveryAgent $agent,
        string $shippingMethodId,
        string $carrierId,
        ?ProductVariant $productVariant,
        string $subOrderNumber,
        string $label,
    ): void {
        // SubOrder
        $subOrder = SubOrder::firstOrCreate(
            ['sub_order_number' => $subOrderNumber],
            [
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'status' => 'shipped',
                'fulfillment_model' => 'fbm',
                'shipping_method_id' => $shippingMethodId,
                'subtotal' => 2500,
                'shipping' => 250,
                'tax' => 375,
                'vendor_payout' => 2000,
                'platform_commission' => 500,
            ]
        );

        // One order item — requires a real product_variant_id (NOT NULL column)
        if ($productVariant) {
            OrderItem::firstOrCreate(
                ['sub_order_id' => $subOrder->id, 'sku' => 'TEST-SKU-' . $subOrderNumber],
                [
                    'order_id' => $order->id,
                    'product_variant_id' => $productVariant->id,
                    'product_snapshot' => [
                        'name_en' => 'Test Product A',
                        'name_ar' => 'منتج تجريبي أ',
                        'sku' => 'TEST-SKU-' . $subOrderNumber,
                    ],
                    'vendor_id' => $vendor->id,
                    'quantity' => 2,
                    'unit_price' => 1250,
                    'line_subtotal' => 2500,
                    'line_discount' => 0,
                    'line_tax' => 375,
                    'line_total' => 2875,
                    'commission_rate_pct' => 10,
                    'commission_amount' => 250,
                ]
            );
        }

        // Shipment — Eloquent model lacks $incrementing=false/$keyType='string', so we
        // use DB::table() directly to avoid the UUID primary key being cast to 0.
        $shipmentId = DB::table('shipments')->where('sub_order_id', $subOrder->id)->value('id');
        if (!$shipmentId) {
            $shipmentId = Str::uuid()->toString();
            DB::table('shipments')->insert([
                'id' => $shipmentId,
                'sub_order_id' => $subOrder->id,
                'carrier_id' => $carrierId,
                'status' => 'in_transit',
                'tracking_number' => 'TRK-TEST-' . Str::upper(Str::random(6)),
                'weight_grams' => 500,
                'shipping_cost_actual' => 500,
                'delivery_otp' => self::TEST_OTP,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // DeliveryAssignment — keyed on shipment_id
        $existing = DeliveryAssignment::where('shipment_id', $shipmentId)->first();
        if ($existing) {
            $this->command->line("  Skipping {$label} — already exists (id: {$existing->id})");
            return;
        }

        $assignment = DeliveryAssignment::create([
            'shipment_id' => $shipmentId,
            'sub_order_id' => $subOrder->id,
            'agent_id' => $agent->id,
            'status' => DeliveryAssignment::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'delivery_otp' => self::TEST_OTP,
            'otp_attempts' => 0,
            'otp_verified' => false,
        ]);

        $this->command->line("  ✓ {$label}: assignment {$assignment->id}");
    }
}
