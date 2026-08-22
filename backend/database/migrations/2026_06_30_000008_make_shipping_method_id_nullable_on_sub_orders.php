<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function ($table) {
            $table->uuid('shipping_method_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function ($table) {
            $table->uuid('shipping_method_id')->nullable(false)->change();
        });
    }
};
