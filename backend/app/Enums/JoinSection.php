<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * The top-level account sections a visitor can join the platform through, as
 * presented on the portal /join landing page.
 *
 * Values are deliberately decoupled from {@see VendorType} and
 * {@see MarketerType}: a section is a landing-page grouping, while the actual
 * registration destination is resolved by App\Services\Portal\JoinService.
 *
 * Labels live in lang/{locale}/enums/join_section.php.
 */
enum JoinSection: string
{
    use EnumHelpers;

    /** Sell physical/digital products through vendor listings (existing /register wizard). */
    case ProductVendor = 'product_vendor';

    /** Specialized companies and intermediaries (brokers) that operate on classified categories. */
    case ClassifiedVendor = 'classified_vendor';

    /** Influencers/creators and commission-based marketers (existing marketer registration). */
    case Marketer = 'marketer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
