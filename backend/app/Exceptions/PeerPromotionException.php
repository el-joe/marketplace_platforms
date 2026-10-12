<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * P13 (R11): an influencer-to-influencer promotion request that breaks a rule.
 * `reason` is a stable key (lang: marketer.campaigns.peer.errors.<reason>) so panels can
 * show a localized message while the exception message stays English for logs.
 */
class PeerPromotionException extends RuntimeException
{
    public const SELF_PROMOTION = 'self_promotion';

    public const LISTING_NOT_ACTIVE = 'listing_not_active';

    public const NOT_PEER_LISTING = 'not_peer_listing';

    public const NOT_INFLUENCER = 'not_influencer';

    public const MONTHLY_LIMIT = 'monthly_limit';

    public const INSUFFICIENT_STOCK = 'insufficient_stock';

    public function __construct(public readonly string $reason, string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
