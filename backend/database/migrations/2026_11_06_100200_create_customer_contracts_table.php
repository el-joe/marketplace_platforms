<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signed customer purchase contracts (kind customer_purchase): a customer's recorded acceptance of the contract that
     * applies to one classified advertisement (property, vehicle, ...). A separate concept from vendor_contracts
     * (seller <-> platform) and marketer_seller_contracts (seller <-> marketer): it shares only the template storage.
     *
     * The rendered text (or a private copy of the uploaded document), its hash and the variables are frozen at signing;
     * customer, listing, category and seller names are also kept as a snapshot so the record stays readable if the
     * source rows are later removed. Only the status may change afterwards.
     */
    public function up(): void
    {
        if (! Schema::hasTable('customer_contracts')) {
            Schema::create('customer_contracts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id')->nullable();
                $table->uuid('contract_template_id');
                $table->unsignedInteger('template_version');
                $table->uuid('classified_listing_id')->nullable();
                $table->uuid('classified_inquiry_id')->nullable();
                $table->uuid('classified_category_id')->nullable();
                $table->uuid('country_id')->nullable()->comment('The advertisement country when signed.');
                $table->enum('purpose', ['purchase', 'rental'])->default('purchase');
                $table->string('customer_name', 150)->comment('Account name when signed (snapshot).');
                $table->string('customer_email')->nullable();
                $table->string('listing_number', 40)->nullable();
                $table->string('listing_title')->nullable();
                $table->enum('language_signed', ['en', 'ar'])->default('en');
                $table->enum('content_mode', ['editor', 'file'])->default('editor');
                $table->longText('rendered_content')->comment('Frozen contract text. Never updated after signing.');
                $table->char('rendered_content_hash', 64);
                $table->json('variables');
                $table->string('signed_file_path')->nullable();
                $table->string('signed_file_name')->nullable();
                $table->string('signed_file_mime', 100)->nullable();
                $table->string('signer_name', 150)->comment('Name typed by the customer.');
                $table->string('signed_ip', 45)->nullable();
                $table->string('signed_user_agent')->nullable();
                $table->timestamp('signed_at');
                $table->enum('status', ['active', 'superseded', 'cancelled'])->default('active');
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancelled_reason', 500)->nullable();
                $table->timestamps();

                $table->string('active_key', 255)->nullable()->virtualAs(
                    "IF(status = 'active', CONCAT_WS('|', customer_id, classified_listing_id, contract_template_id, template_version), NULL)"
                );

                $table->foreign('customer_id', 'cc_customer_foreign')->references('id')->on('customers')->nullOnDelete();
                $table->foreign('contract_template_id', 'cc_template_foreign')->references('id')->on('classified_contract_templates')->restrictOnDelete();
                $table->foreign('classified_listing_id', 'cc_listing_foreign')->references('id')->on('classified_listings')->nullOnDelete();
                $table->foreign('classified_category_id', 'cc_category_foreign')->references('id')->on('classified_categories')->nullOnDelete();
                $table->foreign('country_id', 'cc_country_foreign')->references('id')->on('countries')->nullOnDelete();

                $table->unique('active_key', 'cc_one_active_signature_unique');
                $table->index(['customer_id', 'status', 'signed_at'], 'cc_customer_status_index');
                $table->index(['classified_listing_id', 'status'], 'cc_listing_status_index');
                $table->index(['country_id', 'classified_category_id', 'signed_at'], 'cc_country_category_index');
                $table->index('contract_template_id', 'cc_template_index');
            });
        }

        if (! Schema::hasColumn('classified_inquiries', 'customer_contract_id')) {
            Schema::table('classified_inquiries', function (Blueprint $table) {
                $table->uuid('customer_contract_id')->nullable()->after('customer_id');
                $table->foreign('customer_contract_id', 'ci_customer_contract_foreign')->references('id')->on('customer_contracts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('classified_inquiries', 'customer_contract_id')) {
            Schema::table('classified_inquiries', function (Blueprint $table) {
                $table->dropForeign('ci_customer_contract_foreign');
                $table->dropColumn('customer_contract_id');
            });
        }

        Schema::dropIfExists('customer_contracts');
    }
};
