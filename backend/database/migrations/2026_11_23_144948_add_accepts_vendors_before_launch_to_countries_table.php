<?php

use App\Models\Country;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-country "accept vendor registrations / listings before launch" switch.
 *
 * Vendor-facing country pickers (portal registration, partner listings,
 * marketer and travel-agency sign-up, vendor/marketer APIs) offer launched
 * countries plus draft countries that have this switch on
 * ({@see Country::scopeAvailableForVendors()}).
 *
 * Oman is always selectable in the portal switcher ahead of launch
 * (Country::PORTAL_ALWAYS_SELECTABLE_ISO) and already takes vendor sign-ups,
 * so it is switched on here to keep its current behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->boolean('accepts_vendors_before_launch')->default(false)->after('is_launched');
        });

        DB::table('countries')
            ->whereIn('iso_code_2', ['OM'])
            ->where('is_launched', false)
            ->update(['accepts_vendors_before_launch' => true]);
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('accepts_vendors_before_launch');
        });
    }
};
