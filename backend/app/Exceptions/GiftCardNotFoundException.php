<?php

namespace App\Exceptions;

use Exception;

class GiftCardNotFoundException extends Exception
{
    protected $message = 'Gift card not found.';
}
