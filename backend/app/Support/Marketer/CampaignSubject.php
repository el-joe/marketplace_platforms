<?php

namespace App\Support\Marketer;

use App\Enums\CommissionVertical;
use App\Enums\MarketingFeeType;
use App\Models\Category;
use App\Models\ClassifiedListing;
use App\Models\MarketerCampaign;
use App\Models\TravelPackage;
use App\Models\VendorListing;
use InvalidArgumentException;

/**
 * The thing a marketer campaign promotes, seen through one vertical-neutral shape: a vendor listing (product), a
 * classified listing or a travel package (trip). It gives the wizard, the quote and the campaign service one answer
 * to "which country / currency / category / price / fee type?" instead of a match on the model class everywhere.
 */
final class CampaignSubject
{
    private function __construct(
        public readonly CommissionVertical $vertical,
        public readonly VendorListing|ClassifiedListing|TravelPackage $model,
        public readonly ?string $countryId,
        public readonly string $currency,
        public readonly ?string $categoryId,
        public readonly string $title,
        public readonly float $price,
        public readonly ?string $imageUrl,
    ) {}

    public static function of(VendorListing|ClassifiedListing|TravelPackage $model): self
    {
        return match (true) {
            $model instanceof VendorListing => new self(
                CommissionVertical::Product,
                $model,
                $model->country_id,
                (string) $model->currency,
                $model->productVariant?->product?->category_id,
                (string) ($model->productVariant?->product?->name_ar ?: $model->productVariant?->product?->name_en),
                (float) $model->price,
                $model->productVariant?->product?->image_url ?? null,
            ),
            $model instanceof ClassifiedListing => new self(
                CommissionVertical::Classified,
                $model,
                $model->country_id,
                (string) $model->currency,
                $model->classified_category_id,
                (string) ($model->title_ar ?: $model->title_en),
                (float) $model->price,
                $model->primary_image_url,
            ),
            default => new self(
                CommissionVertical::Travel,
                $model,
                $model->agency?->country_id,
                (string) $model->currency,
                null,
                (string) ($model->title_ar ?: $model->title_en),
                (float) $model->price,
                $model->coverImage()?->url(),
            ),
        };
    }

    /** The subject of an existing campaign. */
    public static function fromCampaign(MarketerCampaign $campaign): ?self
    {
        $model = match ($campaign->campaign_category) {
            'classified' => $campaign->classifiedListing,
            'travel' => $campaign->travelPackage,
            default => $campaign->vendorListing,
        };

        return $model ? self::of($model) : null;
    }

    public function source(): CampaignSource
    {
        return match ($this->vertical) {
            CommissionVertical::Product => CampaignSource::vendorListing($this->model->id),
            CommissionVertical::Classified => CampaignSource::classifiedListing($this->model->id),
            CommissionVertical::Travel => CampaignSource::travelPackage($this->model->id),
        };
    }

    public function isProduct(): bool
    {
        return $this->vertical === CommissionVertical::Product;
    }

    /** Product campaigns are billed per fulfilment model; the other verticals have no such split. */
    public function feeType(): ?MarketingFeeType
    {
        return $this->model instanceof VendorListing
            ? MarketingFeeType::fromListingModel($this->model->fulfillment_model)
            : null;
    }

    public function productCategory(): ?Category
    {
        return $this->model instanceof VendorListing ? $this->model->productVariant?->product?->category : null;
    }

    /**
     * A transient (unsaved) campaign carrying only what MarketerCommissionCalculator needs to resolve vertical and
     * category, for previews made before any campaign exists.
     */
    public function context(): MarketerCampaign
    {
        $context = new MarketerCampaign([
            'campaign_category' => $this->vertical->value,
            'country_id' => $this->countryId,
        ]);

        match (true) {
            $this->model instanceof VendorListing => $context->fill(['vendor_listing_id' => $this->model->id])->setRelation('vendorListing', $this->model),
            $this->model instanceof ClassifiedListing => $context->fill(['classified_listing_id' => $this->model->id])->setRelation('classifiedListing', $this->model),
            $this->model instanceof TravelPackage => $context->fill(['travel_package_id' => $this->model->id])->setRelation('travelPackage', $this->model),
            default => throw new InvalidArgumentException('Unsupported campaign subject.'),
        };

        return $context;
    }
}
