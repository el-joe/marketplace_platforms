<?php

namespace App\Exceptions;

use Exception;

class InsufficientWalletBalanceException extends Exception
{
    public function __construct(string $message = 'Insufficient wallet balance to complete this transaction.')
    {
        parent::__construct($message);
    }
}
