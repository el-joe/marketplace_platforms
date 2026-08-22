<?php

namespace App\Exceptions;

use Exception;

class GiftCardCurrencyMismatchException extends Exception
{
    protected $message = 'This gift card cannot be used in your country.';
}
