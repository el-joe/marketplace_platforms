<?php

namespace App\Exceptions;

/**
 * Thrown by StorageFeeStatusService when an FBN storage fee status change is
 * not allowed. The message is already localized, so controllers can return it as-is.
 */
class StorageFeeStatusChangeException extends \DomainException
{
    public static function invalidTransition(): self
    {
        return new self(__('admin.fbn_section.status_change_invalid'));
    }

    public static function deductedInPayout(string $payoutNumber): self
    {
        return new self(__('admin.fbn_section.revert_blocked_payout', ['payout' => $payoutNumber]));
    }
}
