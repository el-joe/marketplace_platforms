<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Models\Marketer;

/**
 * The three marketer types. The value equals the `marketer_jobs.key` of the matching job row.
 * Labels live in lang/{locale}/enums/marketer_type.php.
 *
 * - influencer: one fixed amount per campaign.
 * - affiliate:  percentage of the sale basis.
 * - broker:     fixed amount, percentage, or both.
 */
enum MarketerType: string
{
    use EnumHelpers;

    case Influencer = 'influencer';
    case Affiliate = 'affiliate';
    case Broker = 'broker';

    /** @return array<string, string> value => localized label */
    public static function labels(): array
    {
        return self::options();
    }

    /** Whether this type may be paid a fixed amount. */
    public function allowsFixed(): bool
    {
        return $this !== self::Affiliate;
    }

    /** Whether this type may be paid a percentage of the sale basis. */
    public function allowsPercentage(): bool
    {
        return $this !== self::Influencer;
    }

    /**
     * The types a marketer holds (from marketer_jobs.key), in influencer, broker, affiliate order.
     * Legacy job keys (celebrity, brand_ambassador) are not types and are ignored.
     *
     * @return list<self>
     */
    public static function heldBy(Marketer $marketer): array
    {
        $keys = $marketer->relationLoaded('marketerJobs')
            ? $marketer->marketerJobs->pluck('key')->all()
            : $marketer->marketerJobs()->pluck('key')->all();

        return array_values(array_filter(
            [self::Influencer, self::Broker, self::Affiliate],
            fn (self $t) => in_array($t->value, $keys, true)
        ));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
