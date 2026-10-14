<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Marketing-fee billing type. Single source of the combined labels shown everywhere:
 * FBM (cross_dock), FBN (Fulfilled by Nawy), FBP (fbm). Labels live in lang/{locale}/enums/marketing_fee_type.php.
 */
enum MarketingFeeType: string
{
    use EnumHelpers;

    case Fbm = 'fbm';
    case Fbn = 'fbn';
    case Fbp = 'fbp';

    /** Listing fulfillment_model -> billing type; any unknown / missing value is billed as FBP. */
    public static function fromListingModel(FulfillmentModel|string|null $model): self
    {
        $value = $model instanceof FulfillmentModel ? $model->value : $model;

        return match ($value) {
            FulfillmentModel::Fbn->value => self::Fbn,
            FulfillmentModel::CrossDock->value => self::Fbm,
            default => self::Fbp,
        };
    }
}
