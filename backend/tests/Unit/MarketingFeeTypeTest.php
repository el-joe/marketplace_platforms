<?php

namespace Tests\Unit;

use App\Enums\FulfillmentModel;
use App\Enums\MarketingFeeType;
use App\Services\MarketerCampaignService;
use Tests\TestCase;

class MarketingFeeTypeTest extends TestCase
{
    public function test_listing_model_maps_to_billing_type(): void
    {
        $this->assertSame(MarketingFeeType::Fbn, MarketingFeeType::fromListingModel(FulfillmentModel::Fbn));
        $this->assertSame(MarketingFeeType::Fbm, MarketingFeeType::fromListingModel(FulfillmentModel::CrossDock));
        $this->assertSame(MarketingFeeType::Fbm, MarketingFeeType::fromListingModel('cross_dock'));
        $this->assertSame(MarketingFeeType::Fbp, MarketingFeeType::fromListingModel(FulfillmentModel::Fbm));
        $this->assertSame(MarketingFeeType::Fbp, MarketingFeeType::fromListingModel('something_else'));
        $this->assertSame(MarketingFeeType::Fbp, MarketingFeeType::fromListingModel(null));
    }

    public function test_labels_are_the_combined_titles_in_both_locales(): void
    {
        $this->assertSame('FBM (cross_dock)', MarketingFeeType::Fbm->labelBilingual()['en']);
        $this->assertSame('FBN (Fulfilled by Nawy)', MarketingFeeType::Fbn->labelBilingual()['en']);
        $this->assertSame('FBP (fbm)', MarketingFeeType::Fbp->labelBilingual()['en']);
        foreach (MarketingFeeType::cases() as $case) {
            $this->assertNotSame('enums/marketing_fee_type.'.$case->value, $case->labelBilingual()['ar']);
        }
    }

    public function test_cross_dock_is_allowed_for_campaigns_by_default(): void
    {
        $this->assertContains('cross_dock', MarketerCampaignService::DEFAULT_ALLOWED_FULFILMENT_MODELS);
    }
}
