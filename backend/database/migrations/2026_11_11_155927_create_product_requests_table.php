<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests from vendors and marketers to add a new product to the catalog.
 * An admin either rejects the request (with a reason) or approves it by completing
 * the full admin product form, which links the created product via product_id.
 * See App\Models\ProductRequest and App\Services\Catalog\ProductRequestService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 30)->unique();

            // vendor | marketer (see App\Models\ProductRequest::REQUESTER_*)
            $table->string('requester_type', 20);
            $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('marketer_id')->nullable()->constrained('marketers')->cascadeOnDelete();
            // The VendorAdmin / MarketerAdmin who submitted the request
            $table->nullableUuidMorphs('requested_by');

            // Target market
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();

            $table->string('name_en');
            $table->string('name_ar');
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            // Free-text brand when it is not in the brands list yet
            $table->string('brand_name', 120)->nullable();
            $table->string('gtin', 14)->nullable()->index();
            $table->string('model_number', 100)->nullable();
            $table->text('description');
            $table->text('variants_note')->nullable();
            $table->string('reference_url', 500)->nullable();
            $table->text('notes')->nullable();
            // Array of paths on the public disk (product-requests/{id}/...)
            $table->json('images');

            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();

            // Admin queue: WHERE status = ? ORDER BY created_at
            $table->index(['status', 'created_at']);
            // Panel lists and duplicate-pending checks per owner
            $table->index(['vendor_id', 'status']);
            $table->index(['marketer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requests');
    }
};
