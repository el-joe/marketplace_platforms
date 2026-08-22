<?php

namespace App\Exceptions;

use Exception;

class CurrencyMismatchException extends Exception
{
    public function __construct(string $conversionCurrency, string $listingCurrency)
    {
        parent::__construct(
            "Conversion currency ({$conversionCurrency}) does not match listing currency ({$listingCurrency})."
        );
    }
}
