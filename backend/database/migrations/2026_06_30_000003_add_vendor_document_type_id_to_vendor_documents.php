<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: add nullable FK column alongside the existing enum
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->foreignUuid('vendor_document_type_id')
                ->nullable()
                ->after('document_type')
                ->constrained('vendor_document_types')
                ->cascadeOnDelete();
        });

        // Step 2: backfill — match enum string value to vendor_document_types.code
        DB::statement('
            UPDATE vendor_documents vd
            JOIN vendor_document_types vdt ON vdt.code = vd.document_type
            SET vd.vendor_document_type_id = vdt.id
            WHERE vd.vendor_document_type_id IS NULL
        ');

        // Step 3: verification — abort if any rows were not backfilled
        $unmatched = DB::table('vendor_documents')
            ->whereNull('vendor_document_type_id')
            ->count();

        if ($unmatched > 0) {
            // Roll back the FK column so the migration leaves no partial state.
            Schema::table('vendor_documents', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vendor_document_type_id');
            });

            throw new \RuntimeException(
                "Backfill failed: {$unmatched} vendor_documents row(s) could not be matched " .
                'to a vendor_document_types.code. Run VendorDocumentTypeSeeder first.'
            );
        }
    }

    public function down(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_document_type_id');
        });
    }
};
