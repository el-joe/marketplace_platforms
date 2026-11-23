<?php

namespace App\Exceptions;

/**
 * Thrown by OrderCancellationService when a cancellation request cannot take effect. The message is
 * already localized (current app locale), so controllers can return it to the user as-is.
 *
 * Extends \DomainException so existing callers that catch \DomainException keep working.
 */
class OrderNotCancellableException extends \DomainException
{
    public static function alreadyCancelled(): self
    {
        return new self(__('common.exceptions.order.already_cancelled'));
    }

    public static function pastCancellationBoundary(string $subOrderNumber, string $status): self
    {
        $exception = new self(__('common.exceptions.order.cancel_blocked_shipped'));
        $exception->subOrderNumber = $subOrderNumber;
        $exception->status = $status;

        return $exception;
    }

    public static function noEffect(): self
    {
        return new self(__('common.exceptions.order.cancel_failed'));
    }

    public ?string $subOrderNumber = null;

    public ?string $status = null;
}
