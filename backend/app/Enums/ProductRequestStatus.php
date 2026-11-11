<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Lifecycle of a vendor/marketer request to add a new catalog product.
 * Labels live in lang/{locale}/enums/product_request_status.php.
 */
enum ProductRequestStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Badge colour used by the admin and panel views.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-700',
            self::Approved => 'bg-emerald-100 text-emerald-700',
            self::Rejected => 'bg-red-100 text-red-700',
            self::Cancelled => 'bg-gray-100 text-gray-500',
        };
    }
}
