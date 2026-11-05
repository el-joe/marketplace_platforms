<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Models\ClassifiedContractTemplate;

/**
 * The business relationship a contract template regulates. Templates of different kinds share storage and
 * versioning only: every kind has its own resolver, signing service and signature table.
 *
 * - seller_platform:  the platform Seller Agreement (seller <-> platform), signed once.
 * - seller_category:  individual classified / product contracts (seller <-> platform).
 * - seller_marketer:  collaboration of a seller with an influencer / celebrity / broker (seller <-> marketer).
 * - customer_purchase: a customer's contract for buying (or renting) a classified item such as a property or a car.
 *
 * Each kind is one case plus its own category_scope value; the kind is derived from the scope in one place
 * ({@see self::forScope()}).
 */
enum ContractKind: string
{
    use EnumHelpers;

    case SellerPlatform = 'seller_platform';
    case SellerCategory = 'seller_category';
    case SellerMarketer = 'seller_marketer';
    case CustomerPurchase = 'customer_purchase';

    /** The kind of a template with this category_scope. */
    public static function forScope(?string $scope): self
    {
        return match ($scope) {
            ClassifiedContractTemplate::SCOPE_PLATFORM => self::SellerPlatform,
            ClassifiedContractTemplate::SCOPE_MARKETER => self::SellerMarketer,
            ClassifiedContractTemplate::SCOPE_CUSTOMER => self::CustomerPurchase,
            default => self::SellerCategory,
        };
    }

    /** The category_scope value templates of this kind are stored with. */
    public function scope(): ?string
    {
        return match ($this) {
            self::SellerPlatform => ClassifiedContractTemplate::SCOPE_PLATFORM,
            self::SellerMarketer => ClassifiedContractTemplate::SCOPE_MARKETER,
            self::CustomerPurchase => ClassifiedContractTemplate::SCOPE_CUSTOMER,
            self::SellerCategory => null,
        };
    }

    public function isMarketerCollaboration(): bool
    {
        return $this === self::SellerMarketer;
    }

    public function isCustomerPurchase(): bool
    {
        return $this === self::CustomerPurchase;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
