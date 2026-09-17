<?php

namespace App\Enums;

/**
 * enhancement.md P-06: who initiated a cancellation. Drives the
 * cancellable-status boundary in OrderCancellationService:
 *  - Customer: only before the sub-order/item has shipped (self-service).
 *  - Vendor: same boundary as Customer — a vendor can reject/cancel its own
 *    sub-order only before it has shipped.
 *  - Admin: may force-cancel up to (and including) 'delivered' by passing
 *    $force = true to OrderCancellationService::cancel(); cannot cancel
 *    'completed' or already-terminal ('cancelled'/'refunded') scopes even
 *    with force (nothing left to reverse).
 *  - System: used by automated paths that already know the cancellation is
 *    legitimate (RTO on a failed delivery, fraud detection) — always
 *    allowed regardless of status, since these are triggered by the
 *    lifecycle itself rather than a human requesting an exception.
 */
enum CancelActor: string
{
    case Customer = 'customer';
    case Vendor = 'vendor';
    case Admin = 'admin';
    case System = 'system';
}
