<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Storefront surfaces a classified section (a top-level classified category) can be shown on. The admin picks
 * the surfaces per category; the category tree, the order and the countries stay database-driven.
 */
enum ClassifiedPlacement: string
{
    use EnumHelpers;

    /** Header navigation (the classified mega-menu). */
    case Nav = 'nav';

    /** The classified landing page: category pills / section list. */
    case Landing = 'landing';

    /** A dedicated block with the latest ads of the section on the storefront home page. */
    case Home = 'home';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * What categories created before placements existed keep doing: appear in the navigation and on the landing page.
     *
     * @return list<string>
     */
    public static function legacyDefault(): array
    {
        return [self::Nav->value, self::Landing->value];
    }
}
