<?php

use App\Services\PortalContentCountrySync;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: every existing global ('*') Portal Content row becomes the
 * default value of every country. Idempotent — only missing rows are inserted,
 * existing country rows (and admin edits) are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PortalContentCountrySync::class)->copyGlobalToAllCountries();
    }

    public function down(): void
    {
        // Data-only, additive: nothing to roll back (the schema migration's
        // down() removes country rows).
    }
};
