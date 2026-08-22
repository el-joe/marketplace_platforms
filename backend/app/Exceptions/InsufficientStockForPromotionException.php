<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockForPromotionException extends RuntimeException
{
    public function __construct(int $available, int $required)
    {
        parent::__construct(
            "Insufficient stock for influencer promotion. Available: {$available}, Required: {$required}."
        );
    }
}
