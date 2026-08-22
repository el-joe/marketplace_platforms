<?php

namespace App\Services\Vendor;

use App\Enums\GlobalSystemType;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Models\Vendor;
use App\Services\ListingCertificationGate;
use App\Services\ListingShippingResolver;
use Illuminate\Validation\ValidationException;

class ListingService
{
    public function __construct(private readonly ListingShippingResolver $shippingResolver) {}

    public function create(array $data, Vendor $vendor): VendorListing
    {
        $variant = ProductVariant::findOrFail($data['product_variant_id']);

        if (! $variant->is_active || $variant->trashed()) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['This product variant is not available for listing.'],
            ]);
        }

        $this->assertPromotionFieldsMeetMinimums($data);

        return VendorListing::create([
            'vendor_id'              => $vendor->id,
            'product_variant_id'     => $data['product_variant_id'],
            'country_id'             => $data['country_id'],
            'price'                  => $data['price'],
            'compare_at_price'       => $data['compare_at_price'] ?? null,
            'condition'              => $data['condition'],
            'fulfillment_model'      => $data['fulfillment_model'],
            'status'                 => 'pending_review',
            'influencer_commission_percentage' => $data['influencer_commission_percentage'] ?? null,
            'affiliate_commission_percentage'  => $data['affiliate_commission_percentage'] ?? null,
            'influencer_sample_quota'          => $data['influencer_sample_quota'] ?? null,
            'affiliate_sample_quota'           => $data['affiliate_sample_quota'] ?? null,
        ]);
    }

    private function assertPromotionFieldsMeetMinimums(array $data): void
    {
        if (! empty($data['influencer_commission_percentage'])) {
            $minCommission = (float) Setting::get('min_influencer_commission_percentage', 0);
            if ($data['influencer_commission_percentage'] < $minCommission) {
                throw ValidationException::withMessages([
                    'influencer_commission_percentage' => ["Influencer commission must be at least {$minCommission}%."],
                ]);
            }

            $minSamples = (int) Setting::get('min_influencer_sample_quota', 0);
            if (($data['influencer_sample_quota'] ?? 0) < $minSamples) {
                throw ValidationException::withMessages([
                    'influencer_sample_quota' => ["Influencer sample quota must be at least {$minSamples}."],
                ]);
            }
        }

        if (! empty($data['affiliate_commission_percentage'])) {
            $minCommission = (float) Setting::get('min_affiliate_commission_percentage', 0);
            if ($data['affiliate_commission_percentage'] < $minCommission) {
                throw ValidationException::withMessages([
                    'affiliate_commission_percentage' => ["Affiliate commission must be at least {$minCommission}%."],
                ]);
            }

            $minSamples = (int) Setting::get('min_affiliate_sample_quota', 0);
            if (($data['affiliate_sample_quota'] ?? 0) < $minSamples) {
                throw ValidationException::withMessages([
                    'affiliate_sample_quota' => ["Affiliate sample quota must be at least {$minSamples}."],
                ]);
            }
        }
    }

    public function updatePrice(VendorListing $listing, array $data): VendorListing
    {
        $listing->update([
            'price'            => $data['price'],
            'compare_at_price' => $data['compare_at_price'] ?? null,
        ]);

        return $listing->fresh();
    }

    public function updateStatus(VendorListing $listing, string $status): VendorListing
    {
        if ($status === 'active') {
            ListingCertificationGate::assertCanGoLive($listing);
        }

        $listing->update(['status' => $status]);

        return $listing->fresh();
    }

    public function updateShippingMethod(VendorListing $listing, string $shippingMethodId): VendorListing
    {
        $availableMethods = $this->shippingResolver->resolveForListing($listing->load('productVariant.product.category'));

        if (! $availableMethods->contains('id', $shippingMethodId)) {
            throw ValidationException::withMessages([
                'primary_shipping_method_id' => ['This shipping method is not available for this listing.'],
            ]);
        }

        $listing->update(['primary_shipping_method_id' => $shippingMethodId]);

        return $listing->fresh();
    }

    public function updateDeliveryCoverage(VendorListing $listing, bool $vendorCoversDelivery): VendorListing
    {
        if ($listing->global_system_type !== GlobalSystemType::MerchantFbp) {
            throw ValidationException::withMessages([
                'vendor_covers_delivery' => ['This option is only available for merchant fulfillment (FBP) listings.'],
            ]);
        }

        $listing->update(['vendor_covers_delivery' => $vendorCoversDelivery]);

        return $listing->fresh();
    }

    public function delete(VendorListing $listing): void
    {
        $hasOpenOrders = OrderItem::where('vendor_listing_id', $listing->id)
            ->whereHas('subOrder', fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled', 'refunded']))
            ->exists();

        if ($hasOpenOrders) {
            throw ValidationException::withMessages([
                'listing' => ['Cannot delete a listing with open orders.'],
            ]);
        }

        $listing->update(['status' => 'archived']);
    }
}
