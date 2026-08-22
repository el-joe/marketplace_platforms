<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            // Polymorphic — tokenable_type/_id can be VendorAdmin (char36)
            // or DeliveryAgent (char36). Both PKs are UUIDs.
            $table->string('tokenable_type');
            $table->char('tokenable_id', 36);
            $table->string('token')->unique();
            $table->enum('platform', ['ios', 'android']);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id'], 'device_tokens_tokenable_idx');
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
