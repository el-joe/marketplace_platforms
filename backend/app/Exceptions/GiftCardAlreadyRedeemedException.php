<?php

namespace App\Exceptions;

use Exception;

class GiftCardAlreadyRedeemedException extends Exception
{
    protected $message = 'This gift card has already been redeemed.';
}
