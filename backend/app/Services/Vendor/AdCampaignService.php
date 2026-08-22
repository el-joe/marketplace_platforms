<?php

namespace App\Services\Vendor;

use App\Enums\AdCampaignStatus;
use App\Enums\AdCampaignTargetingType;
use App\Jobs\NotifyAdminNewAdCampaignJob;
use App\Models\AdCampaign;
use App\Models\AdCampaignCategoryTarget;
use App\Models\AdCampaignKeyword;
use App\Models\AdCampaignProduct;
use App\Enums\VendorListingStatus;
use App\Models\VendorAdmin;
use App\Models\VendorListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdCampaignService
{
    // Fields that are never editable once active (targeting & products locked while running)
    private const LOCKED_WHEN_ACTIVE = ['targeting_type', 'type', 'country_id', 'product_variant_ids', 'keywords', 'category_ids'];

    // Fields that are only editable in pre-approval or paused states (not while active)
    private const EDITABLE_PRE_APPROVAL = ['name', 'starts_at', 'ends_at'];

    public function create(VendorAdmin $vendorAdmin, array $data): AdCampaign
    {
        return DB::transaction(function () use ($vendorAdmin, $data) {
            $this->validateProducts($vendorAdmin, $data['product_variant_ids']);

            $campaign = AdCampaign::create([
                'vendor_id'      => $vendorAdmin->vendor_id,
                'country_id'     => $data['country_id'],
                'name'           => $data['name'],
                'type'           => $data['type'],
                'status'         => AdCampaignStatus::PendingReview->value,
                'budget_total'   => $data['budget_total'],
                'budget_daily'   => $data['budget_daily'] ?? null,
                'bid'            => $data['bid'],
                'targeting_type' => $data['targeting_type'],
                'starts_at'      => $data['starts_at'],
                'ends_at'        => $data['ends_at'] ?? null,
            ]);

            $this->syncProducts($campaign, $vendorAdmin->vendor_id, $data['product_variant_ids']);

            if (in_array($data['targeting_type'], ['keyword', 'mixed'])) {
                $this->syncKeywords($campaign, $data['keywords'] ?? []);
            }

            if (in_array($data['targeting_type'], ['category', 'mixed'])) {
                $this->syncCategoryTargets($campaign, $data['category_ids'] ?? []);
            }

            NotifyAdminNewAdCampaignJob::dispatch($campaign);

            return $campaign;
        });
    }

    public function update(AdCampaign $campaign, VendorAdmin $vendorAdmin, array $data): AdCampaign
    {
        return DB::transaction(function () use ($campaign, $vendorAdmin, $data) {
            $isActive = $campaign->status === AdCampaignStatus::Active;

            foreach (self::LOCKED_WHEN_ACTIVE as $field) {
                if ($isActive && array_key_exists($field, $data)) {
                    throw ValidationException::withMessages([
                        $field => "لا يمكن تعديل هذا الحقل أثناء تشغيل الحملة.",
                    ]);
                }
            }

            if ($isActive) {
                foreach (self::EDITABLE_PRE_APPROVAL as $field) {
                    if (array_key_exists($field, $data)) {
                        throw ValidationException::withMessages([
                            $field => "لا يمكن تعديل هذا الحقل أثناء تشغيل الحملة.",
                        ]);
                    }
                }
            }

            $fillable = ['name', 'type', 'budget_total', 'budget_daily', 'bid', 'starts_at', 'ends_at', 'country_id', 'targeting_type'];
            $update = array_intersect_key($data, array_flip($fillable));
            if (!empty($update)) {
                $campaign->update($update);
            }

            if (isset($data['product_variant_ids'])) {
                $this->validateProducts($vendorAdmin, $data['product_variant_ids']);
                $this->syncProducts($campaign, $vendorAdmin->vendor_id, $data['product_variant_ids']);
            }

            $targetingType = $campaign->fresh()->targeting_type;

            if (isset($data['keywords']) && in_array($targetingType, [AdCampaignTargetingType::Keyword, AdCampaignTargetingType::Mixed], true)) {
                $this->syncKeywords($campaign, $data['keywords']);
            }

            if (isset($data['category_ids']) && in_array($targetingType, [AdCampaignTargetingType::Category, AdCampaignTargetingType::Mixed], true)) {
                $this->syncCategoryTargets($campaign, $data['category_ids']);
            }

            return $campaign->fresh();
        });
    }

    public function canEditField(AdCampaign $campaign, string $field): bool
    {
        return match ($campaign->status) {
            AdCampaignStatus::Draft, AdCampaignStatus::PendingReview, AdCampaignStatus::Rejected => true,
            AdCampaignStatus::Paused => !in_array($field, self::LOCKED_WHEN_ACTIVE),
            AdCampaignStatus::Active => in_array($field, ['budget_daily', 'bid']),
            default => false,
        };
    }

    private function validateProducts(VendorAdmin $vendorAdmin, array $productVariantIds): void
    {
        $validCount = VendorListing::where('vendor_id', $vendorAdmin->vendor_id)
            ->where('status', VendorListingStatus::Active->value)
            ->whereIn('product_variant_id', $productVariantIds)
            ->count();

        if ($validCount !== count($productVariantIds)) {
            throw ValidationException::withMessages([
                'product_variant_ids' => 'بعض المنتجات غير متاحة أو لا تنتمي إلى متجرك.',
            ]);
        }
    }

    private function syncProducts(AdCampaign $campaign, string $vendorId, array $productVariantIds): void
    {
        // Deactivate removed products
        $campaign->products()->whereNotIn('product_variant_id', $productVariantIds)->update(['is_active' => false]);

        foreach ($productVariantIds as $variantId) {
            $listing = VendorListing::where('vendor_id', $vendorId)
                ->where('product_variant_id', $variantId)
                ->where('status', VendorListingStatus::Active->value)
                ->first();

            if (!$listing) continue;

            AdCampaignProduct::updateOrCreate(
                ['ad_campaign_id' => $campaign->id, 'product_variant_id' => $variantId],
                [
                    'vendor_id'         => $vendorId,
                    'vendor_listing_id' => $listing->id,
                    'is_active'         => true,
                ]
            );
        }
    }

    private function syncKeywords(AdCampaign $campaign, array $keywords): void
    {
        $campaign->keywords()->update(['is_active' => false]);

        foreach ($keywords as $kw) {
            AdCampaignKeyword::create([
                'ad_campaign_id'    => $campaign->id,
                'keyword'           => $kw['keyword'],
                'keyword_normalized'=> mb_strtolower(trim($kw['keyword'])),
                'match_type'        => $kw['match_type'],
                'bid_override'      => isset($kw['bid_override']) ? (int) ($kw['bid_override'] * 100) : null,
                'is_negative'       => false,
                'is_active'         => true,
            ]);
        }
    }

    private function syncCategoryTargets(AdCampaign $campaign, array $categoryIds): void
    {
        $campaign->categoryTargets()->update(['is_active' => false]);

        foreach ($categoryIds as $ct) {
            AdCampaignCategoryTarget::create([
                'ad_campaign_id' => $campaign->id,
                'category_id'    => $ct['category_id'],
                'bid_override'   => isset($ct['bid_override']) ? (int) ($ct['bid_override'] * 100) : null,
                'is_active'      => true,
            ]);
        }
    }
}
