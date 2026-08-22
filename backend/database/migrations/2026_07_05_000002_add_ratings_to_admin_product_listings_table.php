<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_product_listings', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->integer('rating_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('admin_product_listings', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });
    }
};
