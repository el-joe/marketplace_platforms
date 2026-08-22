<?php

namespace App\Exceptions;

use Exception;

class InvalidGiftCardPinException extends Exception
{
    protected $message = 'Invalid PIN.';
}
