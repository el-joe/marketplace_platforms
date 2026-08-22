<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Safety check before making the column NOT NULL
        $unmatched = DB::table('vendor_documents')
            ->whereNull('vendor_document_type_id')
            ->count();

        if ($unmatched > 0) {
            throw new \RuntimeException(
                "Cannot make vendor_document_type_id NOT NULL: {$unmatched} row(s) are still null. " .
                'Ensure migration 2026_06_30_000003 ran successfully.'
            );
        }

        Schema::table('vendor_documents', function (Blueprint $table) {
            // Make the FK column NOT NULL
            $table->uuid('vendor_document_type_id')->nullable(false)->change();

            // Drop the old enum column
            $table->dropColumn('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            // Restore the enum column (nullable so existing rows don't violate NOT NULL)
            $table->enum('document_type', [
                'business_license',
                'tax_certificate',
                'owner_id',
                'bank_proof',
                'vat_registration',
            ])->nullable()->after('vendor_id');

            $table->uuid('vendor_document_type_id')->nullable()->change();
        });

        // Reverse-backfill the enum from the FK for completeness
        DB::statement('
            UPDATE vendor_documents vd
            JOIN vendor_document_types vdt ON vdt.id = vd.vendor_document_type_id
            SET vd.document_type = vdt.code
            WHERE vd.document_type IS NULL
        ');
    }
};
