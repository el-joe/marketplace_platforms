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
        Schema::create('vendor_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->char('vendor_admin_id', 36)->index();
            $table->string('name', 100);
            $table->string('token', 128)->unique();
            $table->string('token_prefix', 12);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('vendor_admin_id')
                ->references('id')
                ->on('vendor_admins')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_api_tokens');
    }
};
