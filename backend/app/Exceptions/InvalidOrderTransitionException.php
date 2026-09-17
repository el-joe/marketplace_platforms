<?php

namespace App\Exceptions;

/**
 * enhancement.md P-08: thrown by OrderStateMachine::transition() whenever a
 * caller (any controller/service/API) tries to move a sub-order to a status
 * that is not reachable from its current status. Centralising this in one
 * exception, thrown from one place, is what makes illegal jumps (e.g.
 * 'placed' -> 'delivered') impossible everywhere rather than in just one
 * controller.
 */
class InvalidOrderTransitionException extends \DomainException
{
    public static function forSubOrder(string $from, string $to): self
    {
        return new self("Cannot transition sub-order from '{$from}' to '{$to}'.");
    }
}
