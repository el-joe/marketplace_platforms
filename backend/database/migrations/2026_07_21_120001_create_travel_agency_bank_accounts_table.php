<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_agency_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('travel_agency_id')->index();
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

    public function down(): void
    {
        Schema::dropIfExists('travel_agency_bank_accounts');
    }
};
