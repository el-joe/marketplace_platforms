<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketer_listings', function (Blueprint $table) {
            if(!Schema::hasColumn('marketer_listings', 'listing_category')) {
                $table->enum('listing_category', ['product', 'travel', 'classified'])
                    ->default('product')
                    ->after('marketer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketer_listings', function (Blueprint $table) {
            $table->dropColumn('listing_category');
        });
    }
};
