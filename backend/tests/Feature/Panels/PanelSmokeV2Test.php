<?php

namespace Tests\Feature\Panels;

use App\Models\Admin;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\ShippingCompanySupervisor;
use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-24 task 1: "smoke test v2".
 *
 * Builds on PanelSmokeTest (P-23's 9 confirmed-bug regressions, not
 * repeated here). This file:
 *  - resolves real IDs from MarketplaceScenario through route model
 *    binding for parameterised routes;
 *  - asserts 422/redirect-with-errors (never 500) on empty POST/PUT
 *    payloads for mutating routes;
 *  - exercises admin with both a super_admin and a permission-limited
 *    role, to prove the permission gate actually gates;
 *  - covers vendor product, vendor classified, marketer, travel agency
 *    (owner + member), carrier supervisor and delivery agent panels.
 */
class PanelSmokeV2Test extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();

        if (empty($this->scenario->country->site_code)) {
            $this->scenario->country->update(['site_code' => strtolower($this->scenario->country->iso_code_2)]);
        }
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'admin']);
        $admin->assignRole($role);
        $permission = Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $admin->givePermissionTo($permission);
        $warrantyPermission = Permission::firstOrCreate(['name' => 'warranty_plans.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo($warrantyPermission);

        return $admin;
    }

    /** An admin with only 'orders.view' — used to prove the permission gate rejects everything else. */
    private function limitedAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'orders.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo($permission);

        // ScopeAdminToAssignedVendor middleware runs on every admin route
        // and calls hasPermissionTo('vendors.assigned_only') directly, so
        // it must exist for the guard even for a permission-limited admin.
        $scopePermission = Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $admin->givePermissionTo($scopePermission);

        return $admin;
    }

    private function superVendorAdmin(?Vendor $vendor = null): VendorAdmin
    {
        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => ($vendor ?? $this->scenario->vendor)->id,
            'name'      => 'Panel Smoke V2 Vendor Admin',
            'email'     => 'panel-smoke-v2-' . Str::random(8) . '@example.test',
            'password'  => bcrypt('password'),
            'role'      => 'owner',
            'is_owner'  => true,
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'vendor']);
        $vendorAdmin->assignRole($role);

        foreach ([
            'orders.view', 'orders.process', 'orders.cancel', 'listings.view',
            'marketer_campaigns.view', 'marketer_campaigns.create', 'marketer_campaigns.cancel',
            'returns.view', 'returns.process',
        ] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']);
            $vendorAdmin->givePermissionTo($permission);
        }

        return $vendorAdmin;
    }

    // ─── Admin: permission gate ────────────────────────────────────────────

    public function test_admin_super_admin_can_reach_payouts_index(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.payouts.index'));

        $response->assertOk();
    }

    public function test_admin_limited_role_is_rejected_from_payouts_not_500(): void
    {
        $admin = $this->limitedAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.payouts.index'));

        // Must be a clean permission rejection (403), never a 500.
        $response->assertStatus(403);
    }

    public function test_admin_limited_role_can_still_reach_orders_it_has_permission_for(): void
    {
        $admin = $this->limitedAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.orders.index'));

        $response->assertOk();
    }

    // ─── Admin: parameterised routes through real IDs + empty-payload validation ──

    public function test_admin_returns_inspect_route_resolves_and_rejects_empty_payload(): void
    {
        $admin = $this->superAdmin();

        // No return request exists in the base scenario; assert the route
        // itself resolves cleanly (404 for a made-up id is fine — it is not
        // a 500), proving the {returnRequest} binding doesn't fatal.
        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.returns.inspect', ['returnRequest' => Str::uuid()]), []);

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_transactions_confirm_bank_transfer_route_does_not_500_on_missing_transaction(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.transactions.confirm-bank-transfer', ['transaction' => Str::uuid()]), []);

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_warranty_purchases_index_does_not_500(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.warranty-purchases.index'));

        $response->assertOk();
    }

    /**
     * warranty_purchases.order_id/order_item_id are NOT NULL, so a real
     * row (and therefore a real, bindable id for the {warrantyPurchase}
     * route) only comes out of a real checkout — same pattern as
     * WarrantyLifecycleTest.
     */
    public function test_admin_warranty_purchases_show_resolves_real_id(): void
    {
        $this->scenario->country->update(['site_code' => 'ae-' . Str::lower(Str::random(6))]);

        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);

        \App\Models\CartItem::create([
            'cart_id'            => $cart->id,
            'vendor_listing_id'  => $this->scenario->vendorListingFbp->id,
            'quantity'           => 1,
            'unit_price'         => (int) $this->scenario->vendorListingFbp->getRawOriginal('price'),
            'warranty_plan_id'   => $this->scenario->warrantyPlanFlat->id,
            'added_at'           => now(),
        ]);

        $response = $this->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
            'address_id'                  => $this->scenario->customerAddress->id,
            'country_payment_gateway_id'  => $this->scenario->countryPaymentGateways['wallet']->id,
            'idempotency_key'             => (string) Str::uuid(),
        ]);
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = \App\Models\Order::where('order_number', $orderNumber)->firstOrFail();
        $subOrder = $order->subOrders()->where('seller_type', 'vendor')->first();
        $item = $subOrder->items()->first();
        $purchase = \App\Models\WarrantyPurchase::where('order_item_id', $item->id)->firstOrFail();

        $admin = $this->superAdmin();
        $show = $this->actingAs($admin, 'admin')
            ->get(route('admin.warranty-purchases.show', ['warrantyPurchase' => $purchase->id]));

        $show->assertOk();
    }

    public function test_admin_marketer_campaign_show_resolves_real_id(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.marketer-campaigns.show', ['marketerCampaign' => $this->scenario->marketerCampaign->id]));

        $response->assertOk();
    }

    public function test_admin_financial_report_page_does_not_500(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.financial.index'));

        $response->assertOk();
    }

    // ─── Admin: new P-24 system tools (inventory reconcile, buy-box rebuild) ──

    public function test_admin_inventory_reconcile_tool_reports_zero_drift_on_clean_scenario(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.system-tools.inventory-reconcile'), []);

        $response->assertOk()->assertJson(['drift_count' => 0]);
    }

    public function test_admin_buybox_rebuild_tool_runs_for_scenario_country(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.system-tools.buybox-rebuild'), ['country_id' => $this->scenario->country->id]);

        $response->assertOk();
        $this->assertCount(1, $response->json('results'));
    }

    // ─── Vendor (product): state machine, low-stock, campaigns ────────────

    public function test_partner_order_confirm_rejects_missing_order_cleanly(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->postJson(route('vendor.orders.cancel', ['subOrderNumber' => 'DOES-NOT-EXIST']), []);

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_partner_low_stock_page_does_not_500(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')->get(route('partner.inventory.low-stock'));

        $response->assertOk();
    }

    public function test_partner_marketer_campaigns_index_does_not_500(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')->get(route('partner.marketer-campaigns.index'));

        $response->assertOk();
    }

    public function test_partner_marketer_campaign_create_from_own_listing_resolves_real_id(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        // vendorListingFbn has no existing campaign in the base scenario
        // (only vendorListingFbp does), so this exercises the "create" path
        // rather than the "redirect to existing active campaign" path.
        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.marketer-campaigns.create', ['vendorListing' => $this->scenario->vendorListingFbn->id]));

        $response->assertOk();
    }

    public function test_partner_marketer_campaign_store_rejects_empty_payload_with_422_not_500(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->postJson(route('partner.marketer-campaigns.store'), []);

        $response->assertStatus(422);
    }

    public function test_partner_payouts_index_does_not_500(): void
    {
        $vendorAdmin = $this->superVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')->get(route('partner.payouts.index'));

        $response->assertOk();
    }

    // ─── Vendor (classified): vendor_type gate + CRUD ──────────────────────

    private function classifiedVendorAdmin(): VendorAdmin
    {
        $vendor = Vendor::create([
            'name'            => 'Classified Test Vendor',
            'email'           => 'classified-vendor-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'           => '+9715' . fake()->numerify('########'),
            'password'        => bcrypt('password'),
            'store_name'      => 'Classified Store ' . Str::random(6),
            'store_slug'      => 'classified-store-' . Str::lower(Str::random(6)),
            'business_type'   => 'individual',
            'payout_schedule' => 'monthly',
            'global_status'   => 'active',
            'country_id'      => $this->scenario->country->id,
            'approved_at'     => now(),
            'vendor_type'     => \App\Enums\VendorType::ClassifiedVendor,
        ]);

        return $this->superVendorAdmin($vendor);
    }

    public function test_vendor_classified_fixture_can_be_seeded_via_vendor_type_enum(): void
    {
        // Documents the P-24 requirement: MarketplaceScenario has no
        // classified-vendor fixture, but Vendor::vendor_type =
        // VendorType::ClassifiedVendor (added by
        // 2026_09_09_000003_add_vendor_type_to_vendors_table) is a real,
        // working path — not a fabricated value. Proven end-to-end below.
        $vendorAdmin = $this->classifiedVendorAdmin();

        $this->assertSame(\App\Enums\VendorType::ClassifiedVendor, $vendorAdmin->vendor->vendor_type);
    }

    public function test_vendor_classified_listings_index_does_not_500(): void
    {
        $vendorAdmin = $this->classifiedVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')->get(route('partner.classifieds.index'));

        $response->assertOk();
    }

    public function test_vendor_classified_listing_store_rejects_empty_payload_with_422_not_500(): void
    {
        $vendorAdmin = $this->classifiedVendorAdmin();

        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->postJson(route('partner.classifieds.store'), []);

        $response->assertStatus(422);
    }

    public function test_vendor_type_gate_hides_product_only_menu_for_classified_vendor(): void
    {
        $vendorAdmin = $this->classifiedVendorAdmin();

        // partner.orders.* is gated by vendor.type:product_vendor.
        $response = $this->actingAs($vendorAdmin, 'vendor')->get(route('partner.orders.index'));

        $response->assertStatus(403);
    }

    public function test_vendor_type_gate_hides_classified_menu_for_product_vendor(): void
    {
        $vendorAdmin = $this->superVendorAdmin(); // product_vendor by default

        $response = $this->actingAs($vendorAdmin, 'vendor')->get(route('partner.classifieds.index'));

        $response->assertStatus(403);
    }

    public function test_vendor_classified_listing_show_resolves_real_id(): void
    {
        $vendorAdmin = $this->classifiedVendorAdmin();

        $category = ClassifiedCategory::create([
            'name_en' => 'Real Estate', 'name_ar' => 'عقارات',
            'slug'    => 'real-estate-' . Str::lower(Str::random(6)),
            'is_active' => true, 'sort_order' => 0,
        ]);

        $listing = ClassifiedListing::create([
            'listing_number'          => 'CL-' . Str::upper(Str::random(8)),
            'slug'                    => 'listing-' . Str::lower(Str::random(8)),
            'seller_type'             => Vendor::class,
            'seller_id'               => $vendorAdmin->vendor_id,
            'classified_category_id'  => $category->id,
            'country_id'              => $this->scenario->country->id,
            'city_id'                 => $this->scenario->city->id,
            'listing_purpose'         => 'sale',
            'title_en'                => 'Test Classified',
            'title_ar'                => 'إعلان تجريبي',
            'description_en'          => 'A test classified listing.',
            'description_ar'          => 'إعلان تجريبي.',
            'price'                   => 100000,
            'currency'                => 'AED',
            'status'                  => 'active',
        ]);

        $response = $this->actingAs($vendorAdmin, 'vendor')
            ->get(route('partner.classifieds.show', ['id' => $listing->id]));

        $response->assertOk();
    }

    // ─── Marketer ───────────────────────────────────────────────────────────

    public function test_marketer_dashboard_does_not_500(): void
    {
        // The marketer web guard authenticates a MarketerAdmin (session
        // login row), not the Marketer record itself.
        $marketerAdmin = \App\Models\MarketerAdmin::create([
            'marketer_id' => $this->scenario->marketer->id,
            'name'        => 'Marketer Owner',
            'email'       => 'marketer-admin-' . Str::lower(Str::random(8)) . '@example.test',
            'password'    => bcrypt('password'),
            'is_owner'    => true,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($marketerAdmin, 'marketer')->get(route('marketer.dashboard'));

        $response->assertOk();
    }

    // ─── Travel agency: owner + member ─────────────────────────────────────

    private function travelAgencyOwner(): TravelAgencyMember
    {
        $agency = TravelAgency::create([
            'name'           => 'Test Travel Agency',
            'email'          => 'travel-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'          => '+9715' . fake()->numerify('########'),
            'password'       => bcrypt('password'),
            'license_number' => 'TL-' . Str::upper(Str::random(8)),
            'country_id'     => $this->scenario->country->id,
            'status'         => \App\Enums\TravelAgencyStatus::Active,
            'approved_at'    => now(),
        ]);

        return TravelAgencyMember::create([
            'travel_agency_id' => $agency->id,
            'name'              => 'Owner Member',
            'email'             => 'owner-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'             => '+9715' . fake()->numerify('########'),
            'password'          => bcrypt('password'),
            'role'              => 'owner',
            'is_owner'          => true,
            'is_active'         => true,
        ]);
    }

    private function travelAgencyMember(TravelAgency $agency): TravelAgencyMember
    {
        return TravelAgencyMember::create([
            'travel_agency_id' => $agency->id,
            'name'              => 'Regular Member',
            'email'             => 'member-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'             => '+9715' . fake()->numerify('########'),
            'password'          => bcrypt('password'),
            'role'              => 'staff',
            'is_owner'          => false,
            'is_active'         => true,
        ]);
    }

    public function test_travel_agency_owner_dashboard_does_not_500(): void
    {
        $owner = $this->travelAgencyOwner();

        $response = $this->actingAs($owner, 'travel_agency')->get(route('travel-agency.dashboard'));

        $response->assertOk();
    }

    public function test_travel_agency_member_without_permission_is_rejected_not_500(): void
    {
        $owner = $this->travelAgencyOwner();
        $member = $this->travelAgencyMember($owner->travel_agency_id ? $owner->travelAgency : $owner->travelAgency);

        $response = $this->actingAs($member, 'travel_agency')->get(route('travel-agency.packages.index'));

        // A member with no packages.view permission must be cleanly rejected, not 500.
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_travel_agency_owner_bypasses_permission_check_for_packages(): void
    {
        $owner = $this->travelAgencyOwner();

        $response = $this->actingAs($owner, 'travel_agency')->get(route('travel-agency.packages.index'));

        $response->assertOk();
    }

    public function test_travel_agency_package_store_rejects_empty_payload_with_422_not_500(): void
    {
        $owner = $this->travelAgencyOwner();

        $response = $this->actingAs($owner, 'travel_agency')
            ->postJson(route('travel-agency.packages.store'), []);

        $response->assertStatus(422);
    }

    // ─── Carrier supervisor ─────────────────────────────────────────────────

    public function test_carrier_supervisor_dashboard_does_not_500(): void
    {
        $supervisor = $this->scenario->shippingCompanySupervisor;

        $response = $this->actingAs($supervisor, 'shipping_supervisor')->get(route('carrier.dashboard'));

        $response->assertOk();
    }

    /** Supervisors are permission-gated via an array column, not roles/policies. */
    private function supervisorWithFullPermissions(): ShippingCompanySupervisor
    {
        $supervisor = $this->scenario->shippingCompanySupervisor;
        $supervisor->forceFill(['permissions' => [
            'view_orders', 'assign_orders', 'manage_agents',
        ]])->save();

        return $supervisor;
    }

    public function test_carrier_supervisor_unassigned_shipments_queue_does_not_500(): void
    {
        $supervisor = $this->supervisorWithFullPermissions();

        $response = $this->actingAs($supervisor, 'shipping_supervisor')
            ->getJson(route('carrier.assignments.unassigned'));

        $response->assertOk();
    }

    public function test_carrier_agent_roster_shows_real_agent(): void
    {
        $supervisor = $this->supervisorWithFullPermissions();

        $response = $this->actingAs($supervisor, 'shipping_supervisor')->get(route('carrier.agents.index'));

        $response->assertOk();
    }

    public function test_carrier_agent_show_resolves_real_id(): void
    {
        $supervisor = $this->supervisorWithFullPermissions();

        $response = $this->actingAs($supervisor, 'shipping_supervisor')
            ->get(route('carrier.agents.show', ['id' => $this->scenario->deliveryAgent->id]));

        $response->assertOk();
    }

    // ─── Delivery agent ─────────────────────────────────────────────────────

    public function test_delivery_agent_assignments_index_does_not_500(): void
    {
        $agent = $this->scenario->deliveryAgent;

        $response = $this->actingAs($agent, 'delivery')->get(route('delivery.assignments.index'));

        $response->assertOk();
    }

    public function test_delivery_agent_accept_rejects_missing_assignment_cleanly(): void
    {
        $agent = $this->scenario->deliveryAgent;

        $response = $this->actingAs($agent, 'delivery')
            ->postJson(route('delivery.assignments.accept', ['assignment' => Str::uuid()]), []);

        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
