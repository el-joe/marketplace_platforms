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
        Schema::table('addresses', function (Blueprint $table) {
            // Drop the old integer-based morph index
            $table->dropIndex('addresses_addressable_type_addressable_id_index');

            // Change addressable_id to varchar(36) to support UUID polymorphic IDs
            $table->string('addressable_id', 36)->change();

            // Re-create the composite morph index
            $table->index(['addressable_type', 'addressable_id'], 'addresses_addressable_type_addressable_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_addressable_type_addressable_id_index');
            $table->unsignedBigInteger('addressable_id')->change();
            $table->index(['addressable_type', 'addressable_id'], 'addresses_addressable_type_addressable_id_index');
        });
    }
};
