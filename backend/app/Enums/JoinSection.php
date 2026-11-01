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

    /** Open Souq: list across the classified categories (real estate, cars, chalets, ...). */
    case OpenSouq = 'open_souq';

    /** Travel agencies publishing trips, packages and bookings. */
    case TravelAgency = 'travel_agency';

    /** Influencers/creators and commission-based marketers (existing marketer registration). */
    case Marketer = 'marketer';

    /** Admin-managed visibility setting key (Settings → General), one boolean per section. */
    public function settingKey(): string
    {
        return 'join_option_'.$this->value.'_enabled';
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
