<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaidAdBookingStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** Statuses that consume slot capacity. */
    public static function holdingStatuses(): array
    {
        return [self::PendingReview, self::Approved, self::Scheduled, self::Active, self::Paused];
    }

    public function canTransitionTo(self $to): bool
    {
        $map = [
            self::Draft->value => [self::PendingReview, self::Cancelled],
            self::PendingReview->value => [self::Approved, self::Rejected, self::Cancelled, self::Expired],
            self::Approved->value => [self::Scheduled, self::Active, self::Cancelled, self::Expired],
            self::Scheduled->value => [self::Active, self::Cancelled, self::Paused],
            self::Active->value => [self::Paused, self::Completed, self::Cancelled],
            self::Paused->value => [self::Active, self::Completed, self::Cancelled],
        ];

        return in_array($to, $map[$this->value] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled, self::Expired], true);
    }
}
