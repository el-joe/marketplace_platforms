<?php

namespace App\Exceptions;

use Exception;

class GiftCardExpiredException extends Exception
{
    protected $message = 'This gift card has expired.';
}
