<?php

use App\Models\PortalContent;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Folds the /companies choice screen into /join:
 *  - adds one admin toggle per /join option (Settings → General),
 *  - moves the Open Souq / Travel Agency card copy from the retired
 *    `companies` Portal Content page onto `join`, and drops the rest.
 * Copy an admin already edited is preserved (moved, not recreated).
 */
return new class extends Migration
{
    private const KEYS = [
        'join_option_product_vendor_enabled' => 'Show "Product Seller" on the /join page',
        'join_option_open_souq_enabled' => 'Show "Open Souq" (classifieds) on the /join page',
        'join_option_travel_agency_enabled' => 'Show "Travel Agency" on the /join page',
        'join_option_marketer_enabled' => 'Show "Influencer / Marketer" on the /join page',
    ];

    public function up(): void
    {
        foreach (self::KEYS as $key => $description) {
            if (! Setting::where('key', $key)->exists()) {
                Setting::create([
                    'key' => $key,
                    'value' => true,
                    'category' => 'general',
                    'description' => $description,
                    'is_public' => false,
                ]);
            }
        }

        foreach (['card_travel_agency', 'card_open_souq'] as $block) {
            $rows = PortalContent::where('page_key', 'companies')->where('block_key', $block)->get();
            foreach ($rows as $row) {
                $taken = PortalContent::where('page_key', 'join')
                    ->where('country_code', $row->country_code)
                    ->where('block_key', $block)
                    ->where('field_key', $row->field_key)
                    ->exists();
                if (! $taken) {
                    $row->update(['page_key' => 'join']);
                }
            }
        }

        PortalContent::where('page_key', 'companies')->delete();
        PortalContent::where('page_key', 'join')->where('block_key', 'card_classified_vendor')
            ->whereNull('updated_by_admin_id')->delete();

        PortalContent::flush('join');
        PortalContent::flush('companies');
    }

    public function down(): void
    {
        Setting::whereIn('key', array_keys(self::KEYS))->delete();
    }
};
