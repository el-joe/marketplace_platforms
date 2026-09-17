<?php

namespace Tests\Support;

use App\Models\Address;
use App\Models\AdminListing;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Country;
use App\Models\CountryPaymentGateway;
use App\Models\Customer;
use App\Models\DeliveryAgent;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerListing;
use App\Models\MarketerProfile;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use App\Models\ShippingZone;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarrantyPlan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A fluent builder that seeds a minimal, internally-consistent marketplace
 * "world" for order-lifecycle feature tests: country/city/shipping zone,
 * category with FBN/FBP commission + brand, a product with two variants,
 * vendor listings (FBP + FBN) and an admin listing (each with warehouse
 * inventory), a marketer with an accepted campaign invitation + marketer
 * listing, a customer with an address + wallet, one coupon per type/funder,
 * warranty plans, payment gateways, and a delivery agent + shipping company
 * supervisor.
 *
 * Usage:
 *   $scenario = MarketplaceScenario::make()->build();
 *   $scenario->customer; $scenario->vendorListingFbp; $scenario->coupons['percentage']; ...
 */
class MarketplaceScenario
{
    public Country $country;
    public City $city;
    public ShippingZone $shippingZone;

    public Category $category;
    public Brand $brand;
    /** @var Commission[] keyed by 'fbn'|'fbp' */
    public array $commissions = [];

    public Product $product;
    /** @var ProductVariant[] */
    public array $variants = [];

    public Vendor $vendor;
    public Warehouse $vendorWarehouse;
    public Warehouse $platformWarehouse;

    public VendorListing $vendorListingFbp;
    public VendorListing $vendorListingFbn;
    public WarehouseInventory $vendorListingFbpInventory;
    public WarehouseInventory $vendorListingFbnInventory;

    public AdminListing $adminListing;
    public WarehouseInventory $adminListingInventory;

    public Marketer $marketer;
    public MarketerProfile $marketerProfile;
    public MarketerCampaign $marketerCampaign;
    public MarketerCampaignInvitation $marketerCampaignInvitation;
    public MarketerListing $marketerListing;

    public Customer $customer;
    public Address $customerAddress;
    public Wallet $customerWallet;

    /** @var array<string, Coupon> keyed by "{type}_{funded_by}" */
    public array $coupons = [];

    public WarrantyPlan $warrantyPlanFlat;
    public WarrantyPlan $warrantyPlanPercentage;

    /** @var array<string, PaymentGateway> keyed by code */
    public array $paymentGateways = [];
    /** @var array<string, CountryPaymentGateway> keyed by code */
    public array $countryPaymentGateways = [];

    public DeliveryAgent $deliveryAgent;
    public ShippingCompany $shippingCompany;
    public ShippingCompanySupervisor $shippingCompanySupervisor;

    public static function make(): self
    {
        return new self();
    }

    public function build(): self
    {
        $this->buildGeo();
        $this->buildCategoryAndBrand();
        $this->buildProductAndVariants();
        $this->buildVendorAndListings();
        $this->buildAdminListing();
        $this->buildMarketer();
        $this->buildCustomer();
        $this->buildCoupons();
        $this->buildWarrantyPlans();
        $this->buildPaymentGateways();
        $this->buildDeliveryAndShipping();

        return $this;
    }

    private function buildGeo(): void
    {
        $this->country = Country::create([
            'id'            => (string) Str::uuid(),
            'iso_code_2'    => 'AE',
            'iso_code_3'    => 'ARE',
            'name_ar'       => 'الإمارات',
            'name_en'       => 'United Arab Emirates - ' . Str::random(6),
            'currency_code' => 'AED',
            'vat_rate'      => 5.00,
            'is_active'     => true,
            'is_launched'   => true,
            'cod_available' => true,
            'timezone'      => 'Asia/Dubai',
        ]);

        $this->shippingZone = ShippingZone::create([
            'name'       => 'Dubai Zone',
            'country_id' => $this->country->id,
            'is_active'  => true,
        ]);

        $this->city = City::create([
            'country_id'       => $this->country->id,
            'name_ar'          => 'دبي',
            'name_en'          => 'Dubai',
            'shipping_zone_id' => $this->shippingZone->id,
            'is_active'        => true,
            'cod_available'    => true,
        ]);
    }

    private function buildCategoryAndBrand(): void
    {
        $slug = 'electronics-' . Str::lower(Str::random(8));

        $this->category = Category::create([
            'id'                   => (string) Str::uuid(),
            'name_en'              => 'Electronics',
            'name_ar'              => 'إلكترونيات',
            'slug'                 => $slug,
            'commission_rate'      => 10.00,
            'commission_fbp_pct'   => 10.00,
            'commission_fbp_fixed' => 200,
            'commission_fbn_pct'   => 12.00,
            'commission_fbn_fixed' => 300,
            'is_active'            => true,
            'is_visible'           => true,
        ]);

        $this->brand = Brand::create([
            'name_en'    => 'Acme',
            'name_ar'    => 'أكمي',
            'slug'       => 'acme-' . Str::lower(Str::random(8)),
            'is_verified' => true,
            'is_active'  => true,
        ]);

        // Category-level commission rows (FBN/FBP), referenced by category_id.
        $this->commissions['fbp'] = Commission::create([
            'category_id'     => $this->category->id,
            'rate_pct'        => 10.00,
            'rate_type'       => 'flat',
            'min_commission'  => 0,
            'effective_from'  => now()->subDay()->toDateString(),
        ]);

        $this->commissions['fbn'] = Commission::create([
            'category_id'     => $this->category->id,
            'rate_pct'        => 12.00,
            'rate_type'       => 'flat',
            'min_commission'  => 0,
            'effective_from'  => now()->subDay()->toDateString(),
        ]);
    }

    private function buildProductAndVariants(): void
    {
        $this->product = Product::create([
            'category_id'  => $this->category->id,
            'brand_id'     => $this->brand->id,
            'name_en'      => 'Test Smartphone',
            'name_ar'      => 'هاتف تجريبي',
            'slug'         => 'test-smartphone-' . Str::lower(Str::random(8)),
            'status'       => 'active',
            'has_variants' => true,
        ]);

        $variantWithImages = ProductVariant::create([
            'product_id'   => $this->product->id,
            'sku'          => 'SKU-' . Str::upper(Str::random(10)),
            'variant_name' => '128GB / Black',
            'is_default'   => true,
            'is_active'    => true,
            'position'     => 0,
        ]);

        ProductImage::create([
            'product_id'         => $this->product->id,
            'product_variant_id' => $variantWithImages->id,
            'path'               => 'products/variant-black-1.jpg',
            'disk'               => 'public',
            'position'           => 0,
            'is_primary'         => true,
        ]);
        ProductImage::create([
            'product_id'         => $this->product->id,
            'product_variant_id' => $variantWithImages->id,
            'path'               => 'products/variant-black-2.jpg',
            'disk'               => 'public',
            'position'           => 1,
            'is_primary'         => false,
        ]);

        $variantWithoutImages = ProductVariant::create([
            'product_id'   => $this->product->id,
            'sku'          => 'SKU-' . Str::upper(Str::random(10)),
            'variant_name' => '256GB / White',
            'is_default'   => false,
            'is_active'    => true,
            'position'     => 1,
        ]);

        $this->variants = [$variantWithImages, $variantWithoutImages];
    }

    private function buildVendorAndListings(): void
    {
        $storeName = 'Test Store ' . Str::random(6);

        $this->vendor = Vendor::create([
            'name'            => 'Test Vendor',
            'email'           => 'vendor-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'           => '+9715' . fake()->numerify('########'),
            'password'        => Hash::make('password'),
            'store_name'      => $storeName,
            'store_slug'      => Str::slug($storeName) . '-' . Str::lower(Str::random(6)),
            'business_type'   => 'llc',
            'payout_schedule' => 'monthly',
            'global_status'   => 'active',
            'country_id'      => $this->country->id,
            'approved_at'     => now(),
            'warranty_months' => 12,
        ]);

        $this->vendorWarehouse = Warehouse::create([
            'country_id'      => $this->country->id,
            'name'            => 'Vendor Warehouse',
            'code'            => 'VW-' . Str::upper(Str::random(6)),
            'type'            => 'seller_owned',
            'owner_vendor_id' => $this->vendor->id,
            'is_active'       => true,
        ]);

        $this->platformWarehouse = Warehouse::create([
            'country_id' => $this->country->id,
            'name'       => 'Platform Warehouse',
            'code'       => 'PW-' . Str::upper(Str::random(6)),
            'type'       => 'platform_fbn',
            'is_active'  => true,
        ]);

        $variant = $this->variants[0];

        // Fulfilled-by-partner (vendor's own warehouse) listing.
        $this->vendorListingFbp = VendorListing::create([
            'id'                 => (string) Str::uuid(),
            'vendor_id'          => $this->vendor->id,
            'product_variant_id' => $variant->id,
            'country_id'         => $this->country->id,
            'warehouse_id'       => $this->vendorWarehouse->id,
            'price'              => 1000.00,
            'currency'           => 'AED',
            'condition'          => 'new',
            'fulfillment_model'  => 'fbm',
            'status'             => 'active',
        ]);

        $this->vendorListingFbpInventory = WarehouseInventory::create([
            'vendor_listing_id' => $this->vendorListingFbp->id,
            'warehouse_id'      => $this->vendorWarehouse->id,
            'quantity_on_hand'  => 50,
            'quantity_reserved' => 0,
        ]);

        // Fulfilled-by-network (platform warehouse) listing.
        $this->vendorListingFbn = VendorListing::create([
            'id'                 => (string) Str::uuid(),
            'vendor_id'          => $this->vendor->id,
            'product_variant_id' => $this->variants[1]->id,
            'country_id'         => $this->country->id,
            'warehouse_id'       => $this->platformWarehouse->id,
            'price'              => 1200.00,
            'currency'           => 'AED',
            'condition'          => 'new',
            'fulfillment_model'  => 'fbn',
            'status'             => 'active',
        ]);

        $this->vendorListingFbnInventory = WarehouseInventory::create([
            'vendor_listing_id' => $this->vendorListingFbn->id,
            'warehouse_id'      => $this->platformWarehouse->id,
            'quantity_on_hand'  => 40,
            'quantity_reserved' => 0,
        ]);
    }

    private function buildAdminListing(): void
    {
        $admin = \App\Models\Admin::factory()->create();

        $this->adminListing = AdminListing::create([
            'warehouse_id'        => $this->platformWarehouse->id,
            'product_variant_id'  => $this->variants[0]->id,
            'country_id'          => $this->country->id,
            'price'               => 90000, // bigint (base currency units)
            'currency'            => 'AED',
            'condition'           => 'new',
            'fulfillment_model'   => 'fbn',
            'status'              => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->adminListingInventory = WarehouseInventory::create([
            'admin_listing_id' => $this->adminListing->id,
            'warehouse_id'     => $this->platformWarehouse->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
        ]);
    }

    private function buildMarketer(): void
    {
        $this->marketer = Marketer::create([
            'name'          => 'Test Marketer',
            'email'         => 'marketer-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'         => '+9715' . fake()->numerify('########'),
            'marketer_type' => 'affiliate',
            'global_status' => 'active',
            'country_id'    => $this->country->id,
            'approved_at'   => now(),
        ]);

        $this->marketerProfile = MarketerProfile::create([
            'marketer_id' => $this->marketer->id,
        ]);

        $this->marketerCampaign = MarketerCampaign::create([
            'vendor_id'                    => $this->vendor->id,
            'owner_type'                   => 'vendor',
            'owner_id'                     => $this->vendor->id,
            'vendor_listing_id'            => $this->vendorListingFbp->id,
            'campaign_category'            => 'product',
            'country_id'                   => $this->country->id,
            'currency'                     => 'AED',
            'commission_type'              => 'fixed',
            'max_commission_budget'        => 100000,
            'platform_commission_amount'   => 0,
            'marketer_commission_amount'   => 5000,
            'status'                       => 'active',
        ]);

        $this->marketerCampaignInvitation = MarketerCampaignInvitation::create([
            'campaign_id'  => $this->marketerCampaign->id,
            'marketer_id'  => $this->marketer->id,
            'status'       => 'accepted',
            'responded_at' => now(),
            'referral_code' => 'REF-' . Str::upper(Str::random(8)),
        ]);

        $this->marketerListing = MarketerListing::create([
            'marketer_id'         => $this->marketer->id,
            'product_variant_id'  => $this->variants[0]->id,
            'listing_category'    => 'product',
            'country_id'          => $this->country->id,
            'invitation_id'       => $this->marketerCampaignInvitation->id,
            'source_type'         => 'vendor_listing',
            'source_listing_id'   => $this->vendorListingFbp->id,
            'price'               => 1050,
            'currency'            => 'AED',
            'status'              => 'active',
            'condition'           => 'new',
            'referral_code'       => 'ML-' . Str::upper(Str::random(8)),
        ]);
    }

    private function buildCustomer(): void
    {
        $this->customer = Customer::create([
            'name'     => 'Test Customer',
            'email'    => 'customer-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'    => '+9715' . fake()->numerify('########'),
            'password' => Hash::make('password'),
            'country_id' => $this->country->id,
            'status'   => 'active',
        ]);

        $this->customerAddress = Address::create([
            'addressable_type' => Customer::class,
            'addressable_id'   => $this->customer->id,
            'label'            => 'Home',
            'recipient_name'   => $this->customer->name,
            'recipient_phone'  => $this->customer->phone,
            'country_id'       => $this->country->id,
            'city_id'          => $this->city->id,
            'area'             => 'Downtown',
            'street_address'   => '123 Test Street',
            'is_default'       => true,
            'address_type'     => 'home',
        ]);

        $this->customerWallet = Wallet::create([
            'owner_type' => \App\Enums\WalletOwnerType::Customer,
            'owner_id'   => $this->customer->id,
            'balance'    => 50000,
            'currency'   => 'AED',
        ]);
    }

    private function buildCoupons(): void
    {
        $admin = \App\Models\Admin::factory()->create();

        $types = ['percentage', 'fixed_amount', 'free_shipping', 'bogo'];
        $funders = ['platform', 'vendor', 'shared'];

        foreach ($types as $type) {
            foreach ($funders as $funder) {
                $key = "{$type}_{$funder}";

                $this->coupons[$key] = Coupon::create([
                    'code'                      => Str::upper(Str::random(3)) . '-' . Str::upper(Str::random(6)),
                    'name'                      => "Test {$type} coupon ({$funder})",
                    'type'                      => $type,
                    'value'                     => $type === 'fixed_amount' ? 50 : 10,
                    'currency'                  => 'AED',
                    'scope'                     => $funder === 'vendor' ? 'vendor' : 'platform',
                    'vendor_id'                 => $funder === 'vendor' || $funder === 'shared' ? $this->vendor->id : null,
                    'usage_limit_per_customer'  => 1,
                    'customer_eligibility'      => 'all',
                    'funded_by'                 => $funder,
                    'vendor_share_pct'          => $funder === 'shared' ? 50 : null,
                    'valid_from'                => now()->subDay(),
                    'valid_until'               => now()->addMonth(),
                    'is_active'                 => true,
                    'created_by_user_id'        => $admin->id,
                ]);
            }
        }
    }

    private function buildWarrantyPlans(): void
    {
        $admin = \App\Models\Admin::factory()->create();

        $this->warrantyPlanFlat = WarrantyPlan::create([
            'category_id'          => $this->category->id,
            'name_en'              => 'Flat Warranty Plan',
            'name_ar'              => 'خطة ضمان ثابتة',
            'duration_months'      => 12,
            'price'                => 5000,
            'price_type'           => 'flat',
            'currency'             => 'AED',
            'is_active'            => true,
            'created_by_admin_id'  => $admin->id,
        ]);

        $this->warrantyPlanPercentage = WarrantyPlan::create([
            'category_id'          => $this->category->id,
            'name_en'              => 'Percentage Warranty Plan',
            'name_ar'              => 'خطة ضمان بنسبة',
            'duration_months'      => 24,
            'price'                => 0,
            'price_type'           => 'percentage',
            'price_pct'            => 5.00,
            'currency'             => 'AED',
            'is_active'            => true,
            'created_by_admin_id'  => $admin->id,
        ]);
    }

    private function buildPaymentGateways(): void
    {
        $definitions = [
            'cod'            => ['type' => 'internal', 'name' => 'Cash on Delivery'],
            'wallet'         => ['type' => 'internal', 'name' => 'Wallet'],
            'stripe'         => ['type' => 'redirect', 'name' => 'Stripe (mocked)'],
            'bank_transfer'  => ['type' => 'offline', 'name' => 'Bank Transfer'],
        ];

        foreach ($definitions as $code => $def) {
            $gateway = PaymentGateway::create([
                'code'            => $code,
                'type'            => $def['type'],
                'name'            => $def['name'],
                'supports_webhook' => $code === 'stripe',
                'supports_refund'  => in_array($code, ['stripe', 'wallet'], true),
                'is_active'        => true,
            ]);

            $this->paymentGateways[$code] = $gateway;

            $this->countryPaymentGateways[$code] = CountryPaymentGateway::create([
                'country_id'  => $this->country->id,
                'gateway_id'  => $gateway->id,
                'display_name_en' => $def['name'],
                'display_name_ar' => $def['name'],
                'is_active'   => true,
                'environment' => 'sandbox',
                'fee_pct'     => $code === 'stripe' ? 2.90 : 0,
                'fee_fixed'   => $code === 'stripe' ? 100 : 0,
            ]);
        }
    }

    private function buildDeliveryAndShipping(): void
    {
        $admin = \App\Models\Admin::factory()->create();

        $this->shippingCompany = ShippingCompany::create([
            'name'          => 'Test Shipping Co',
            'country_id'    => $this->country->id,
            'contact_email' => 'ship-' . Str::lower(Str::random(8)) . '@example.test',
            'status'        => 'active',
            'approved_by_admin_id' => $admin->id,
            'approved_at'   => now(),
        ]);

        $this->shippingCompanySupervisor = ShippingCompanySupervisor::create([
            'shipping_company_id' => $this->shippingCompany->id,
            'country_id'          => $this->country->id,
            'name'                => 'Test Supervisor',
            'email'               => 'supervisor-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'               => '+9715' . fake()->numerify('########'),
            'password'            => Hash::make('password'),
            'is_active'           => true,
        ]);

        $this->deliveryAgent = DeliveryAgent::create([
            'country_id'            => $this->country->id,
            'name'                  => 'Test Delivery Agent',
            'email'                 => 'agent-' . Str::lower(Str::random(8)) . '@example.test',
            'phone'                 => '+9715' . fake()->numerify('########'),
            'password'              => Hash::make('password'),
            'status'                => 'active',
            'agent_type'            => 'third_party',
            'shipping_company_id'   => $this->shippingCompany->id,
            'added_by_supervisor_id' => $this->shippingCompanySupervisor->id,
            'vehicle_type'          => 'motorcycle',
        ]);
    }
}
