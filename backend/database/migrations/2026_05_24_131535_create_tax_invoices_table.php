<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Table: tax_invoices Legal e-invoice records (mandatory in KSA/Egypt). ColumnTypeDescriptionidUUID PKinvoice_numberVARCHAR(50) UNIQUEorder_idUUID FK → orders.idseller_idUUID FK → seller_profiles.idcustomer_idUUID FK → users.idsubtotal_centsBIGINTtax_centsBIGINTtotal_centsBIGINTcurrencyCHAR(3)pdf_media_idUUID FK → media.id NULLsubmitted_to_authorityBOOLEAN DEFAULT falseZATCA/ETA submissionauthority_referenceVARCHAR(100) NULLsubmitted_atTIMESTAMP NULLissued_atTIMESTAMPcreated_atTIMESTAMP
         */
        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number', 50)->unique();
            $table->uuid('order_id')->index();
            $table->uuid('vendor_id')->index();
            $table->uuid('customer_id')->index();
            $table->bigInteger('subtotal');
            $table->bigInteger('tax');
            $table->bigInteger('total');
            $table->char('currency', 3);
            $table->uuid('pdf_media_id')->nullable()->index();
            $table->boolean('submitted_to_authority')->default(false);
            $table->string('authority_reference', 100)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_invoices');
    }
};
