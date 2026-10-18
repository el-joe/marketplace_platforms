<?php

use App\Models\PortalContent;
use Database\Seeders\PortalContentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: makes every field on the redesigned portal /companies and
 * /marketers choice screens (hero, logo, cards and the Continue action) fully
 * editable through the Portal Content CMS in the admin panel.
 *
 * It inserts the newly CMS-managed rows via the non-destructive
 * PortalContentSeeder; rows an admin has edited are always preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PortalContentSeeder)->run();

        PortalContent::flush('companies');
        PortalContent::flush('marketers');
    }

    public function down(): void
    {
        // Data-only, non-destructive sync: nothing to roll back.
    }
};
