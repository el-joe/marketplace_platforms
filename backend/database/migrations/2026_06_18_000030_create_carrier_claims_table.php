<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('claim_number', 30)->unique();
            $table->foreignUuid('shipment_id')->constrained('shipments');
            $table->foreignUuid('shipping_company_id')->nullable()->constrained('shipping_companies')->nullOnDelete();
            $table->foreignUuid('delivery_agent_id')->nullable()->constrained('delivery_agents')->nullOnDelete();
            $table->enum('claim_type', ['lost', 'damaged', 'delayed', 'wrong_item', 'other']);
            $table->text('description');
            $table->unsignedBigInteger('claimed_amount_cents');
            $table->json('evidence_files')->nullable();
            $table->enum('status', ['submitted', 'under_review', 'approved', 'rejected', 'compensated'])
                  ->default('submitted');
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('compensated_amount_cents')->nullable();
            $table->foreignUuid('resolved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('shipping_company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_claims');
    }
};
