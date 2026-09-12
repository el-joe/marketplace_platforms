<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->boolean('has_order_notes')->default(false)
                  ->comment('If true, an order-notes textarea is shown to the customer on this listing');
            $table->string('size_guide_image_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropColumn(['has_order_notes', 'size_guide_image_url']);
        });
    }
};
