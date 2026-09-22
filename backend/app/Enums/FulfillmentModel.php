<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * `vendor_listings.fulfillment_model` / `admin_listings.fulfillment_model`.
 *
 * Values confirmed against the live DB enum (mysql-schema.sql) and every
 * `in:fbm,fbn,cross_dock` validation rule in the codebase as of 2026-09-23 —
 * there is no `fbp` value in use (the plan doc's "Fbp" was aspirational; the
 * actual third value is `cross_dock`).
 */
enum FulfillmentModel: string
{
    use EnumHelpers;

    case Fbm = 'fbm';
    case Fbn = 'fbn';
    case CrossDock = 'cross_dock';
}
