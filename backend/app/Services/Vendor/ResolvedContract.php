<?php

namespace App\Services\Vendor;

use App\Models\ClassifiedContractTemplate;
use App\Models\Country;
use Illuminate\Database\Eloquent\Model;

/**
 * What covers a seller for one product / category in one country.
 *
 * - KIND_GLOBAL: the platform Seller Agreement of the seller's country covers it. No separate signature.
 *   $template is that agreement (null while no agreement is configured for the country).
 * - KIND_INDIVIDUAL: an individual contract must be signed. $template is the contract, $subject the
 *   Category / ClassifiedCategory / Product that owns the signature (one signature covers everything
 *   that inherits it).
 */
final class ResolvedContract
{
    public const KIND_GLOBAL = 'global';

    public const KIND_INDIVIDUAL = 'individual';

    public function __construct(
        public readonly ?Country $country,
        public readonly string $kind,
        public readonly ?ClassifiedContractTemplate $template,
        public readonly ?string $scope = null,
        public readonly ?Model $subject = null,
    ) {}

    public static function global(?Country $country, ?ClassifiedContractTemplate $template): self
    {
        return new self($country, self::KIND_GLOBAL, $template);
    }

    public static function individual(?Country $country, ClassifiedContractTemplate $template, string $scope, Model $subject): self
    {
        return new self($country, self::KIND_INDIVIDUAL, $template, $scope, $subject);
    }

    public function isIndividual(): bool
    {
        return $this->kind === self::KIND_INDIVIDUAL;
    }
}
