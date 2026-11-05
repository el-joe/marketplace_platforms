<?php

namespace App\Services\Vendor;

use RuntimeException;

/**
 * The store's email is already the login of a different seller account.
 */
class OwnerEmailConflictException extends RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct("Owner email {$email} already belongs to another seller account.");
    }
}
