<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_campaign_sample_custom_attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketer_campaign_sample_id');
            $table->foreign('marketer_campaign_sample_id', 'mcscav_sample_id_fk')
                ->references('id')->on('marketer_campaign_samples')->cascadeOnDelete();
            $table->uuid('product_custom_attribute_id')->nullable();
            $table->foreign('product_custom_attribute_id', 'mcscav_product_custom_attribute_id_fk')
                ->references('id')->on('product_custom_attributes')->nullOnDelete();
            // Snapshotted when the vendor submits, so later edits/deletion of the
            // attribute definition never change what a past sample record shows.
            $table->string('label');
            $table->string('unit')->nullable();
            $table->string('value');
            $table->timestamps();

            $table->index('marketer_campaign_sample_id', 'mcscav_sample_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_campaign_sample_custom_attribute_values');
    }
};
