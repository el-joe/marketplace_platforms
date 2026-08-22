<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketer_campaign_samples', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('marketer_campaigns')->cascadeOnDelete();

            // Nullable: platform samples don't belong to a specific marketer invitation
            $table->foreignUuid('invitation_id')->nullable()
                  ->constrained('marketer_campaign_invitations')->nullOnDelete();

            $table->enum('sample_owner', ['platform', 'marketer'])
                  ->comment('platform = admin-owned non-refundable; marketer = allocated to specific marketer');

            $table->unsignedSmallInteger('quantity');
            $table->enum('status', ['pending', 'dispatched', 'delivered', 'returned'])->default('pending');

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Delivery address (JSON snapshot)
            $table->json('delivery_address_snapshot')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_campaign_samples');
    }
};
