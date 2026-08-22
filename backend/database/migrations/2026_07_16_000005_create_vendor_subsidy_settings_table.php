<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_subsidy_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained()->cascadeOnDelete();
            $table->integer('admin_support_cents')->default(0)
                ->comment('Amount the admin/platform covers toward delivery cost for exceptional zones');
            $table->integer('vendor_share_cents')->default(0)
                ->comment('Amount the vendor covers toward delivery cost for exceptional zones');
            $table->string('currency', 3);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'country_id', 'effective_from', 'effective_until'], 'vendor_subsidy_settings_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_subsidy_settings');
    }
};
