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
         * Table: seller_bank_accounts ColumnTypeDescriptionidUUID PKseller_idUUID FK → seller_profiles.idaccount_holder_nameVARCHAR(150)bank_nameVARCHAR(150)branchVARCHAR(150) NULLibanVARCHAR(50) NULLaccount_number_encryptedTEXTEncrypted at restswift_codeVARCHAR(20) NULLcurrencyCHAR(3)is_primaryBOOLEAN DEFAULT falseverification_statusENUM('pending','verified','rejected')verified_by_admin_idUUID FK → users.id NULLverified_atTIMESTAMP NULLcreated_at, updated_at, deleted_atTIMESTAMPS
         */
        Schema::create('vendor_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->index();
            $table->string('account_holder_name', 150);
            $table->string('bank_name', 150);
            $table->string('branch', 150)->nullable();
            $table->string('iban', 50)->nullable();
            $table->text('account_number_encrypted');
            $table->string('swift_code', 20)->nullable();
            $table->char('currency', 3);
            $table->boolean('is_primary')->default(false);
            $table->enum('verification_status', ['pending', 'verified', 'rejected']);
            $table->uuid('verified_by_admin_id')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_bank_accounts');
    }
};
