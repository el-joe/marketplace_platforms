<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(int $requested, int $available, string $currency = '')
    {
        parent::__construct(
            "Insufficient wallet balance. Requested: {$requested} {$currency}, Available: {$available} {$currency}."
        );
    }
}
