<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_agents', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->foreignUuid('country_id')->constrained('countries');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->unique();
            $table->string('password');

            $table->enum('status', ['active', 'inactive', 'on_shift', 'suspended'])->default('active');
            $table->enum('agent_type', ['platform', 'third_party'])->default('platform');
            $table->enum('vehicle_type', ['motorcycle', 'car', 'van', 'bicycle'])->default('motorcycle');

            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->boolean('is_available')->default(false);

            $table->decimal('rating_avg', 3, 2)->default(5.00);
            $table->unsignedInteger('total_deliveries')->default(0);

            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_id', 'is_available', 'status'], 'delivery_agents_dispatch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_agents');
    }
};
