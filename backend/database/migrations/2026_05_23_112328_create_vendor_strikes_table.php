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
        Schema::create('vendor_strikes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->index();
            $table->enum('reason', ['late_shipment', 'poor_quality', 'customer_complaint', 'policy_violation', 'other']);
            $table->enum('severity', ['minor', 'major', 'critical'])->default('minor');
            $table->text('description')->nullable();
            $table->uuid('issued_by_admin_id')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_strikes');
    }
};
