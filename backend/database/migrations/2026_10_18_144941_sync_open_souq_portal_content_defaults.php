<?php

use App\Models\PortalContent;
use Database\Seeders\PortalContentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: makes the Open Souq introduction landing fully editable
 * through the Portal Content CMS in the admin panel.
 *
 * It inserts the newly CMS-managed rows via the non-destructive
 * PortalContentSeeder; rows an admin has edited are always preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PortalContentSeeder)->run();

        PortalContent::flush('open-souq');
    }

    public function down(): void
    {
        // Data-only, non-destructive sync: nothing to roll back.
    }
};
