<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('claim_number', 30)->unique();
            $table->uuid('customer_id')->index();
            $table->uuid('order_item_id')->index();
            $table->uuid('product_id')->index();
            $table->uuid('vendor_id')->index();
            $table->enum('listing_type', ['vendor_listing', 'admin_listing']);
            $table->enum('issue_type', [
                'defective',
                'not_working',
                'physical_damage',
                'missing_parts',
                'software_issue',
                'other',
            ]);
            $table->text('issue_description');
            $table->date('purchase_date');
            $table->date('warranty_expires_at');
            $table->json('evidence_files')->nullable();
            $table->enum('status', [
                'submitted',
                'under_review',
                'approved',
                'rejected',
                'resolved',
            ])->default('submitted');
            $table->enum('resolution', ['repair', 'replace', 'refund', 'no_action'])->nullable();
            $table->text('resolution_notes')->nullable();
            $table->uuid('resolved_by_admin_id')->nullable()->index();
            $table->text('vendor_response')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claims');
    }
};
